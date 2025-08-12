<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin to synchronize Klaro Cards cards with Wordpress.
 * Version: 0.5.1
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// imports
require_once plugin_dir_path(__FILE__) . 'includes/kcsync-api.php';
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

// getting the posts from Klaro Cards and creating/updating/deletings posts
function kcsync_sync_stories() {
    $nonce = 'kcsync_sync_' . get_current_user_id();

    // checking if the user has the correct permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission refusée');
    }

    // checking if the nonce is valid
    if (!check_ajax_referer($nonce)) {
        wp_send_json_error('Nonce invalide');
    }

    $parsedown = new Parsedown();

    // data needed for API call
    $api_token = get_option('kcsync_api_key');
    $kc_url = get_option('kcsync_api_url');
    $api_url = $kc_url . '/api/v1';
    $board_name = get_option('kcsync_board_name');

    // getting stories from board
    $board_url = $api_url . '/boards/' . $board_name . '/stories';
    $board_stories = kcsync_api_call($board_url, $api_token);

    // we check if there is any stories
    if (count($board_stories) === 0) {
        wp_send_json_success('Succès, il n\'y a aucune carte à importer');
    }

    $new_posts = array(); // where we'll store the new posts

    // getting WP posts that are linked to KC
    $posts = get_posts(array(
        'numberposts' => -1, // all posts
        'meta_key' => 'story_id',
        'meta_compare' => 'EXISTS',
        'post_type' => 'post'
    ));

    $posts_count = count($posts); // counting the number of posts

    // foreach stories from the board 
    foreach ($board_stories as $board_story) {
        // while i < posts count and the board story id != the post meta "story_id"
        $i = 0;
        while ($i < $posts_count and $board_story['id'] !== get_post_meta($posts[$i]->ID, 'story_id', true)) {
            $i++;
        }

        // if the story isn't in the posts we create a post
        if ($posts_count === $i) {
            // getting the stories from Klaro Cards
            $story_url = $api_url . '/stories/' . $board_story['id'];
            $story = kcsync_api_call($story_url, $api_token);

            // isolating the first image to use for the WP post
            $post_img = $kc_url . $story['attachments'][0]['url'];
            $post_content = "<img src='$post_img'></img>" . $parsedown->text($story['specification']);
            array_push($new_posts, [
                'post_title' => $story['title'],
                'post_content' => $post_content,
                'post_status' => 'publish',
                'meta_input' => [
                    'story_id' => $board_story['id']
                ]
            ]);
        } else {
            /*
             * The following condition checks whether the WP post's last modified time is earlier than the KC story's last modified time.
             * If true, it means the story has been modified in KC and needs to be synchronized with WP.
             * If false, it means we already have the latest version of the story in WP.
             * 
             * One important thing to note is that the KC API provides time data in the GMT timezone, regardless of the project's timezone.
             * Therefore, we should always check that the timezones are matching when manipulating time data.
             */
            if (strtotime($posts[$i]->post_modified_gmt) < strtotime($board_story['updatedAt'])) {
                $story_url = $api_url . '/stories/' . $board_story['id'];
                $story = kcsync_api_call($story_url, $api_token);
                wp_update_post(array(
                    'ID' => $posts[$i]->ID,
                    'post_title' => $story['title'],
                    'post_content' => $parsedown->text($story['specification'])
                ));
                array_splice($posts, $i, 1); // we delete the corresponding post from the array
                $posts_count = count($posts); // updating the posts' count
            } else {
                // else we don't do anything and just delete the corresponding post from the array
                array_splice($posts, $i, 1);
                $posts_count = count($posts); // updating the posts' count
            }
        }
    }

    // If there are still posts in the array => the corresponding stories have been deleted
    if (count($posts) !== 0) {
        foreach ($posts as $post) {
            // We delete the corresponding posts
            wp_delete_post(
                $post->ID,
                true
            );
        }
    }

    foreach ($new_posts as $post) {
        wp_insert_post($post); // we insert the new posts
    }

    wp_send_json_success('Succès !');
}

add_action('wp_ajax_kcsync_sync_stories', 'kcsync_sync_stories');