<?php
defined('ABSPATH') || exit;

function forvoyez_count_incomplete_images()
{
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_wp_attachment_image_alt',
                'value' => '',
                'compare' => '='
            ),
            array(
                'key' => '_wp_attachment_image_alt',
                'compare' => 'NOT EXISTS'
            )
        )
    );

    $query_images = new WP_Query($args);
    $incomplete_count = 0;

    foreach ($query_images->posts as $image) {
        if (empty($image->post_title) || empty(get_post_meta($image->ID, '_wp_attachment_image_alt', true)) || empty($image->post_excerpt)) {
            $incomplete_count++;
        }
    }

    return $incomplete_count;
}

function forvoyez_get_api_key() {
    $settings = new Forvoyez_Settings();
    return $settings->get_api_key();
}