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
    $page_hook_suffix = add_options_page(
        'Auto Alt Text Settings',  // Page title
        'Auto Alt Text',           // Menu title
        'manage_options',          // Capability required to see this option
        'forvoyez-auto-alt-text',  // Menu slug
        'forvoyez_settings_page'   // Function to output the content for this page
    );
    add_action('admin_print_styles-' . $page_hook_suffix, 'forvoyez_enqueue_admin_styles');
    add_action('admin_print_scripts-' . $page_hook_suffix, 'forvoyez_enqueue_admin_scripts');
}

add_action('admin_menu', 'forvoyez_add_menu_item');

// Enqueue admin styles
function forvoyez_enqueue_admin_styles()
{
    wp_enqueue_style('forvoyez-admin-styles', plugins_url('assets/css/admin-style.css', __FILE__), array(), '1.0.0');
}

// Enqueue admin scripts
function forvoyez_enqueue_admin_scripts()
{
    wp_enqueue_script('forvoyez-admin-scripts', plugins_url('assets/js/admin-script.js', __FILE__), array('jquery'), '1.0.0', true);
    wp_localize_script('forvoyez-admin-scripts', 'forvoyezAjax', array('ajaxurl' => admin_url('admin-ajax.php')));

    wp_localize_script('forvoyez-admin-scripts', 'forvoyezData', array(
        'nonce' => wp_create_nonce('forvoyez_nonce')
    ));
}

// Create the settings page
function forvoyez_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Welcome to Auto Alt Text for Images</h1>
        <p>This plugin will help you automatically generate alt text for your images using the ForVoyez API.</p>
        <?php forvoyez_display_incomplete_images(); ?>
    </div>
    <?php
}

// Function to display images with incomplete metadata
function forvoyez_display_incomplete_images()
{
    $paged = isset($_GET['paged']) ? abs((int)$_GET['paged']) : 1;
    $per_page = 21; // Changé à 21 pour une grille de 3x7

    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => $per_page,
        'paged' => $paged,
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
    $total_images = $query_images->found_posts;
    $total_pages = ceil($total_images / $per_page);

    ?>
    <div class="wrap">
        <h2>Images Needing SEO Metadata</h2>
        <div class="forvoyez-global-controls">
            <button id="forvoyez-toggle-menu" class="button">Show All Details</button>
        </div>

        <div class="forvoyez-legend">
            <span><span class="dashicons dashicons-editor-textcolor"></span> Alt Text</span>
            <span><span class="dashicons dashicons-heading"></span> Title</span>
            <span><span class="dashicons dashicons-editor-quote"></span> Caption</span>
        </div>

        <div class="forvoyez-image-grid" data-offset="<?php echo ($paged - 1) * $per_page; ?>" data-limit="<?php echo $per_page; ?>">
            <?php
            $incomplete_count = 0;
            foreach ($query_images->posts as $image) :
                if (empty($image->post_title) || empty(get_post_meta($image->ID, '_wp_attachment_image_alt', true)) || empty($image->post_excerpt)) :
                    $incomplete_count++;
                    forvoyez_render_image_item($image);
                endif;
            endforeach;
            ?>
        </div>
        <div class="forvoyez-pagination">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $paged
            ));
            ?>
        </div>
        <p>Total images needing attention: <span class="forvoyez-image-count"><?php echo $incomplete_count; ?></span></p>
    </div>
    <?php
}

function forvoyez_render_image_item($image)
{
    $image_url = wp_get_attachment_url($image->ID);
    $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
    ?>
    <div class="forvoyez-image-item" data-image-id="<?php echo esc_attr($image->ID); ?>">
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
        <div class="forvoyez-see-more">
            <span class="dashicons dashicons-visibility"></span>
            <span class="see-more-text">See More</span>
            <span class="hide-details-text" style="display: none;">Hide Details</span>
        </div>
        <button class="forvoyez-analyze-button" title="Analyse with ForVoyez">
            <span class="dashicons dashicons-upload"></span>
        </button>
        <div class="forvoyez-loader"></div>
        <div class="forvoyez-image-details">
            <p><strong>Title:</strong> <?php echo esc_html($image->post_title ?: 'Not set'); ?></p>
            <p><strong>Alt Text:</strong> <?php echo esc_html($image_alt ?: 'Not set'); ?></p>
            <p><strong>Caption:</strong> <?php echo esc_html($image->post_excerpt ?: 'Not set'); ?></p>
        </div>
    </div>
    <?php
}

// AJAX handler for image click
add_action('wp_ajax_forvoyez_image_click', 'forvoyez_handle_image_click');
function forvoyez_handle_image_click()
{
    // For now, we'll just send back a success message
    wp_send_json_success(array('message' => 'Image clicked successfully'));
}

add_action('wp_ajax_forvoyez_update_image_metadata', 'forvoyez_handle_update_image_metadata');

function forvoyez_handle_update_image_metadata()
{
    check_ajax_referer('forvoyez_nonce', 'nonce');

    if (!current_user_can('upload_files')) {
        wp_send_json_error('Permission denied');
    }

    $image_id = intval($_POST['image_id']);
    $metadata = $_POST['metadata'];

    // Update alt text
    update_post_meta($image_id, '_wp_attachment_image_alt', sanitize_text_field($metadata['alt_text']));

    // Update title
    $post = array(
        'ID' => $image_id,
        'post_title' => sanitize_text_field($metadata['title']),
    );
    wp_update_post($post);

    // Update caption
    $post = array(
        'ID' => $image_id,
        'post_excerpt' => wp_kses_post($metadata['caption']),
    );
    wp_update_post($post);

    wp_send_json_success('Metadata updated successfully');
}

add_action('wp_ajax_forvoyez_load_more_images', 'forvoyez_load_more_images');

function forvoyez_load_more_images()
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

    foreach ($query_images->posts as $image) {
        if (empty($image->post_title) || empty(get_post_meta($image->ID, '_wp_attachment_image_alt', true)) || empty($image->post_excerpt)) {
            ob_start();
            forvoyez_render_image_item($image);
            $html .= ob_get_clean();
            $count++;
        }
    }

    wp_send_json_success(array(
        'html' => $html,
        'count' => $count
    ));
}

function forvoyez_count_incomplete_images() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => -1, // Get all images
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