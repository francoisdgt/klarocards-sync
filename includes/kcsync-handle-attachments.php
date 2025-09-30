<?php

/**
 * Handles attachments for Klaro Cards posts
 *
 * @package KlaroCardsSync
 * @since 0.7.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Generates a hash for an attachment from its filename and size in bytes
 *
 * @param array $attachment Attachment
 * @return string Attachment hash
 */
function kcsync_generate_attachment_hash($attachment)
{
  error_log("Klaro Cards Sync: kcsync_generate_attachment_hash");
  return md5($attachment['filename'] . $attachment['sizeInBytes']);
}

/**
 * Checks for a duplicate attachment in the media library
 *
 * @param array $attachment Attachment
 * @return int|false Attachment ID or false if no duplicate found
 */
function kcsync_check_for_duplicate_attachment($attachment)
{
  // Generating the hash
  $hash = kcsync_generate_attachment_hash($attachment);

  // Checking for duplicates in the media library
  $duplicates = get_posts(array(
    'post_type' => 'attachment',
    'meta_key' => 'kcsync_attachment_hash',
    'meta_value' => $hash
  ));

  if (!empty($duplicates)) {
    return $duplicates[0]->ID;
  }

  return false;
}

/**
 * Gets the first attachment that is an image
 *
 * @param array $story Klaro Cards story
 * @param string $kc_url Klaro Cards URL
 * @param int $index Index of the attachment
 * @return array|null Attachment
 * @since 0.7.0
 */
function kcsync_get_attachment($story, $kc_url, $index = 0)
{
  if (empty($story['attachments']) || $index >= count($story['attachments'])) {
    return null;
  }

  $attachment = $story['attachments'][$index];
  $attachment_url = sanitize_url($kc_url . $attachment['url']);
  $mimes = array(
    'jpg|jpeg|jpe' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
  );

  if (wp_check_filetype($attachment_url, $mimes)['ext']) {
    return $attachment;
  }

  return kcsync_get_attachment($story, $kc_url, $index + 1);
}

/**
 * Handles the attachment
 *
 * @param array $story Klaro Cards story
 * @param string $kc_url Klaro Cards URL
 * @return int|null $attachment_id Attachment ID or null if no attachment found
 * @since 0.7.0
 */
function kcsync_handle_attachment($story, $kc_url, $attachment)
{
  if (empty($attachment)) {
    error_log("Klaro Cards Sync: No attachment found");
    return null;
  }

  // Checking for duplicates
  $attachment_id = kcsync_check_for_duplicate_attachment($attachment);
  if ($attachment_id) {
    error_log("Klaro Cards Sync: Duplicate attachment found, ID: $attachment_id");
    return $attachment_id; // Returning the attachment ID if a duplicate is found
  }

  // Uploading the attachment to the media library (without associating to a post)
  $attachment_url = sanitize_url($kc_url . $attachment['url']);
  $attachment_id = media_sideload_image($attachment_url, 0, $attachment['filename'], 'id');

  if (is_wp_error($attachment_id)) {
    error_log("Klaro Cards Sync: Erreur upload attachment: " . $attachment_id->get_error_message());
    return null;
  }

  // Store the hash in post meta for future duplicate detection
  $hash = kcsync_generate_attachment_hash($attachment);
  update_post_meta($attachment_id, 'kcsync_attachment_hash', $hash);

  error_log("Klaro Cards Sync: Attachment uploaded successfully, ID: $attachment_id");
  return $attachment_id;
}

/**
 * Associates an attachment with a WordPress post
 *
 * @param int $attachment_id Attachment ID
 * @param int $post_id WordPress post ID
 * @return bool Success status
 * @since 0.7.0
 */
function kcsync_associate_attachment_with_post($attachment_id, $post_id)
{
  if (empty($attachment_id) || empty($post_id)) {
    return false;
  }

  // Update the attachment's post_parent to associate it with the post
  $result = wp_update_post(array(
    'ID' => $attachment_id,
    'post_parent' => $post_id
  ));

  if (is_wp_error($result)) {
    error_log("Klaro Cards Sync: Erreur association attachment $attachment_id avec post $post_id: " . $result->get_error_message());
    return false;
  }

  // Set as featured image (thumbnail)
  set_post_thumbnail($post_id, $attachment_id);

  error_log("Klaro Cards Sync: Attachment $attachment_id associé au post $post_id et défini comme image à la une");
  return true;
}

/**
 * Updates the post thumbnail
 *
 * @param int $attachment_id Attachment ID
 * @param int $post_id WordPress post ID
 * @return bool Success status
 * @since 0.7.0
 */
function kcsync_update_post_thumbnail($attachment_id, $post_id)
{
  error_log("Klaro Cards Sync: updating post thumbnail");
  if (is_null($post_id)) {
    return false;
  }

  // Deleting post thumbnail if no attachment
  if (is_null($attachment_id)) {
    error_log("Klaro Cards Sync: no attachment so we remove thumbnail");
    delete_post_thumbnail($post_id);
    return true;
  }

  error_log("Klaro Cards Sync: attachment found");

  // Set as featured image (thumbnail)
  $post_meta_id = set_post_thumbnail($post_id, $attachment_id);

  error_log("Klaro Cards Sync: Image à la une mise à jour avec succès, ID: $post_meta_id");
  return true;
}
