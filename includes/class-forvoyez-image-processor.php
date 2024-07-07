<?php

class Forvoyez_Image_Processor
{
    public function init()
    {
        add_action('wp_ajax_forvoyez_analyze_image', array($this, 'analyze_image'));
        add_action('wp_ajax_forvoyez_update_image_metadata', array($this, 'update_image_metadata'));
        add_action('wp_ajax_forvoyez_load_more_images', array($this, 'load_more_images'));
    }

    public function analyze_image()
    {
        // Implement image analysis logic here
    }

    public function update_image_metadata()
    {
        // Implement metadata update logic here
    }

    public function load_more_images()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 21;

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'offset' => $offset,
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
        $html = '';
        $count = 0;

        ob_start();
        foreach ($query_images->posts as $image) {
            if (empty($image->post_title) || empty(get_post_meta($image->ID, '_wp_attachment_image_alt', true)) || empty($image->post_excerpt)) {
                Forvoyez_Admin::render_image_item($image);
                $count++;
            }
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'count' => $count,
            'total' => forvoyez_count_incomplete_images()
        ));
    }
}