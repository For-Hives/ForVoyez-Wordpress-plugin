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
        $api_key = get_option('forvoyez_api_key');
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

    private static function display_filters($total_images, $displayed_images)
    {
        $per_page = isset($_GET['per_page']) ? abs((int)$_GET['per_page']) : 25;
        $filter = isset($_GET['filter']) ? $_GET['filter'] : array();
        ?>
        <div class="forvoyez-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="forvoyez-auto-alt-text">
                <div class="forvoyez-filter-row">
                    <div class="forvoyez-filter-group">
                        <label>Items per page:
                            <select name="per_page">
                                <option value="25" <?php selected($per_page, 25); ?>>25</option>
                                <option value="50" <?php selected($per_page, 50); ?>>50</option>
                                <option value="100" <?php selected($per_page, 100); ?>>100</option>
                                <option value="-1" <?php selected($per_page, -1); ?>>All</option>
                            </select>
                        </label>
                    </div>
                    <div class="forvoyez-filter-group">
                        <label><input type="checkbox" name="filter[]" value="alt" <?php checked(in_array('alt', $filter)); ?>> Missing Alt</label>
                        <label><input type="checkbox" name="filter[]" value="title" <?php checked(in_array('title', $filter)); ?>> Missing Title</label>
                        <label><input type="checkbox" name="filter[]" value="caption" <?php checked(in_array('caption', $filter)); ?>> Missing Caption</label>
                    </div>
                    <div class="forvoyez-filter-group">
                        <input type="submit" value="Apply Filters" class="button">
                    </div>
                </div>
                <div class="forvoyez-filter-row">
                    <div class="forvoyez-displayed-images">
                        Images Displayed: <strong><?php echo $displayed_images; ?></strong>
                        /<?php echo $total_images; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public static function render_image_item($image)
    {
        $image_url = wp_get_attachment_url($image->ID);
        $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $is_analyzed = get_post_meta($image->ID, '_forvoyez_analyzed', true);
        $disabled_class = $is_analyzed ? 'forvoyez-analyzed' : '';
        $all_complete = !empty($image_alt) && !empty($image->post_title) && !empty($image->post_excerpt);
        ?>
        <div class="forvoyez-image-item <?php echo $disabled_class; ?>" data-image-id="<?php echo esc_attr($image->ID); ?>">
            <input type="checkbox" class="forvoyez-image-checkbox" value="<?php echo esc_attr($image->ID); ?>">
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
            <div class="forvoyez-metadata-icons">
                <?php if ($all_complete) : ?>
                    <span class="dashicons dashicons-yes-alt" title="All metadata complete" style="color: green;"></span>
                <?php else : ?>
                    <?php if (empty($image_alt)) : ?>
                        <span class="dashicons dashicons-editor-textcolor" title="Missing Alt Text"></span>
                    <?php endif; ?>
                    <?php if (empty($image->post_title)) : ?>
                        <span class="dashicons dashicons-heading" title="Missing Title"></span>
                    <?php endif; ?>
                    <?php if (empty($image->post_excerpt)) : ?>
                        <span class="dashicons dashicons-editor-quote" title="Missing Caption"></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ($is_analyzed): ?>
                <div class="forvoyez-checkmark"><span class="dashicons dashicons-yes-alt"></span></div>
            <?php else: ?>
                <button class="forvoyez-analyze-button" title="Analyze with ForVoyez">
                    <span class="dashicons dashicons-upload"></span>
                </button>
            <?php endif; ?>
            <div class="forvoyez-loader"></div>
            <div class="forvoyez-image-details">
                <p><strong>Title:</strong> <?php echo esc_html($image->post_title ?: 'Not set'); ?></p>
                <p><strong>Alt Text:</strong> <?php echo esc_html($image_alt ?: 'Not set'); ?></p>
                <p><strong>Caption:</strong> <?php echo esc_html($image->post_excerpt ?: 'Not set'); ?></p>
            </div>
        </div>
        <?php
    }
}