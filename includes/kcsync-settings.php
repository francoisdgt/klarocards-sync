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

    // adding kcsync_api_url setting field
    add_settings_field(
        'kcsync_api_url',
        'Lien API du projet source',
        'kcsync_api_url_callback',
        'kcsync_settings',
        'kcsync_general_section'
    );

    // adding kcsync_board_name setting field
    add_settings_field(
        'kcsync_board_name',
        'Nom du tableau',
        'kcsync_board_name_callback',
        'kcsync_settings',
        'kcsync_general_section'
    );

    // adding kcsync_api_key setting field
    add_settings_field(
        'kcsync_api_key',
        'API KEY',
        'kcsync_api_key_callback',
        'kcsync_settings',
        'kcsync_general_section'
    );

    register_setting('kcsync_settings', 'kcsync_api_url'); // defining kcsync_api_url setting in the general settings group
    register_setting('kcsync_settings', 'kcsync_board_name'); // defining kcsync_board_name setting in the general settings group
    register_setting('kcsync_settings', 'kcsync_api_key'); // defining kcsync_api_key setting in the general settings group
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

function kcsync_api_key_callback()
{
    $setting = get_option('kcsync_api_key'); // getting the kcsync_api_url setting
?>
    <!-- setting the input and a description -->
    <input
        type="text" name="kcsync_api_key"
        value="<?php echo isset($setting) ? esc_attr($setting) : ''; // if $setting not null then value = $setting, if null value = ''
                ?>">
<?php
}

function kcsync_board_name_callback()
{
    $setting = get_option('kcsync_board_name'); // getting the kcsync_api_url setting
?>
    <!-- setting the input and a description -->
    <input
        type="text" name="kcsync_board_name"
        value="<?php echo isset($setting) ? esc_attr($setting) : ''; // if $setting not null then value = $setting, if null value = ''
                ?>">
    <p class="description">Le nom du tableau à partir du quel on doit récupérer les cartes</p>
<?php
}
