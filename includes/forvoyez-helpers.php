<?php
/**
 * Helper functions for the ForVoyez plugin.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit('Direct access to this file is not allowed.');
}

/**
 * Count the number of images with incomplete metadata.
 *
 * This function counts images that are missing either a title,
 * alt text, or caption.
 *
 * @since 1.0.0
 * @return int The number of images with incomplete metadata.
 */
function forvoyez_count_incomplete_images() {
    $args = [
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
    ];

    $query_images = new WP_Query($args);
    $incomplete_count = 0;

    foreach ($query_images->posts as $image) {
        $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $title = $image->post_title;
        $caption = $image->post_excerpt;

        $is_incomplete = false;

        // Check if title is empty or equals the filename
        if (empty($title) || $title === pathinfo($image->guid, PATHINFO_FILENAME)) {
            $is_incomplete = true;
            error_log("Image {$image->ID} has no proper title");
        }

        // Check if alt text is empty
        if (empty($alt_text)) {
            $is_incomplete = true;
            error_log("Image {$image->ID} has no alt text");
        }

        // Check if caption is empty
        if (empty($caption)) {
            $is_incomplete = true;
            error_log("Image {$image->ID} has no caption");
        }

        if ($is_incomplete) {
            $incomplete_count++;
            error_log("Incomplete image found: ID {$image->ID}, Title: {$title}, Alt: {$alt_text}, Caption: {$caption}");
        }
    }

    error_log("Total incomplete images found: {$incomplete_count}");
    return $incomplete_count;
}

/**
 * Get the ForVoyez API key.
 *
 * This function retrieves the API key from the plugin settings.
 *
 * @since 1.0.0
 * @return string The ForVoyez API key.
 */
function forvoyez_get_api_key() {
    global $forvoyez_settings;
    if (!$forvoyez_settings) {
        $forvoyez_settings = new Forvoyez_Settings();
    }
    return $forvoyez_settings->get_api_key();
}