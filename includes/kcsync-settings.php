<?php

if (!defined('ABSPATH')) exit;

// registering settings
function kcsync_settings_init()
{
    // adding the general section
    add_settings_section(
        'kcsync_general_section',
        'Général',
        'kcsync_general_section_callback',
        'kcsync_settings'
    );

    // adding kcsync_api_url settings field
    add_settings_field(
        'kcsync_api_url',
        'Lien API du projet source',
        'kcsync_api_url_callback',
        'kcsync_settings',
        'kcsync_general_section'
    );

    register_setting('kcsync_settings', 'kcsync_api_url'); // defining kcsync_api_url setting in the general settings group
}

add_action('admin_init', 'kcsync_settings_init');

// Callback functions
function kcsync_general_section_callback()
{
    echo '<p>Paramètres généraux du plugin Klaro Cards Sync</p>'; // adding the section descrpition
}

function kcsync_api_url_callback()
{
    $setting = get_option('kcsync_api_url'); // getting the kcsync_api_url setting
?>
    <!-- setting the input and a description -->
    <input
        type="text" name="kcsync_api_url"
        value="<?php echo isset($setting) ? esc_attr($setting) : ''; // if $setting not null then value = $setting, if null value = ''
                ?>">
    <p class="description">L'URL doit être au format suivant : https://yourproject.klaro.cards/api/v1/</p>
<?php
}
