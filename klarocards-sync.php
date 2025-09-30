<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin pour synchroniser les cartes Klaro Cards avec WordPress.
 * Version: 0.7.2
 * License: GPL v2 or later
 * Author: François Dangotte, Hugo Torres
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package KlaroCardsSync
 * @since 0.6.0
 */

if (!defined('ABSPATH')) exit;

// Composer autoload for dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Include plugin components
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-options-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-handle-attachments.php';

/**
 * Activation hook
 */
function kcsync_activate()
{
  // Create categories if they don't exist
  $categories = [
    'Formation',
    'Blog',
    'Projet',
    'Création',
    'Service'
  ];
  wp_create_categories($categories);
}

/**
 * Enqueue admin scripts for post management pages
 *
 * @param string $hook Current page hook
 */
function kcsync_enqueue_admin_scripts($hook)
{
  // Check if we're on a post management page
  $screen = get_current_screen();
  if ($screen && $screen->post_type !== 'post') return;

  // Enqueue synchronization script
  wp_enqueue_script(
    'kcsync_sync_stories',
    plugins_url('/assets/js/kcsync-sync-stories.js', __FILE__),
    array('jquery'), // Dependencies
    '0.7.2', // Script version
    true // Load in footer
  );

  // Localize script with AJAX data
  wp_localize_script('kcsync_sync_stories', 'kcsync_ajax_data', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('kcsync_sync_' . get_current_user_id()) // Security nonce
  ));
}

// Activation hook
register_activation_hook(__FILE__, 'kcsync_activate');

// Hook for enqueuing admin scripts
add_action('admin_enqueue_scripts', 'kcsync_enqueue_admin_scripts');

// AJAX hook for story synchronization
add_action('wp_ajax_kcsync_sync_stories', 'kcsync_sync_stories');
