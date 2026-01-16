<?php
/**
 * Plugin Name: Blog Pisac
 * Description: WordPress/WooCommerce plugin za planiranje i pisanje blog tekstova uz OpenRouter.
 * Version: 0.1.0
 * Author: OpenAI
 * Text Domain: blog-pisac
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'BP_OPTION_KEY', 'bp_settings' );

define( 'BP_TOPIC_POST_TYPE', 'bp_topic' );

define( 'BP_CRON_HOOK', 'bp_write_next_topic' );

require_once BP_PLUGIN_PATH . 'includes/class-bp-settings.php';
require_once BP_PLUGIN_PATH . 'includes/class-bp-openrouter.php';
require_once BP_PLUGIN_PATH . 'includes/class-bp-topic.php';
require_once BP_PLUGIN_PATH . 'includes/class-bp-admin.php';

register_activation_hook( __FILE__, 'bp_activate_plugin' );
register_deactivation_hook( __FILE__, 'bp_deactivate_plugin' );

function bp_activate_plugin() {
    BP_Topic::register_post_type();
    flush_rewrite_rules();
    bp_schedule_cron();
}

function bp_deactivate_plugin() {
    wp_clear_scheduled_hook( BP_CRON_HOOK );
    flush_rewrite_rules();
}

add_action( 'init', array( 'BP_Topic', 'register_post_type' ) );
add_action( 'init', array( 'BP_Topic', 'register_meta' ) );

add_action( 'plugins_loaded', function () {
    BP_Settings::init();
    BP_Admin::init();
} );

add_action( 'bp_write_next_topic', array( 'BP_Admin', 'cron_write_next_topic' ) );

function bp_schedule_cron() {
    $settings = BP_Settings::get_settings();
    $interval = isset( $settings['cron_interval_hours'] ) ? (int) $settings['cron_interval_hours'] : 0;

    if ( $interval <= 0 ) {
        wp_clear_scheduled_hook( BP_CRON_HOOK );
        return;
    }

    add_filter(
        'cron_schedules',
        function ( $schedules ) use ( $interval ) {
            $schedules['bp_custom_interval'] = array(
                'interval' => HOUR_IN_SECONDS * $interval,
                'display'  => sprintf( __( 'Every %d hours', 'blog-pisac' ), $interval ),
            );
            return $schedules;
        }
    );

    if ( ! wp_next_scheduled( BP_CRON_HOOK ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'bp_custom_interval', BP_CRON_HOOK );
    }
}

add_action(
    'update_option_' . BP_OPTION_KEY,
    function () {
        bp_schedule_cron();
    }
);
