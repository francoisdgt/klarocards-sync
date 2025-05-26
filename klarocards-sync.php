<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin to synchronize Klaro Cards cards with Wordpress.
 * Version: 0.4.3
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// imports
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-options-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-settings.php';

// Enqueueing scripts
function kcsync_enqueue_admin_scripts($hook) {
    // checking if the current screen is the post admin screen
    $screen = get_current_screen();
    if ($screen && $screen->post_type !== 'post') return;

    wp_enqueue_script(
        'kcsync_sync_stories',
        plugins_url('/assets/js/kcsync-sync-stories.js', __FILE__),
        array('jquery'), // dependencies
        '1.0', // script version
        true
    );

    // Localizing script
    wp_localize_script('kcsync_sync_stories', 'kcsync_ajax_data', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kcsync_sync_' . get_current_user_id()) // passing a nonce to the script for security
    ));
}

add_action('admin_enqueue_scripts', 'kcsync_enqueue_admin_scripts');

function kcsync_sync_stories() {
    $nonce = 'kcsync_sync_' . get_current_user_id();
    error_log("test");

    // checking if the user has the correct permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission refusée');
    }

    // checking if the nonce is valid
    if (!check_ajax_referer($nonce)) {
        wp_send_json_error('Nonce invalide');
    }

    wp_send_json_success('Succès !');
}

add_action('wp_ajax_kcsync_sync_stories', 'kcsync_sync_stories');