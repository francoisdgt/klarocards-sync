<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin to synchronize Klaro Cards cards with Wordpress.
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/options-page.php';

// Adding plugin options to the database
register_activation_hook(__FILE__, 'kcsync_add_options');

function kcsync_add_options()
{
    add_option('kcsync_api_url', '');
}

// Removing plugin options to the wp_options database
register_deactivation_hook(__FILE__, 'kcsync_remove_options');

function kcsync_remove_options()
{
    delete_option('kcsync_api_url');
}

// Adding a custom button on the post admin page
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
