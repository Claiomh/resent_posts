<?php
/*
Plugin Name: Resent Posts
Description: A custom plugin that displays recent posts and allows configuration from the admin panel.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Проверка наличия PSR\Log и уведомление в админке
function resent_posts_check_psr_log() {
    if (!class_exists('Psr\Log\LoggerInterface')) {
        add_action('admin_notices', 'resent_posts_psr_log_notice');
    }
}

function resent_posts_psr_log_notice() {
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><?php _e('Resent Posts: PSR\Log library not found. Logging is disabled. Please install PSR\Log via Composer to enable logging.', 'resent-posts'); ?></p>
    </div>
    <?php
}

add_action('admin_init', 'resent_posts_check_psr_log');

// Если PSR\Log доступен, продолжаем загрузку логгера
if (class_exists('Psr\Log\LoggerInterface')) {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/MyLogger.php';

    function my_custom_logger() {
        static $logger = null;

        if (null === $logger) {
            if (class_exists('Psr\Log\LoggerInterface')) {
                $logger = new MyLogger();
            }
        }

        return $logger;
    }
} else {
    function my_custom_logger() {
        return null;
    }
}

register_activation_hook(__FILE__, 'resent_posts_activation');
register_deactivation_hook(__FILE__, 'resent_posts_deactivation');

function resent_posts_activation() {
    $logger = my_custom_logger();
    if ($logger) {
        $logger->info('Resent Posts activated.');
    }
}

function resent_posts_deactivation() {
    $logger = my_custom_logger();
    if ($logger) {
        $logger->info('Resent Posts deactivated.');
    }
}

// Add settings page
add_action('admin_menu', 'resent_posts_add_settings_page');
function resent_posts_add_settings_page() {
    add_options_page(
        'Resent Posts Settings',
        'Resent Posts',
        'manage_options',
        'resent-posts',
        'resent_posts_render_settings_page'
    );
}

function resent_posts_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Resent Posts Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('resent_posts_plugin_options');
            do_settings_sections('resent-posts');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'resent_posts_register_settings');
function resent_posts_register_settings() {
    register_setting('resent_posts_plugin_options', 'resent_posts_plugin_post_count');

    add_settings_section(
        'resent_posts_plugin_main_section',
        'Main Settings',
        null,
        'resent-posts'
    );

    add_settings_field(
        'resent_posts_plugin_post_count_field',
        'Number of Posts',
        'resent_posts_post_count_field_callback',
        'resent-posts',
        'resent_posts_plugin_main_section'
    );
}

function resent_posts_post_count_field_callback() {
    $post_count = get_option('resent_posts_plugin_post_count', 5);
    echo '<input type="number" name="resent_posts_plugin_post_count" value="' . esc_attr($post_count) . '" />';
}

// Shortcode to display recent posts
add_shortcode('my_recent_posts', 'resent_posts_display_recent_posts');
function resent_posts_display_recent_posts($atts) {
    $post_count = get_option('resent_posts_plugin_post_count', 5);
    $query = new WP_Query([
        'posts_per_page' => $post_count,
        'post_status' => 'publish',
    ]);

    if ($query->have_posts()) {
        $output = '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            $output .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
        }
        $output .= '</ul>';
        wp_reset_postdata();
        return $output;
    } else {
        return '<p>No posts found.</p>';
    }
}
