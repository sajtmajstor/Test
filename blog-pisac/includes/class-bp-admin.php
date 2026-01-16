<?php

class BP_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'wp_ajax_bp_generate_plan', array( __CLASS__, 'handle_generate_plan' ) );
        add_action( 'wp_ajax_bp_add_topic', array( __CLASS__, 'handle_add_topic' ) );
        add_action( 'wp_ajax_bp_write_topic', array( __CLASS__, 'handle_write_topic' ) );
        add_action( 'admin_post_bp_upload_csv', array( __CLASS__, 'handle_csv_upload' ) );
    }

    public static function register_menu() {
        add_menu_page(
            __( 'Blog Pisac', 'blog-pisac' ),
            __( 'Blog Pisac', 'blog-pisac' ),
            'manage_options',
            'blog-pisac',
            array( __CLASS__, 'render_writing_page' ),
            'dashicons-edit'
        );

        add_submenu_page(
            'blog-pisac',
            __( 'Pisanje', 'blog-pisac' ),
            __( 'Pisanje', 'blog-pisac' ),
            'manage_options',
            'blog-pisac',
            array( __CLASS__, 'render_writing_page' )
        );

        add_submenu_page(
            'blog-pisac',
            __( 'Spisak tema', 'blog-pisac' ),
            __( 'Spisak tema', 'blog-pisac' ),
            'manage_options',
            'blog-pisac-topics',
            array( __CLASS__, 'render_topics_page' )
        );

        add_submenu_page(
            'blog-pisac',
            __( 'Planiranje', 'blog-pisac' ),
            __( 'Planiranje', 'blog-pisac' ),
            'manage_options',
            'blog-pisac-planning',
            array( __CLASS__, 'render_planning_page' )
        );
    }

    public static function render_writing_page() {
        $settings   = BP_Settings::get_settings();
        $topics     = self::get_topics();
        $categories = get_categories( array( 'hide_empty' => false ) );
        ?>
        <div class="wrap bp-writing">
            <h1><?php esc_html_e( 'Pisanje', 'blog-pisac' ); ?></h1>

            <div class="bp-card">
                <h2><?php esc_html_e( 'Automatsko pisanje (cron)', 'blog-pisac' ); ?></h2>
                <p><?php esc_html_e( 'Podešavanje intervala nalazi se u podešavanjima. Ovde možete videti trenutni interval.', 'blog-pisac' ); ?></p>
                <p><strong><?php esc_html_e( 'Interval:', 'blog-pisac' ); ?></strong> <?php echo esc_html( (int) $settings['cron_interval_hours'] ); ?> <?php esc_html_e( 'sati', 'blog-pisac' ); ?></p>
            </div>

            <div class="bp-card">
                <h2><?php esc_html_e( 'Ručno pisanje', 'blog-pisac' ); ?></h2>
                <input type="text" id="bp-topic-filter" placeholder="<?php esc_attr_e( 'Pretraga po naslovu ili kategoriji...', 'blog-pisac' ); ?>" class="regular-text" />
                <select id="bp-topic-select" class="regular-text">
                    <?php foreach ( $topics as $topic ) : ?>
                        <option value="<?php echo esc_attr( $topic->ID ); ?>" data-title="<?php echo esc_attr( strtolower( $topic->post_title ) ); ?>" data-category="<?php echo esc_attr( strtolower( self::get_category_name( $topic ) ) ); ?>">
                            <?php echo esc_html( $topic->post_title ); ?> (<?php echo esc_html( self::get_category_name( $topic ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary" id="bp-write-topic" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bp_write_topic' ) ); ?>">
                    <?php esc_html_e( 'Napiši', 'blog-pisac' ); ?>
                </button>
                <div class="bp-status" id="bp-writing-status"></div>
            </div>

            <div class="bp-card">
                <h2><?php esc_html_e( 'Status', 'blog-pisac' ); ?></h2>
                <?php
                $last_written = get_option( 'bp_last_written', array() );
                if ( ! empty( $last_written['title'] ) ) :
                    ?>
                    <p><strong><?php esc_html_e( 'Poslednji napisan tekst:', 'blog-pisac' ); ?></strong> <?php echo esc_html( $last_written['title'] ); ?></p>
                    <p><strong><?php esc_html_e( 'Vreme:', 'blog-pisac' ); ?></strong> <?php echo esc_html( $last_written['time'] ); ?></p>
                    <?php
                else :
                    ?>
                    <p><?php esc_html_e( 'Nema zapisa.', 'blog-pisac' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function render_topics_page() {
        $topics = self::get_topics();
        $users  = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
        $categories = get_categories( array( 'hide_empty' => false ) );
        ?>
        <div class="wrap bp-topics">
            <h1><?php esc_html_e( 'Spisak tema', 'blog-pisac' ); ?></h1>

            <div class="bp-card">
                <h2><?php esc_html_e( 'Dodaj novu temu', 'blog-pisac' ); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'bp_add_topic', 'bp_add_topic_nonce' ); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="bp-topic-title"><?php esc_html_e( 'Naslov članka', 'blog-pisac' ); ?></label></th>
                            <td><input type="text" id="bp-topic-title" name="bp_topic[title]" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-topic-description"><?php esc_html_e( 'Opis članka', 'blog-pisac' ); ?></label></th>
                            <td><textarea id="bp-topic-description" name="bp_topic[description]" class="large-text" rows="3" required></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-topic-keywords"><?php esc_html_e( 'SEO ključne reči', 'blog-pisac' ); ?></label></th>
                            <td><input type="text" id="bp-topic-keywords" name="bp_topic[keywords]" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-topic-category"><?php esc_html_e( 'Kategorija', 'blog-pisac' ); ?></label></th>
                            <td>
                                <select id="bp-topic-category" name="bp_topic[category_id]">
                                    <?php foreach ( $categories as $category ) : ?>
                                        <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-topic-word-count"><?php esc_html_e( 'Broj reči', 'blog-pisac' ); ?></label></th>
                            <td><input type="number" id="bp-topic-word-count" name="bp_topic[word_count]" class="small-text" value="800"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-topic-author"><?php esc_html_e( 'Autor/Editor', 'blog-pisac' ); ?></label></th>
                            <td>
                                <select id="bp-topic-author" name="bp_topic[assigned_user]">
                                    <?php foreach ( $users as $user ) : ?>
                                        <option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p><button type="submit" class="button button-primary" name="bp_topic_submit" value="1"><?php esc_html_e( 'Dodaj temu', 'blog-pisac' ); ?></button></p>
                </form>
            </div>

            <div class="bp-card">
                <h2><?php esc_html_e( 'CSV uvoz', 'blog-pisac' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'bp_upload_csv', 'bp_upload_csv_nonce' ); ?>
                    <input type="hidden" name="action" value="bp_upload_csv" />
                    <input type="file" name="bp_csv_file" accept=".csv" required />
                    <button class="button" type="submit"><?php esc_html_e( 'Uvezi CSV', 'blog-pisac' ); ?></button>
                    <p class="description"><?php esc_html_e( 'Kolone: naslov, opis, ključne reči, kategorija, broj_reči, autor, status', 'blog-pisac' ); ?></p>
                </form>
            </div>

            <div class="bp-card">
                <h2><?php esc_html_e( 'Spisak tema', 'blog-pisac' ); ?></h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Naslov članka', 'blog-pisac' ); ?></th>
                            <th><?php esc_html_e( 'Opis', 'blog-pisac' ); ?></th>
                            <th><?php esc_html_e( 'SEO ključne reči', 'blog-pisac' ); ?></th>
                            <th><?php esc_html_e( 'Kategorija', 'blog-pisac' ); ?></th>
                            <th><?php esc_html_e( 'Broj reči', 'blog-pisac' ); ?></th>
                            <th><?php esc_html_e( 'Autor/Editor', 'blog-pisac' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'blog-pisac' ); ?></th>
                            <th><?php esc_html_e( 'Akcije', 'blog-pisac' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $topics ) ) : ?>
                            <tr><td colspan="8"><?php esc_html_e( 'Nema tema.', 'blog-pisac' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $topics as $topic ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $topic->post_title ); ?></td>
                                    <td><?php echo esc_html( wp_trim_words( get_post_meta( $topic->ID, 'bp_description', true ), 15 ) ); ?></td>
                                    <td><?php echo esc_html( get_post_meta( $topic->ID, 'bp_keywords', true ) ); ?></td>
                                    <td><?php echo esc_html( self::get_category_name( $topic ) ); ?></td>
                                    <td><?php echo esc_html( get_post_meta( $topic->ID, 'bp_word_count', true ) ); ?></td>
                                    <td><?php echo esc_html( self::get_user_name( $topic ) ); ?></td>
                                    <td><?php echo esc_html( get_post_meta( $topic->ID, 'bp_status', true ) ); ?></td>
                                    <td><a href="<?php echo esc_url( get_edit_post_link( $topic->ID ) ); ?>"><?php esc_html_e( 'Izmeni', 'blog-pisac' ); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

        if ( isset( $_POST['bp_topic_submit'] ) && check_admin_referer( 'bp_add_topic', 'bp_add_topic_nonce' ) ) {
            $data = wp_unslash( $_POST['bp_topic'] ?? array() );
            BP_Topic::create_topic( $data );
            wp_safe_redirect( admin_url( 'admin.php?page=blog-pisac-topics' ) );
            exit;
        }
    }

    public static function render_planning_page() {
        $categories = get_categories( array( 'hide_empty' => false ) );
        $users      = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
        ?>
        <div class="wrap bp-planning">
            <h1><?php esc_html_e( 'Planiranje', 'blog-pisac' ); ?></h1>
            <div class="bp-card">
                <form id="bp-planning-form">
                    <?php wp_nonce_field( 'bp_generate_plan', 'bp_generate_plan_nonce' ); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="bp-plan-title"><?php esc_html_e( 'Naslov članka', 'blog-pisac' ); ?></label></th>
                            <td><input type="text" id="bp-plan-title" name="title" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-plan-description"><?php esc_html_e( 'Opis članka', 'blog-pisac' ); ?></label></th>
                            <td><textarea id="bp-plan-description" name="description" class="large-text" rows="3" required></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-plan-keywords"><?php esc_html_e( 'Ključne reči', 'blog-pisac' ); ?></label></th>
                            <td><input type="text" id="bp-plan-keywords" name="keywords" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-plan-category"><?php esc_html_e( 'Kategorija', 'blog-pisac' ); ?></label></th>
                            <td>
                                <select id="bp-plan-category" name="category_id">
                                    <?php foreach ( $categories as $category ) : ?>
                                        <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-plan-word-count"><?php esc_html_e( 'Broj reči', 'blog-pisac' ); ?></label></th>
                            <td><input type="number" id="bp-plan-word-count" name="word_count" class="small-text" value="800"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="bp-plan-author"><?php esc_html_e( 'Autor/Editor', 'blog-pisac' ); ?></label></th>
                            <td>
                                <select id="bp-plan-author" name="assigned_user">
                                    <?php foreach ( $users as $user ) : ?>
                                        <option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary" id="bp-generate-plan" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bp_generate_plan' ) ); ?>">
                        <?php esc_html_e( 'Predloži teme', 'blog-pisac' ); ?>
                    </button>
                </form>
            </div>

            <div class="bp-card" id="bp-plan-results" style="display:none;">
                <h2><?php esc_html_e( 'Predložene teme', 'blog-pisac' ); ?></h2>
                <div class="bp-plan-list"></div>
            </div>
        </div>
        <?php
    }

    public static function get_topics() {
        $query = new WP_Query(
            array(
                'post_type'      => BP_TOPIC_POST_TYPE,
                'posts_per_page' => 100,
                'post_status'    => array( 'publish', 'draft' ),
            )
        );

        return $query->posts;
    }

    public static function get_category_name( $topic ) {
        $category_id = (int) get_post_meta( $topic->ID, 'bp_category_id', true );
        if ( $category_id ) {
            $category = get_category( $category_id );
            if ( $category && ! is_wp_error( $category ) ) {
                return $category->name;
            }
        }

        return __( 'Bez kategorije', 'blog-pisac' );
    }

    public static function get_user_name( $topic ) {
        $user_id = (int) get_post_meta( $topic->ID, 'bp_assigned_user', true );
        if ( $user_id ) {
            $user = get_user_by( 'id', $user_id );
            if ( $user ) {
                return $user->display_name;
            }
        }

        return __( 'Nije dodeljen', 'blog-pisac' );
    }

    public static function handle_generate_plan() {
        check_ajax_referer( 'bp_generate_plan', 'nonce' );

        $data = array(
            'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'keywords'     => sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
            'category_id'  => (int) ( $_POST['category_id'] ?? 0 ),
            'word_count'   => (int) ( $_POST['word_count'] ?? 0 ),
            'assigned_user'=> (int) ( $_POST['assigned_user'] ?? 0 ),
        );

        $settings = BP_Settings::get_settings();
        $category = $data['category_id'] ? get_category( $data['category_id'] ) : null;

        $prompt = sprintf(
            "Predloži 4 SEO optimizovane teme za blog.\nNaslov: %s\nOpis: %s\nKljučne reči: %s\nKategorija: %s\nBroj reči: %d\nInstrukcije sajta: %s\nGlobalni stil: %s\nVrati JSON niz sa poljima title, description.",
            $data['title'],
            $data['description'],
            $data['keywords'],
            $category ? $category->name : '',
            $data['word_count'],
            $settings['site_instructions'],
            $settings['global_style']
        );

        $messages = array(
            array(
                'role'    => 'system',
                'content' => 'Ti si SEO content planner i blog editor.',
            ),
            array(
                'role'    => 'user',
                'content' => $prompt,
            ),
        );

        $result = BP_OpenRouter::chat_completion( $messages, $settings, array( 'task' => 'planning' ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $suggestions = json_decode( $result, true );

        if ( ! is_array( $suggestions ) ) {
            $suggestions = array(
                array(
                    'title'       => $data['title'] ? $data['title'] : __( 'Predlog teme', 'blog-pisac' ),
                    'description' => $data['description'],
                ),
            );
        }

        wp_send_json_success(
            array(
                'suggestions' => $suggestions,
                'defaults'    => $data,
            )
        );
    }

    public static function handle_add_topic() {
        check_ajax_referer( 'bp_admin_nonce', 'nonce' );

        $data = array(
            'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'keywords'     => sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
            'category_id'  => (int) ( $_POST['category_id'] ?? 0 ),
            'word_count'   => (int) ( $_POST['word_count'] ?? 0 ),
            'assigned_user'=> (int) ( $_POST['assigned_user'] ?? 0 ),
            'status'       => 'pending',
        );

        $topic_id = BP_Topic::create_topic( $data );
        if ( is_wp_error( $topic_id ) ) {
            wp_send_json_error( array( 'message' => $topic_id->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Tema je dodata.', 'blog-pisac' ) ) );
    }

    public static function handle_write_topic() {
        check_ajax_referer( 'bp_write_topic', 'nonce' );

        $topic_id = (int) ( $_POST['topic_id'] ?? 0 );
        $topic    = get_post( $topic_id );

        if ( ! $topic || BP_TOPIC_POST_TYPE !== $topic->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Tema nije pronađena.', 'blog-pisac' ) ) );
        }

        $result = self::write_topic_to_post( $topic );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Tekst je napisan.', 'blog-pisac' ) ) );
    }

    public static function cron_write_next_topic() {
        $topic = BP_Topic::get_next_pending_topic();
        if ( ! $topic ) {
            return;
        }

        self::write_topic_to_post( $topic );
    }

    public static function write_topic_to_post( $topic ) {
        $settings = BP_Settings::get_settings();
        $category_id = (int) get_post_meta( $topic->ID, 'bp_category_id', true );
        $author_id   = (int) get_post_meta( $topic->ID, 'bp_assigned_user', true );
        $word_count  = (int) get_post_meta( $topic->ID, 'bp_word_count', true );
        $keywords    = get_post_meta( $topic->ID, 'bp_keywords', true );
        $description = get_post_meta( $topic->ID, 'bp_description', true );

        $author_style = $author_id ? get_user_meta( $author_id, 'bp_user_writing_style', true ) : '';
        $category_instructions = $category_id ? get_term_meta( $category_id, 'bp_category_instructions', true ) : '';
        $category_image_instructions = $category_id ? get_term_meta( $category_id, 'bp_category_image_instructions', true ) : '';

        $internal_links = self::get_internal_links( $settings );

        $prompt = sprintf(
            "Napiši SEO optimizovan članak.\nNaslov: %s\nOpis: %s\nKljučne reči: %s\nBroj reči: %d\nInstrukcije sajta: %s\nGlobalni stil: %s\nSEO instrukcije: %s\nInstrukcije kategorije: %s\nInstrukcije autora: %s\nInterni linkovi: %s\nTekst napiši u HTML-u sa H2/H3 podnaslovima.",
            $topic->post_title,
            $description,
            $keywords,
            $word_count,
            $settings['site_instructions'],
            $settings['global_style'],
            $settings['seo_instructions'],
            $category_instructions,
            $author_style,
            implode( ', ', $internal_links )
        );

        $messages = array(
            array(
                'role'    => 'system',
                'content' => 'Ti si profesionalni content writer.',
            ),
            array(
                'role'    => 'user',
                'content' => $prompt,
            ),
        );

        $result = BP_OpenRouter::chat_completion( $messages, $settings, array( 'task' => 'content' ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $post_id = wp_insert_post(
            array(
                'post_title'   => $topic->post_title,
                'post_content' => wp_kses_post( $result ),
                'post_status'  => $settings['publish_status'],
                'post_author'  => $author_id ? $author_id : $settings['default_author'],
                'post_category'=> $category_id ? array( $category_id ) : array(),
            )
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $topic->ID, 'bp_status', 'written' );
        update_option(
            'bp_last_written',
            array(
                'title' => $topic->post_title,
                'time'  => current_time( 'mysql' ),
            )
        );

        self::queue_image_selection( $post_id, $topic, $category_image_instructions );

        return $post_id;
    }

    public static function queue_image_selection( $post_id, $topic, $category_image_instructions ) {
        $settings = BP_Settings::get_settings();
        $image_prompt = sprintf(
            "Na osnovu teksta ispod predloži ključne reči za pretragu slika.\nInstrukcije za slike: %s\nInstrukcije kategorije: %s\nTekst: %s",
            $settings['image_instructions'],
            $category_image_instructions,
            wp_strip_all_tags( get_post_field( 'post_content', $post_id ) )
        );

        $messages = array(
            array(
                'role'    => 'system',
                'content' => 'Ti si editor koji bira slike za blog.',
            ),
            array(
                'role'    => 'user',
                'content' => $image_prompt,
            ),
        );

        $keywords = BP_OpenRouter::chat_completion( $messages, $settings, array( 'task' => 'images' ) );
        if ( ! is_wp_error( $keywords ) ) {
            update_post_meta( $post_id, 'bp_image_keywords', sanitize_text_field( $keywords ) );
        }
    }

    public static function get_internal_links( $settings ) {
        $sitemaps = array_filter( array(
            $settings['sitemap_post'],
            $settings['sitemap_page'],
            $settings['sitemap_product'],
            $settings['sitemap_custom'],
        ) );

        $links = array();
        foreach ( $sitemaps as $sitemap ) {
            $response = wp_remote_get( $sitemap, array( 'timeout' => 10 ) );
            if ( is_wp_error( $response ) ) {
                continue;
            }
            $body = wp_remote_retrieve_body( $response );
            $xml  = simplexml_load_string( $body );
            if ( ! $xml ) {
                continue;
            }
            foreach ( $xml->url as $url ) {
                $loc = (string) $url->loc;
                if ( $loc ) {
                    $links[] = $loc;
                }
            }
        }

        shuffle( $links );

        return array_slice( $links, 0, 20 );
    }

    public static function handle_csv_upload() {
        if ( ! check_admin_referer( 'bp_upload_csv', 'bp_upload_csv_nonce' ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=blog-pisac-topics' ) );
            exit;
        }

        if ( empty( $_FILES['bp_csv_file']['tmp_name'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=blog-pisac-topics' ) );
            exit;
        }

        $file = fopen( $_FILES['bp_csv_file']['tmp_name'], 'r' );
        if ( ! $file ) {
            wp_safe_redirect( admin_url( 'admin.php?page=blog-pisac-topics' ) );
            exit;
        }

        $settings = BP_Settings::get_settings();
        $row_index = 0;
        while ( ( $row = fgetcsv( $file ) ) !== false ) {
            $row_index++;
            if ( $row_index === 1 && preg_match( '/naslov/i', $row[0] ?? '' ) ) {
                continue;
            }

            $category_name = sanitize_text_field( $row[3] ?? '' );
            $category_id = 0;
            if ( $category_name ) {
                $category = get_category_by_slug( sanitize_title( $category_name ) );
                if ( $category ) {
                    $category_id = $category->term_id;
                }
            }

            $author_login = sanitize_text_field( $row[5] ?? '' );
            $author_id    = $settings['default_author'];
            if ( $author_login ) {
                $user = get_user_by( 'login', $author_login );
                if ( $user ) {
                    $author_id = $user->ID;
                }
            }

            BP_Topic::create_topic(
                array(
                    'title'        => sanitize_text_field( $row[0] ?? '' ),
                    'description'  => sanitize_textarea_field( $row[1] ?? '' ),
                    'keywords'     => sanitize_text_field( $row[2] ?? '' ),
                    'category_id'  => $category_id ? $category_id : $settings['default_category'],
                    'word_count'   => (int) ( $row[4] ?? 0 ),
                    'assigned_user'=> $author_id,
                    'status'       => sanitize_text_field( $row[6] ?? 'pending' ),
                )
            );
        }

        fclose( $file );

        wp_safe_redirect( admin_url( 'admin.php?page=blog-pisac-topics' ) );
        exit;
    }
}
