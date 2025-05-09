<?php

if (!defined('ABSPATH')) exit;

// Admin page HTML
function kcsync_options_page_html()
{
    if (!current_user_can('manage_options')) return;

?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()) ?></h1>
    </div>
<?php
}

// Adding the admin page to the Tools menu in WP admin
add_action('admin_menu', 'kcsync_options_page');

function kcsync_options_page()
{
    add_management_page(
        'Klaro Cards Sync Options',
        'KC Sync Options',
        'manage_options',
        'kcsync',
        'kcsync_options_page_html',
        3
    );
}
