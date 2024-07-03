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
function forvoyez_add_menu_item() {
    add_options_page(
        'Auto Alt Text Settings',  // Page title
        'Auto Alt Text',           // Menu title
        'manage_options',          // Capability required to see this option
        'forvoyez-auto-alt-text',  // Menu slug
        'forvoyez_settings_page'   // Function to output the content for this page
    );
}
add_action('admin_menu', 'forvoyez_add_menu_item');

// Create the settings page
function forvoyez_settings_page() {
    ?>
    <div class="wrap">
        <h1>Welcome to Auto Alt Text for Images</h1>
        <p>This plugin will help you automatically generate alt text for your images using the ForVoyez API.</p>
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
            foreach ($query_images->posts as $image) :
                $image_url = wp_get_attachment_url($image->ID);
                $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
                ?>
                <tr>
                    <td><img src="<?php echo esc_url($image_url); ?>" style="max-width: 100px; height: auto;"></td>
                    <td><?php echo esc_html($image->post_title); ?></td>
                    <td><?php echo esc_html($image_alt); ?></td>
                    <td><?php echo esc_html($image->post_excerpt); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}