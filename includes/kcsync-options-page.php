<?php

if (!defined('ABSPATH')) exit;

// Admin page HTML
function kcsync_options_page_html()
{
    if (!current_user_can('manage_options')) return;

?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()) ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('kcsync_settings'); // output the fields in kcsync_settings group
            do_settings_sections('kcsync_settings'); // output the sections in kcsync_settings group
            submit_button(); // create a submit button
            ?>
        </form>
    </div>
<?php
}

// Adding the admin page to the Tools menu in WP admin
add_action('admin_menu', 'kcsync_options_page');

function kcsync_options_page()
{
    // adding a submenu to the option page
    add_management_page(
        'Klaro Cards Sync Options',
        'KC Sync Options',
        'manage_options',
        'kcsync_settings',
        'kcsync_options_page_html',
        3 // position 3 in the option menu
    );
}
