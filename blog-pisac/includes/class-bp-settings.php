<?php

class BP_Settings {
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'category_add_form_fields', array( __CLASS__, 'render_category_fields' ) );
        add_action( 'category_edit_form_fields', array( __CLASS__, 'render_category_fields_edit' ) );
        add_action( 'created_category', array( __CLASS__, 'save_category_fields' ) );
        add_action( 'edited_category', array( __CLASS__, 'save_category_fields' ) );
        add_action( 'show_user_profile', array( __CLASS__, 'render_user_fields' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_user_fields' ) );
        add_action( 'personal_options_update', array( __CLASS__, 'save_user_fields' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_fields' ) );
    }

    public static function get_settings() {
        $defaults = array(
            'pexels_api_key'        => '',
            'server_token'          => '',
            'model'                 => 'openai/gpt-4o',
            'site_instructions'     => '',
            'global_style'          => '',
            'image_instructions'    => '',
            'images_per_article'    => 3,
            'default_author'        => get_current_user_id(),
            'default_category'      => 0,
            'seo_instructions'      => '',
            'sitemap_post'          => '',
            'sitemap_page'          => '',
            'sitemap_product'       => '',
            'sitemap_custom'        => '',
            'publish_status'        => 'draft',
            'cron_interval_hours'   => 0,
        );

        $settings = get_option( BP_OPTION_KEY, array() );

        return wp_parse_args( $settings, $defaults );
    }

    public static function register_settings() {
        register_setting( 'bp_settings_group', BP_OPTION_KEY, array( __CLASS__, 'sanitize_settings' ) );
    }

    public static function sanitize_settings( $settings ) {
        $settings['pexels_api_key']      = sanitize_text_field( $settings['pexels_api_key'] ?? '' );
        $settings['server_token']        = sanitize_text_field( $settings['server_token'] ?? '' );
        $settings['model']               = sanitize_text_field( $settings['model'] ?? '' );
        $settings['site_instructions']   = wp_kses_post( $settings['site_instructions'] ?? '' );
        $settings['global_style']        = wp_kses_post( $settings['global_style'] ?? '' );
        $settings['image_instructions']  = wp_kses_post( $settings['image_instructions'] ?? '' );
        $settings['images_per_article']  = max( 0, (int) ( $settings['images_per_article'] ?? 0 ) );
        $settings['default_author']      = (int) ( $settings['default_author'] ?? 0 );
        $settings['default_category']    = (int) ( $settings['default_category'] ?? 0 );
        $settings['seo_instructions']    = wp_kses_post( $settings['seo_instructions'] ?? '' );
        $settings['sitemap_post']        = esc_url_raw( $settings['sitemap_post'] ?? '' );
        $settings['sitemap_page']        = esc_url_raw( $settings['sitemap_page'] ?? '' );
        $settings['sitemap_product']     = esc_url_raw( $settings['sitemap_product'] ?? '' );
        $settings['sitemap_custom']      = esc_url_raw( $settings['sitemap_custom'] ?? '' );
        $settings['publish_status']      = in_array( $settings['publish_status'] ?? 'draft', array( 'draft', 'publish' ), true ) ? $settings['publish_status'] : 'draft';
        $settings['cron_interval_hours'] = max( 0, (int) ( $settings['cron_interval_hours'] ?? 0 ) );

        return $settings;
    }

    public static function add_settings_page() {
        add_submenu_page(
            'blog-pisac',
            __( 'Podešavanja', 'blog-pisac' ),
            __( 'Podešavanja', 'blog-pisac' ),
            'manage_options',
            'blog-pisac-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'blog-pisac' ) !== false ) {
            wp_enqueue_style( 'bp-admin', BP_PLUGIN_URL . 'assets/admin.css', array(), '0.1.0' );
            wp_enqueue_script( 'bp-admin', BP_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), '0.1.0', true );
            wp_localize_script(
                'bp-admin',
                'bpAdmin',
                array(
                    'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                    'nonce'     => wp_create_nonce( 'bp_admin_nonce' ),
                )
            );
        }
    }

    public static function render_settings_page() {
        $settings = self::get_settings();
        $users    = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
        $categories = get_categories( array( 'hide_empty' => false ) );
        ?>
        <div class="wrap bp-settings">
            <h1><?php esc_html_e( 'Blog Pisac podešavanja', 'blog-pisac' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'bp_settings_group' ); ?>
                <h2><?php esc_html_e( 'API pristup', 'blog-pisac' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bp-pexels-api-key"><?php esc_html_e( 'Pexels API ključ', 'blog-pisac' ); ?></label></th>
                        <td><input type="password" id="bp-pexels-api-key" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[pexels_api_key]" value="<?php echo esc_attr( $settings['pexels_api_key'] ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-server-token"><?php esc_html_e( 'Server token', 'blog-pisac' ); ?></label></th>
                        <td>
                            <input type="text" id="bp-server-token" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[server_token]" value="<?php echo esc_attr( $settings['server_token'] ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Token se koristi za autorizaciju poziva prema sajtmajstor.com/ai/{domen}/blogger.', 'blog-pisac' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-model"><?php esc_html_e( 'Model', 'blog-pisac' ); ?></label></th>
                        <td>
                            <select id="bp-model" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[model]">
                                <?php
                                $models = array(
                                    'openai/gpt-4o'      => 'GPT-4o',
                                    'openai/gpt-4o-mini' => 'GPT-4o Mini',
                                    'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
                                    'google/gemini-1.5-pro' => 'Gemini 1.5 Pro',
                                    'mistralai/mistral-large' => 'Mistral Large',
                                );
                                foreach ( $models as $value => $label ) :
                                    ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['model'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php
                                endforeach;
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Instrukcije sajta', 'blog-pisac' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bp-site-instructions"><?php esc_html_e( 'Opis sajta', 'blog-pisac' ); ?></label></th>
                        <td><textarea id="bp-site-instructions" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[site_instructions]" rows="4" class="large-text"><?php echo esc_textarea( $settings['site_instructions'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-global-style"><?php esc_html_e( 'Globalni stil pisanja', 'blog-pisac' ); ?></label></th>
                        <td><textarea id="bp-global-style" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[global_style]" rows="4" class="large-text"><?php echo esc_textarea( $settings['global_style'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-seo-instructions"><?php esc_html_e( 'SEO instrukcije', 'blog-pisac' ); ?></label></th>
                        <td><textarea id="bp-seo-instructions" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[seo_instructions]" rows="4" class="large-text"><?php echo esc_textarea( $settings['seo_instructions'] ); ?></textarea></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Instrukcije za slike', 'blog-pisac' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bp-image-instructions"><?php esc_html_e( 'Globalne instrukcije za slike', 'blog-pisac' ); ?></label></th>
                        <td><textarea id="bp-image-instructions" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[image_instructions]" rows="4" class="large-text"><?php echo esc_textarea( $settings['image_instructions'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-images-per-article"><?php esc_html_e( 'Broj slika po članku', 'blog-pisac' ); ?></label></th>
                        <td><input type="number" id="bp-images-per-article" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[images_per_article]" value="<?php echo esc_attr( $settings['images_per_article'] ); ?>" min="0" class="small-text" /></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Podrazumevane vrednosti', 'blog-pisac' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bp-default-author"><?php esc_html_e( 'Default autor', 'blog-pisac' ); ?></label></th>
                        <td>
                            <select id="bp-default-author" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[default_author]">
                                <?php foreach ( $users as $user ) : ?>
                                    <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $settings['default_author'], $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-default-category"><?php esc_html_e( 'Default kategorija', 'blog-pisac' ); ?></label></th>
                        <td>
                            <select id="bp-default-category" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[default_category]">
                                <option value="0"><?php esc_html_e( 'Bez kategorije', 'blog-pisac' ); ?></option>
                                <?php foreach ( $categories as $category ) : ?>
                                    <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $settings['default_category'], $category->term_id ); ?>><?php echo esc_html( $category->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-publish-status"><?php esc_html_e( 'Status objave', 'blog-pisac' ); ?></label></th>
                        <td>
                            <select id="bp-publish-status" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[publish_status]">
                                <option value="draft" <?php selected( $settings['publish_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'blog-pisac' ); ?></option>
                                <option value="publish" <?php selected( $settings['publish_status'], 'publish' ); ?>><?php esc_html_e( 'Publish', 'blog-pisac' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Sitemap linkovi', 'blog-pisac' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bp-sitemap-post"><?php esc_html_e( 'Post sitemap', 'blog-pisac' ); ?></label></th>
                        <td><input type="url" id="bp-sitemap-post" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[sitemap_post]" value="<?php echo esc_attr( $settings['sitemap_post'] ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-sitemap-page"><?php esc_html_e( 'Page sitemap', 'blog-pisac' ); ?></label></th>
                        <td><input type="url" id="bp-sitemap-page" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[sitemap_page]" value="<?php echo esc_attr( $settings['sitemap_page'] ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-sitemap-product"><?php esc_html_e( 'Product sitemap', 'blog-pisac' ); ?></label></th>
                        <td><input type="url" id="bp-sitemap-product" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[sitemap_product]" value="<?php echo esc_attr( $settings['sitemap_product'] ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bp-sitemap-custom"><?php esc_html_e( 'Custom sitemap', 'blog-pisac' ); ?></label></th>
                        <td><input type="url" id="bp-sitemap-custom" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[sitemap_custom]" value="<?php echo esc_attr( $settings['sitemap_custom'] ); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Pisanje (cron)', 'blog-pisac' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="bp-cron-interval"><?php esc_html_e( 'Interval u satima', 'blog-pisac' ); ?></label></th>
                        <td>
                            <input type="number" id="bp-cron-interval" name="<?php echo esc_attr( BP_OPTION_KEY ); ?>[cron_interval_hours]" value="<?php echo esc_attr( $settings['cron_interval_hours'] ); ?>" min="0" class="small-text" />
                            <p class="description"><?php esc_html_e( 'Unesite 0 da biste isključili cron.', 'blog-pisac' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function render_category_fields() {
        ?>
        <div class="form-field">
            <label for="bp-category-instructions"><?php esc_html_e( 'Instrukcije za kategoriju', 'blog-pisac' ); ?></label>
            <textarea id="bp-category-instructions" name="bp_category_instructions" rows="3" class="large-text"></textarea>
        </div>
        <div class="form-field">
            <label for="bp-category-image-instructions"><?php esc_html_e( 'Instrukcije za slike', 'blog-pisac' ); ?></label>
            <textarea id="bp-category-image-instructions" name="bp_category_image_instructions" rows="3" class="large-text"></textarea>
        </div>
        <?php
    }

    public static function render_category_fields_edit( $term ) {
        $instructions = get_term_meta( $term->term_id, 'bp_category_instructions', true );
        $image_instructions = get_term_meta( $term->term_id, 'bp_category_image_instructions', true );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="bp-category-instructions"><?php esc_html_e( 'Instrukcije za kategoriju', 'blog-pisac' ); ?></label></th>
            <td><textarea id="bp-category-instructions" name="bp_category_instructions" rows="3" class="large-text"><?php echo esc_textarea( $instructions ); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="bp-category-image-instructions"><?php esc_html_e( 'Instrukcije za slike', 'blog-pisac' ); ?></label></th>
            <td><textarea id="bp-category-image-instructions" name="bp_category_image_instructions" rows="3" class="large-text"><?php echo esc_textarea( $image_instructions ); ?></textarea></td>
        </tr>
        <?php
    }

    public static function save_category_fields( $term_id ) {
        if ( isset( $_POST['bp_category_instructions'] ) ) {
            update_term_meta( $term_id, 'bp_category_instructions', wp_kses_post( wp_unslash( $_POST['bp_category_instructions'] ) ) );
        }
        if ( isset( $_POST['bp_category_image_instructions'] ) ) {
            update_term_meta( $term_id, 'bp_category_image_instructions', wp_kses_post( wp_unslash( $_POST['bp_category_image_instructions'] ) ) );
        }
    }

    public static function render_user_fields( $user ) {
        $style = get_user_meta( $user->ID, 'bp_user_writing_style', true );
        ?>
        <h2><?php esc_html_e( 'Blog Pisac stil pisanja', 'blog-pisac' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="bp-user-writing-style"><?php esc_html_e( 'Instrukcije za stil pisanja', 'blog-pisac' ); ?></label></th>
                <td>
                    <textarea id="bp-user-writing-style" name="bp_user_writing_style" rows="4" class="large-text"><?php echo esc_textarea( $style ); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_user_fields( $user_id ) {
        if ( isset( $_POST['bp_user_writing_style'] ) ) {
            update_user_meta( $user_id, 'bp_user_writing_style', wp_kses_post( wp_unslash( $_POST['bp_user_writing_style'] ) ) );
        }
    }
}
