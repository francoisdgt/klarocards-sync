<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin to synchronize Klaro Cards cards with Wordpress.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';

function klarocard_sync_page()
{
    add_menu_page(
        'Klaro Cards Sync',       // Titre de la page
        'Klaro Cards Sync',       // Texte à afficher dans le menu
        'manage_options',          // Capacité requise pour accéder à la page
        'klarocard-sync',          // Slug de la page
        'klarocard_sync_page_content', // Fonction pour afficher le contenu de la page
        'dashicons-admin-generic', // Icône du menu
        6                          // Position dans le menu
    );
}
add_action('admin_menu', 'klarocard_sync_page');
