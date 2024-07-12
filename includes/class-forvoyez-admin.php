<?php

class Forvoyez_Admin
{
    public function init()
    {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_menu_item()
    {
        add_options_page(
            'Auto Alt Text Settings',
            'Auto Alt Text',
            'manage_options',
            'forvoyez-auto-alt-text',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('settings_page_forvoyez-auto-alt-text' !== $hook) {
            return;
        }

        wp_enqueue_style('forvoyez-admin-styles', FORVOYEZ_PLUGIN_URL . 'assets/css/admin-style.css', array(), '1.0.0');
        wp_enqueue_script('forvoyez-admin-script', FORVOYEZ_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('forvoyez-api-settings', FORVOYEZ_PLUGIN_URL . 'assets/js/api-settings.js', array('jquery'), '1.0.0', true);


        wp_localize_script('forvoyez-admin-script', 'forvoyezData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('forvoyez_nonce')
        ));
    }

    public function render_admin_page()
    {
        include FORVOYEZ_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public static function display_status_configuration() {
        $api_key = forvoyez_get_api_key();
        if (!empty($api_key)) {
            echo '<p>Your ForVoyez API key is configured, you are ready to go!</p>';
        } else {
            echo '<p>Your ForVoyez API key is not configured. Please configure it to enable automatic alt text generation.</p>';
        }
    }

    public static function display_incomplete_images()
    {
        $paged = isset($_GET['paged']) ? abs((int)$_GET['paged']) : 1;
        $per_page = isset($_GET['per_page']) ? abs((int)$_GET['per_page']) : 25;
        $filters = isset($_GET['filter']) ? $_GET['filter'] : array();

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $paged,
        );

        // Apply filters
        if (!empty($filters)) {
            $meta_query = array('relation' => 'OR');

            if (in_array('alt', $filters)) {
                $meta_query[] = array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                );
            }

            if (in_array('title', $filters)) {
                $args['title'] = '';
            }

            if (in_array('caption', $filters)) {
                $args['post_excerpt'] = '';
            }

            $args['meta_query'] = $meta_query;
        }

        $query_images = new WP_Query($args);
        $total_images = $query_images->found_posts;
        $displayed_images = $query_images->post_count;
        $total_pages = ceil($total_images / $per_page);

        self::display_filters($total_images, $displayed_images);
        ?>
        <div class="forvoyez-image-grid">
            <?php
            if ($query_images->have_posts()) {
                while ($query_images->have_posts()) {
                    $query_images->the_post();
                    self::render_image_item($query_images->post);
                }
            } else {
                echo '<p>No images found matching the selected criteria.</p>';
            }
            ?>
        </div>
        <?php
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $paged
        ));

        wp_reset_postdata();
    }




}