<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin to synchronize Klaro Cards cards with Wordpress.
 * Version: 0.4.1
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// imports
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-options-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-settings.php';
require_once __DIR__ . '/vendor/autoload.php';

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

    // passing a nonce to the script for security
    wp_localize_script('kcsync_sync_stories', 'kcsync_ajax_data', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kcsync_sync_' . get_current_user_id())
    ));
}

add_action('admin_enqueue_scripts', 'kcsync_enqueue_admin_scripts');

function kcsync_sync_stories() {
    // TODO
}