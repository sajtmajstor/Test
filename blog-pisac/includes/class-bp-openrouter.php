<?php

class BP_OpenRouter {
    public static function chat_completion( $messages, $settings = array(), $context = array() ) {
        $settings = wp_parse_args( $settings, BP_Settings::get_settings() );
        $server_token = $settings['server_token'];

        if ( empty( $server_token ) ) {
            return new WP_Error( 'bp_missing_token', __( 'Server token nije podešen.', 'blog-pisac' ) );
        }

        $response = wp_remote_post(
            self::get_server_endpoint(),
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'X-BP-Server-Token' => $server_token,
                ),
                'body'    => wp_json_encode(
                    array(
                        'model'    => $settings['model'],
                        'messages' => $messages,
                        'task'     => sanitize_text_field( $context['task'] ?? 'content' ),
                    )
                ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['content'] ) && empty( $body['choices'][0]['message']['content'] ) ) {
            return new WP_Error( 'bp_invalid_response', __( 'Server je vratio neočekivan odgovor.', 'blog-pisac' ) );
        }

        return ! empty( $body['content'] ) ? $body['content'] : $body['choices'][0]['message']['content'];
    }

    public static function get_server_endpoint() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $host = $host ? $host : 'localhost';

        return sprintf( 'https://sajtmajstor.com/ai/%s/blogger/index.php', $host );
    }
}
