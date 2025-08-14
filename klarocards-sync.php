<?php
/*
 * Plugin Name: Klaro Cards Sync
 * Description: Plugin to synchronize Klaro Cards cards with Wordpress.
 * Version: 0.5.4
 * License: GPL v2 or later
 * Author: François Dangotte, Hugo Torres
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
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
    $user_id = get_current_user_id();
    
    // Log du début de la synchronisation
    error_log("Klaro Cards Sync: Début de synchronisation par l'utilisateur {$user_id}");

    // checking if the user has the correct permissions
    if (!current_user_can('edit_posts')) {
        error_log("Klaro Cards Sync: Utilisateur {$user_id} n'a pas la capacité 'edit_posts'");
        wp_send_json_error('Permission refusée');
    }

    // checking if the nonce is valid
    if (!check_ajax_referer($nonce, '_ajax_nonce', false)) {
        error_log("Klaro Cards Sync: Nonce invalide pour l'utilisateur {$user_id}");
        wp_send_json_error('Nonce invalide');
    }

    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);

    // data needed for API call
    $api_token = get_option('kcsync_api_key');
    $kc_url = get_option('kcsync_api_url');
    $api_url = $kc_url . '/api/v1';
    $board_name = get_option('kcsync_board_name');
    
    error_log("Klaro Cards Sync: Configuration - URL: {$kc_url}, Board: {$board_name}");

    // getting stories from board
    $board_url = $api_url . '/boards/' . $board_name . '/stories';
    error_log("Klaro Cards Sync: Tentative d'appel API vers: {$board_url}");
    
    $board_stories = kcsync_api_call($board_url, $api_token);
    if (is_wp_error($board_stories) || !is_array($board_stories)) {
        $error_msg = is_wp_error($board_stories) ? $board_stories->get_error_message() : 'Données invalides';
        error_log("Klaro Cards Sync: Échec récupération board stories: {$error_msg}");
        wp_send_json_error('Erreur lors de l\'appel à l\'API Klaro Cards: ' . $error_msg);
    }

    // we check if there is any stories
    $stories_count = count($board_stories);
    error_log("Klaro Cards Sync: {$stories_count} stories trouvées sur le board");
    
    if ($stories_count === 0) {
        error_log("Klaro Cards Sync: Aucune story à synchroniser");
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
            
            // Vérification de l'erreur API pour la création
            if (is_wp_error($story)) {
                wp_send_json_error('Erreur lors de la récupération de la carte ' . $board_story['id'] . ' : ' . $story->get_error_message());
            }
            if (!is_array($story)) {
                wp_send_json_error('Données invalides reçues pour la carte ' . $board_story['id']);
            }

            // isolating the first image to use for the WP post
            $post_content = '';
            
            // Validation optimisée des attachments
            if (!empty($story['attachments'][0]['url'])) {
                $attachment_url = $story['attachments'][0]['url'];
                $post_img = esc_url($kc_url . $attachment_url);
                $post_content .= '<img src="' . $post_img . '" alt="' . esc_attr(isset($story['title']) ? $story['title'] : 'Image Klaro Cards') . '" />';
            }
            
            // Validation du contenu de la story
            $story_specification = '';
            if (isset($story['specification']) && is_string($story['specification'])) {
                $story_specification = $story['specification'];
            }
            
            $post_content .= $parsedown->text($story_specification);
            $post_content = wp_kses_post($post_content);
            
            error_log("Klaro Cards Sync: Création post pour story {$board_story['id']} - Titre: " . (isset($story['title']) ? $story['title'] : 'Sans titre'));
            
            array_push($new_posts, [
                'post_title' => isset($story['title']) ? $story['title'] : '',
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
                
                // Vérification de l'erreur API pour la mise à jour
                if (is_wp_error($story)) {
                    wp_send_json_error('Erreur lors de la récupération de la carte ' . $board_story['id'] . ' : ' . $story->get_error_message());
                }
                if (!is_array($story)) {
                    wp_send_json_error('Données invalides reçues pour la carte ' . $board_story['id']);
                }
                
                // Validation robuste du contenu pour la mise à jour
                $post_content = '';
                
                // Validation optimisée des attachments
                if (!empty($story['attachments'][0]['url'])) {
                    $attachment_url = $story['attachments'][0]['url'];
                    $post_img = esc_url($kc_url . $attachment_url);
                    $post_content .= '<img src="' . $post_img . '" alt="' . esc_attr(isset($story['title']) ? $story['title'] : 'Image Klaro Cards') . '" />';
                }
                
                // Validation du contenu de la story
                $story_specification = '';
                if (isset($story['specification']) && is_string($story['specification'])) {
                    $story_specification = $story['specification'];
                }
                
                $post_content .= $parsedown->text($story_specification);
                $post_content = wp_kses_post($post_content);
                
                wp_update_post(array(
                    'ID' => $posts[$i]->ID,
                    'post_title' => isset($story['title']) ? $story['title'] : '',
                    'post_content' => $post_content
                ));
                
                error_log("Klaro Cards Sync: Mise à jour post ID {$posts[$i]->ID} pour story {$board_story['id']}");
                
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
        // Ensure the current user can delete posts before doing so
        if (current_user_can('delete_posts')) {
            foreach ($posts as $post) {
                $linked_story_id = get_post_meta($post->ID, 'story_id', true);
                if (empty($linked_story_id) || !isset($board_story_ids[$linked_story_id])) {
                    // Move to trash instead of force deleting
                    $deleted = wp_delete_post(
                        $post->ID,
                        false  // false = move to trash, true = force delete
                    );
                    
                    if ($deleted) {
                        error_log("Klaro Cards Sync: Post ID {$post->ID} moved to trash (story {$linked_story_id} deleted from board)");
                    } else {
                        error_log("Klaro Cards Sync: Failed to move post ID {$post->ID} to trash");
                    }
                }
            }
        } else {
            error_log("Klaro Cards Sync: User lacks 'delete_posts' capability - posts not deleted");
        }
    }

    foreach ($new_posts as $post) {
        wp_insert_post($post); // we insert the new posts
    }
    
    $new_posts_count = count($new_posts);
    error_log("Klaro Cards Sync: Synchronisation terminée - {$new_posts_count} nouveaux posts créés");
    
    wp_send_json_success('Succès ! ' . $new_posts_count . ' article(s) synchronisé(s)');
}

add_action('wp_ajax_kcsync_sync_stories', 'kcsync_sync_stories');