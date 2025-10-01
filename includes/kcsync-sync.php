<?php

/**
 * Klaro Cards story synchronization logic with WordPress
 *
 * This file contains all synchronization logic, including:
 * - Retrieving stories from Klaro Cards API
 * - Creating, updating and deleting WordPress posts
 * - Performance optimization with existing posts index
 * - Error handling and detailed logging
 *
 * @package KlaroCardsSync
 * @since 0.6.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Main story synchronization function
 *
 * AJAX entry point for synchronization. This function:
 * 1. Verifies permissions and security
 * 2. Retrieves configuration and stories
 * 3. Builds optimized index of existing posts
 * 4. Processes each story (create/update)
 * 5. Cleans up orphaned posts
 * 6. Returns detailed summary
 *
 * @return void
 */
function kcsync_sync_stories()
{
  $nonce = 'kcsync_sync_' . get_current_user_id();
  $user_id = get_current_user_id();

  // Log synchronization start
  error_log("Klaro Cards Sync: Début de synchronisation par l'utilisateur {$user_id}");

  // Check user permissions
  if (!current_user_can('edit_posts')) {
    error_log("Klaro Cards Sync: Utilisateur {$user_id} n'a pas la capacité 'edit_posts'");
    wp_send_json_error('Permission refusée');
  }

  // Verify nonce validity for CSRF security
  if (!check_ajax_referer($nonce, '_ajax_nonce', false)) {
    error_log("Klaro Cards Sync: Nonce invalide pour l'utilisateur {$user_id}");
    wp_send_json_error('Nonce invalide');
  }

  // Initialize Parsedown in safe mode for Markdown parsing
  $parsedown = new Parsedown();
  $parsedown->setSafeMode(true);

  // Retrieve and validate configuration
  $config = kcsync_get_sync_config();
  if (is_wp_error($config)) {
    wp_send_json_error($config->get_error_message());
  }

  // Retrieve stories from Klaro Cards board
  $board_stories = kcsync_get_board_stories($config);
  if (is_wp_error($board_stories)) {
    wp_send_json_error($board_stories->get_error_message());
  }

  // Build index of existing posts (PERFORMANCE OPTIMIZATION)
  // This step solves the Plugin Check issue by avoiding O(N×M) loop
  $story_id_to_post = kcsync_build_story_index();

  // Check if there are stories to process or posts to clean up
  if (empty($board_stories) && empty($story_id_to_post)) {
    error_log("Klaro Cards Sync: Aucune story à synchroniser");
    wp_send_json_success('Succès, il n\'y a aucune carte à importer');
  }

  // Process stories with optimized index (O(1) access instead of O(N))
  $sync_results = kcsync_process_stories($board_stories, $story_id_to_post, $config, $parsedown);

  // Clean up orphaned posts (stories deleted from board)
  $cleanup_results = kcsync_cleanup_orphaned_posts($story_id_to_post, $board_stories, $sync_results['board_story_ids']);

  // Create new posts for stories that don't exist yet
  $creation_results = kcsync_create_new_posts($sync_results['new_posts']);

  // Build and send success summary with statistics
  $summary = kcsync_build_sync_summary($sync_results, $cleanup_results, $creation_results);
  wp_send_json_success($summary);
}

/**
 * Retrieves synchronization configuration
 *
 * @return array|WP_Error Configuration or error
 */
function kcsync_get_sync_config()
{
  $api_token = get_option('kcsync_api_key');
  $kc_url = get_option('kcsync_api_url');
  $board_name = get_option('kcsync_board_name');

  if (empty($api_token) || empty($kc_url) || empty($board_name)) {
    return new WP_Error('config_missing', 'Configuration incomplète. Veuillez vérifier vos paramètres.');
  }

  error_log("Klaro Cards Sync: Configuration - URL: {$kc_url}, Board: {$board_name}");

  return array(
    'api_token' => $api_token,
    'kc_url' => $kc_url,
    'api_url' => $kc_url . '/api/v1',
    'board_name' => $board_name
  );
}

/**
 * Retrieves stories from Klaro Cards board
 *
 * @param array $config Configuration
 * @return array|WP_Error Stories or error
 */
function kcsync_get_board_stories($config)
{
  $board_url = $config['api_url'] . '/boards/' . $config['board_name'] . '/stories';
  error_log("Klaro Cards Sync: Tentative d'appel API vers: {$board_url}");

  $board_stories = kcsync_api_call($board_url, $config['api_token']);

  if (is_wp_error($board_stories)) {
    error_log("Klaro Cards Sync: Échec récupération board stories: " . $board_stories->get_error_message());
    return $board_stories;
  }

  if (!is_array($board_stories)) {
    error_log("Klaro Cards Sync: Données invalides reçues du board");
    return new WP_Error('invalid_board_data', 'Données invalides reçues du board');
  }

  $stories_count = count($board_stories);
  error_log("Klaro Cards Sync: {$stories_count} stories trouvées sur le board");

  return $board_stories;
}

/**
 * Builds index of existing posts (PERFORMANCE OPTIMIZATION)
 *
 * @return array Index story_id => WP_Post
 */
function kcsync_build_story_index()
{
  error_log("Klaro Cards Sync: Construction de l'index des posts existants");

  $posts = get_posts(array(
    'numberposts' => -1,
    'meta_key' => 'story_id',
    'meta_compare' => 'EXISTS',
    'post_type' => 'post',
    'post_status' => 'any'
  ));

  $story_id_to_post = array();
  $posts_count = count($posts);

  foreach ($posts as $post) {
    $story_id = get_post_meta($post->ID, 'story_id', true);
    if (!empty($story_id)) {
      $story_id_to_post[$story_id] = $post;
    }
  }

  error_log("Klaro Cards Sync: Index construit avec {$posts_count} posts, " . count($story_id_to_post) . " liés à des stories");

  return $story_id_to_post;
}

/**
 * Processes stories with optimized index
 *
 * @param array $board_stories Board stories
 * @param array $story_id_to_post Index of existing posts
 * @param array $config Configuration
 * @param Parsedown $parsedown Parsedown instance
 * @return array Processing results
 */
function kcsync_process_stories($board_stories, $story_id_to_post, $config, $parsedown)
{
  $new_posts = array();
  $updated_posts = array();
  $board_story_ids = array();

  error_log("Klaro Cards Sync: Traitement de " . count($board_stories) . " stories");

  foreach ($board_stories as $board_story) {
    if (!is_array($board_story) || empty($board_story['id'])) {
      continue;
    }

    $story_id = $board_story['id'];
    $board_story_ids[$story_id] = true;

    // Check if story already exists in WordPress (O(1) access thanks to index)
    if (isset($story_id_to_post[$story_id])) {
      // Story exists → check if update needed
      $update_result = kcsync_update_existing_post($board_story, $story_id_to_post[$story_id], $config, $parsedown);
      if ($update_result) {
        $updated_posts[] = $update_result;
      }
      unset($story_id_to_post[$story_id]);
    } else {
      // Story doesn't exist → prepare creation
      $new_post_data = kcsync_prepare_new_post($board_story, $config, $parsedown);
      if ($new_post_data) {
        $new_posts[] = $new_post_data;
      }
    }
  }

  error_log("Klaro Cards Sync: Traitement terminé - " . count($updated_posts) . " mises à jour, " . count($new_posts) . " nouvelles");

  return array(
    'new_posts' => $new_posts,
    'updated_posts' => $updated_posts,
    'board_story_ids' => $board_story_ids
  );
}

/**
 * Updates existing post if needed
 *
 * @param array $board_story Board story
 * @param WP_Post $wp_post WordPress post
 * @param array $config Configuration
 * @param Parsedown $parsedown Parsedown instance
 * @return array|false Update data or false
 */
function kcsync_update_existing_post($board_story, $wp_post, $config, $parsedown)
{
  // Retrieve complete story details
  $story_url = $config['api_url'] . '/stories/' . $board_story['id'];
  $story = kcsync_api_call($story_url, $config['api_token']);

  if (is_wp_error($story) || !is_array($story)) {
    error_log("Klaro Cards Sync: Erreur récupération story {$board_story['id']}");
    return false;
  }

  // Getting the attachment needed for the update check
  $story_attachment = kcsync_get_attachment($story, $config['kc_url']);

  // Check if update is needed
  $update_needed = kcsync_check_post_update($story, $wp_post, $story_attachment);
  if (!$update_needed) {
    return false;
  }

  // Handle attachments test
  $attachment_id = kcsync_handle_attachment($story, $config['kc_url'], $story_attachment);
  error_log("Klaro Cards Sync: Attachment ID: $attachment_id");

  // Build updated content
  $post_content = kcsync_build_post_content($story, $attachment_id, $parsedown);

  // Get WordPress category using slug from Klaro Cards story
  $post_category = kcsync_get_category_by_card_kind($story['card-kind']);

  error_log("Klaro Cards Sync: Category {$post_category->name} ({$post_category->slug})");

  // Update post
  $update_result = wp_update_post(array(
    'ID' => $wp_post->ID,
    'post_title' => isset($story['title']) ? $story['title'] : '',
    'post_content' => $post_content,
    'post_category' => array($post_category->term_id)
  ));

  if (is_wp_error($update_result)) {
    error_log("Klaro Cards Sync: Erreur mise à jour post {$wp_post->ID}: " . $update_result->get_error_message());
    return false;
  }

  kcsync_update_post_thumbnail($attachment_id, $wp_post->ID);

  error_log("Klaro Cards Sync: Post {$wp_post->ID} mis à jour pour story {$board_story['id']}");

  return array(
    'post_id' => $wp_post->ID,
    'story_id' => $board_story['id'],
    'action' => 'updated'
  );
}

/**
 * Prepares data for new post
 *
 * @param array $board_story Board story
 * @param array $config Configuration
 * @param Parsedown $parsedown Parsedown instance
 * @return array|false Post data or false
 */
function kcsync_prepare_new_post($board_story, $config, $parsedown)
{
  // Retrieve complete story details
  $story_url = $config['api_url'] . '/stories/' . $board_story['id'];
  $story = kcsync_api_call($story_url, $config['api_token']);

  if (is_wp_error($story) || !is_array($story)) {
    error_log("Klaro Cards Sync: Erreur récupération story {$board_story['id']}");
    return false;
  }

  $story_attachment = kcsync_get_attachment($story, $config['kc_url']);

  $attachment_id = kcsync_handle_attachment($story, $config['kc_url'], $story_attachment);

  // Build post content
  $post_content = kcsync_build_post_content($story, $attachment_id, $parsedown);

  // Get WordPress category using slug from Klaro Cards story
  $post_category = kcsync_get_category_by_card_kind($story['card-kind']);

  $new_post_data = array(
    'post_title' => isset($story['title']) ? $story['title'] : '',
    'post_content' => $post_content,
    'post_status' => 'publish',
    'post_category' => array($post_category->term_id),
    'meta_input' => array(
      'story_id' => $board_story['id']
    )
  );

  error_log("Klaro Cards Sync: Nouveau post préparé pour story {$board_story['id']} - Titre: " . (isset($story['title']) ? $story['title'] : 'Sans titre'));

  return array(
    'post_data' => $new_post_data,
    'attachment_id' => $attachment_id
  );
}

/**
 * Builds post content from story
 *
 * @param array $story Klaro Cards story
 * @param array $config Configuration
 * @param Parsedown $parsedown Parsedown instance
 * @return string Post content
 */
function kcsync_build_post_content($story, $attachment_id, $parsedown)
{
  $post_content = '';

  // Add story content only (images are handled as featured images)
  if (isset($story['specification']) && is_string($story['specification'])) {
    $post_content .= $parsedown->text($story['specification']);
  }

  // Filter HTML content for security
  return wp_kses_post($post_content);
}

/**
 * Cleans up orphaned posts (deleted stories)
 *
 * @param array $story_id_to_post Index of existing posts
 * @param array $board_stories Stories currently on board
 * @return array Cleanup results
 */
function kcsync_cleanup_orphaned_posts($story_id_to_post, $board_stories, $board_story_ids)
{
  $deleted_posts = array();

  // Identify and delete orphaned posts
  foreach ($story_id_to_post as $story_id => $post) {
    if (!isset($board_story_ids[$story_id])) {
      // Story deleted from board → delete post
      if (current_user_can('delete_posts')) {
        $thumbnail_id = get_post_thumbnail_id($post);
        $deleted = wp_delete_post($post->ID, true); // false = trash, true = force delete
        kcsync_remove_attachment_from_media($thumbnail_id);

        if ($deleted) {
          $deleted_posts[] = array(
            'post_id' => $post->ID,
            'story_id' => $story_id,
            'action' => 'deleted'
          );
          error_log("Klaro Cards Sync: Post {$post->ID} supprimé (story {$story_id} supprimée du board)");
        }
      } else {
        error_log("Klaro Cards Sync: Impossible de supprimer le post {$post->ID} - permissions insuffisantes");
      }
    }
  }

  error_log("Klaro Cards Sync: Nettoyage terminé - " . count($deleted_posts) . " posts supprimés");

  return array(
    'deleted_posts' => $deleted_posts
  );
}

/**
 * Creates new posts
 *
 * @param array $new_posts New post data
 * @return array Creation results
 */
function kcsync_create_new_posts($new_posts)
{
  $created_posts = array();

  foreach ($new_posts as $post_data) {
    $post_id = wp_insert_post($post_data['post_data']);

    if (is_wp_error($post_id)) {
      error_log("Klaro Cards Sync: Erreur création post: " . $post_id->get_error_message());
      continue;
    }

    $update_thumbnail_result = kcsync_update_post_thumbnail($post_data['attachment_id'], $post_id);

    $created_posts[] = array(
      'post_id' => $post_id,
      'story_id' => $post_data['post_data']['meta_input']['story_id'],
      'update_thumbnail_result' => $update_thumbnail_result,
      'action' => 'created'
    );

    error_log("Klaro Cards Sync: Nouveau post {$post_id} créé pour story {$post_data['post_data']['meta_input']['story_id']}");
  }

  error_log("Klaro Cards Sync: Création terminée - " . count($created_posts) . " nouveaux posts créés");

  return array(
    'created_posts' => $created_posts
  );
}

/**
 * Builds synchronization summary
 *
 * @param array $sync_results Processing results
 * @param array $cleanup_results Cleanup results
 * @param array $creation_results Creation results
 * @return string Summary message
 */
function kcsync_build_sync_summary($sync_results, $cleanup_results, $creation_results)
{
  $stories_processed = count($sync_results['board_story_ids']);
  $posts_updated = count($sync_results['updated_posts']);
  $posts_created = count($creation_results['created_posts']);
  $posts_deleted = count($cleanup_results['deleted_posts']);

  $summary = sprintf(
    'Synchronisation terminée avec succès ! %d stories traitées, %d posts mis à jour, %d créés, %d supprimés.',
    $stories_processed,
    $posts_updated,
    $posts_created,
    $posts_deleted
  );

  error_log("Klaro Cards Sync: " . $summary);

  return $summary;
}

function kcsync_get_category_by_card_kind($card_kind)
{
  if (empty($card_kind)) {
    error_log("Klaro Cards Sync: card-kind empty");
    return kcsync_get_default_category();
  }

  $category = get_category_by_slug($card_kind);

  if (!$category) {
    error_log("Klaro Cards Sync: category not found");
    return kcsync_get_default_category();
  }

  $allowed_categories = array(
    'Formation',
    'Blog',
    'Projet',
    'Création',
    'Service'
  );
  if (!in_array($category->name, $allowed_categories)) {
    error_log("Klaro Cards Sync: category not allowed");
    return kcsync_get_default_category();
  }

  error_log("Klaro Cards Sync: category found");
  return $category;
}

function kcsync_get_default_category()
{
  $default_category = get_category_by_slug('non-classe');

  if (!$default_category) {
    $default_category = wp_create_category('non-classe');
    if (is_wp_error($default_category)) {
      error_log("Klaro Cards Sync: default category not created");
      return null;
    }
  }

  return $default_category;
}

/**
 * Checks if the post needs to be updated
 *
 * @param array $story Klaro Cards story
 * @param WP_Post $wp_post WordPress post
 * @param array $attachment Attachment
 * @return bool True if the post needs to be updated, false otherwise
 */
function kcsync_check_post_update($story, $wp_post, $attachment)
{
  // If the story has been updated, the post needs to be updated
  if (strtotime($story['updatedAt']) > strtotime($wp_post->post_modified_gmt)) {
    return true;
  }

  // If the attachment has been removed, the post needs to be updated
  if (empty($attachment) && has_post_thumbnail($wp_post->ID)) {
    return true;
  }

  // If a new attachment has been added, the post needs to be updated
  if (!empty($attachment) && !has_post_thumbnail($wp_post->ID)) {
    return true;
  }

  // If no attachment, no update needed
  if (empty($attachment)) {
    return false;
  }

  // If the attachment has been updated, the post needs to be updated
  if (strtotime($attachment['createdAt']) > strtotime($wp_post->post_modified_gmt)) {
    return true;
  }

  return false;
}
