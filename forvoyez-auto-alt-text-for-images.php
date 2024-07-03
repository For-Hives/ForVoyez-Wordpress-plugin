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
        'forvoyez_settings_page',   // Function to output the content for this page
    );
}
add_action('admin_menu', 'forvoyez_add_menu_item');

// Create the settings page
function forvoyez_settings_page() {
    ?>
    <div class="wrap">
        <h1>Welcome to Auto Alt Text for Images</h1>
        <p>This plugin will help you automatically generate alt text for your images using the ForVoyez API.</p>
<!--        call the forvoyez display incomplete images function-->
        <?php forvoyez_display_incomplete_images(); ?>
    </div>
    <?php
}

// Function to display images with incomplete metadata
function forvoyez_display_incomplete_images() {
    ?>
    <div class="wrap">
        <h2>Images Needing SEO Metadata</h2>

        <div class="forvoyez-legend">
            <span><span class="dashicons dashicons-editor-textcolor"></span> Alt Text</span>
            <span><span class="dashicons dashicons-heading"></span> Title</span>
            <span><span class="dashicons dashicons-editor-quote"></span> Caption</span>
        </div>

        <div class="forvoyez-image-grid">
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
                    <div class="forvoyez-image-item">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
                        <div class="forvoyez-metadata-icons">
                            <?php if (empty($image_alt)) : ?>
                                <span class="dashicons dashicons-editor-textcolor" title="Missing Alt Text"></span>
                            <?php endif; ?>
                            <?php if (empty($image->post_title)) : ?>
                                <span class="dashicons dashicons-heading" title="Missing Title"></span>
                            <?php endif; ?>
                            <?php if (empty($image->post_excerpt)) : ?>
                                <span class="dashicons dashicons-editor-quote" title="Missing Caption"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php
                endif;
            endforeach;

            if ($incomplete_count === 0) :
                ?>
                <p>All images have complete metadata. Great job!</p>
            <?php endif; ?>
        </div>
        <p>Total images needing attention: <?php echo $incomplete_count; ?></p>
    </div>

    <style>
        .forvoyez-legend {
            margin-bottom: 20px;
        }
        .forvoyez-legend span {
            margin-right: 20px;
        }
        .forvoyez-image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .forvoyez-image-item {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
        }
        .forvoyez-image-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .forvoyez-metadata-icons {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(255,255,255,0.8);
            border-radius: 5px;
            padding: 5px;
        }
        .forvoyez-metadata-icons .dashicons {
            color: red;
            margin-right: 5px;
        }
    </style>
    <?php
}