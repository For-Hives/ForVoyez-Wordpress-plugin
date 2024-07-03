<?php
/**
 * Plugin Name: Auto Alt Text for Images
 * Description: Automatically generate alt text and SEO metadata for images using ForVoyez API.
 * Version: 1.0.0
 * Author: ForVoyez
 * Author URI: https://forvoyez.com
 * Text Domain: forvoyez-auto-alt-text-for-images
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Add menu item to the WordPress admin
function forvoyez_add_menu_item()
{
    add_options_page(
        'Auto Alt Text Settings',  // Page title
        'Auto Alt Text',           // Menu title
        'manage_options',          // Capability required to see this option
        'forvoyez-auto-alt-text',  // Menu slug
        'forvoyez_display_incomplete_images'  // Function to output the content for this page
    );
}

add_action('admin_menu', 'forvoyez_add_menu_item');

// Function to display images with incomplete metadata
function forvoyez_display_incomplete_images() {
    ?>
    <div class="wrap">
        <h1>Images Needing SEO Metadata</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Alt Text</th>
                <th>Caption</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $args = array(
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
            );
            $query_images = new WP_Query($args);
            $incomplete_count = 0;
            foreach ($query_images->posts as $image) :
                $image_url = wp_get_attachment_url($image->ID);
                $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);

                // Check if any of title, alt text, or caption is missing
                if (empty($image->post_title) || empty($image_alt) || empty($image->post_excerpt)) :
                    $incomplete_count++;
                    ?>
                    <tr>
                        <td><img src="<?php echo esc_url($image_url); ?>" style="max-width: 100px; height: auto;"></td>
                        <td><?php echo empty($image->post_title) ? '<span style="color: red;">Missing</span>' : esc_html($image->post_title); ?></td>
                        <td><?php echo empty($image_alt) ? '<span style="color: red;">Missing</span>' : esc_html($image_alt); ?></td>
                        <td><?php echo empty($image->post_excerpt) ? '<span style="color: red;">Missing</span>' : esc_html($image->post_excerpt); ?></td>
                    </tr>
                <?php
                endif;
            endforeach;

            if ($incomplete_count === 0) :
                ?>
                <tr>
                    <td colspan="4">All images have complete metadata. Great job!</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p>Total images needing attention: <?php echo $incomplete_count; ?></p>
    </div>
    <?php
}