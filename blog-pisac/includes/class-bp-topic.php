<?php

class BP_Topic {
    public static function register_post_type() {
        register_post_type(
            BP_TOPIC_POST_TYPE,
            array(
                'labels' => array(
                    'name'          => __( 'Blog teme', 'blog-pisac' ),
                    'singular_name' => __( 'Blog tema', 'blog-pisac' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_menu' => 'blog-pisac',
                'supports'     => array( 'title', 'editor' ),
                'capability_type' => 'post',
            )
        );
    }

    public static function register_meta() {
        $meta_keys = array(
            'bp_description'   => 'string',
            'bp_keywords'      => 'string',
            'bp_category_id'   => 'integer',
            'bp_word_count'    => 'integer',
            'bp_assigned_user' => 'integer',
            'bp_status'        => 'string',
        );

        foreach ( $meta_keys as $key => $type ) {
            register_post_meta(
                BP_TOPIC_POST_TYPE,
                $key,
                array(
                    'type'         => $type,
                    'single'       => true,
                    'show_in_rest' => true,
                    'auth_callback' => function () {
                        return current_user_can( 'edit_posts' );
                    },
                )
            );
        }
    }

    public static function create_topic( $data ) {
        $post_id = wp_insert_post(
            array(
                'post_type'    => BP_TOPIC_POST_TYPE,
                'post_title'   => sanitize_text_field( $data['title'] ?? '' ),
                'post_content' => wp_kses_post( $data['description'] ?? '' ),
                'post_status'  => 'publish',
            )
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, 'bp_description', wp_kses_post( $data['description'] ?? '' ) );
        update_post_meta( $post_id, 'bp_keywords', sanitize_text_field( $data['keywords'] ?? '' ) );
        update_post_meta( $post_id, 'bp_category_id', (int) ( $data['category_id'] ?? 0 ) );
        update_post_meta( $post_id, 'bp_word_count', (int) ( $data['word_count'] ?? 0 ) );
        update_post_meta( $post_id, 'bp_assigned_user', (int) ( $data['assigned_user'] ?? 0 ) );
        update_post_meta( $post_id, 'bp_status', sanitize_text_field( $data['status'] ?? 'pending' ) );

        return $post_id;
    }

    public static function get_next_pending_topic() {
        $query = new WP_Query(
            array(
                'post_type'      => BP_TOPIC_POST_TYPE,
                'posts_per_page' => 1,
                'meta_key'       => 'bp_status',
                'meta_value'     => 'pending',
                'orderby'        => 'date',
                'order'          => 'ASC',
            )
        );

        if ( empty( $query->posts ) ) {
            return null;
        }

        return $query->posts[0];
    }
}
