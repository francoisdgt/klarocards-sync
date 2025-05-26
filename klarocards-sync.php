<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin to synchronize Klaro Cards cards with Wordpress.
 * Version: 0.4.0
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// imports
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-options-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-settings.php';

register_deactivation_hook(__FILE__, 'kcsync_deactivate');

// adding a custom button on the post admin page
add_action('admin_head-edit.php', 'add_retrieve_button');

function add_retrieve_button()
{
    // checking if the script is called on the post admin page
    $screen = get_current_screen();
    if ($screen->post_type !== 'post') return;

    // script to place the "Récupérer les cartes" button next to the "Add Post" button using jQuery
?>
    <script type="text/javascript">
        jQuery(function() {
            // we're using the fact that the header of the page ends with a hr element with class "wp-header-end"
            jQuery('hr.wp-header-end').before('<a class="button-primary" style="transform: translateY(-3px); margin-inline-start: 4px;">Récupérer les cartes</a>');
        });
    </script>
<?php
}
