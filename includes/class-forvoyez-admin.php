<?php
/**
 * Class Forvoyez_Admin
 *
 * Handles all admin-related functionality for the Forvoyez plugin.
 */
defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_Admin
{
    /**
     * @var Forvoyez_API_Manager
     */
    private $api_manager;

    /**
     * Constructor.
     *
     * @param Forvoyez_API_Manager $api_manager API manager instance.
     */
    public function __construct(Forvoyez_API_Manager $api_manager)
    {
        $this->api_manager = $api_manager;
    }

    /**
     * Initialize admin hooks.
     */
    public function init()
    {
        add_action('admin_menu', [$this, 'add_menu_item']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_forvoyez_load_images', [$this, 'ajax_load_images']);
        add_action('wp_ajax_forvoyez_get_image_counts', [$this, 'ajax_get_image_counts']);
        add_action('wp_ajax_forvoyez_get_image_ids', [$this, 'ajax_get_image_ids']);
        add_action('wp_ajax_forvoyez_verify_api_key', [$this, 'ajax_verify_api_key']);
    }

    /**
     * Add menu item to WordPress admin.
     */
    public function add_menu_item()
    {
        add_menu_page(
            __('Auto Alt Text for Images', 'forvoyez-auto-alt-text-for-images'),
            __('Auto Alt Text', 'forvoyez-auto-alt-text-for-images'),
            'manage_options',
            'forvoyez-auto-alt-text',
            [$this, 'render_admin_page'],
            'dashicons-format-image',
            30
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts($hook)
    {
        if ('toplevel_page_forvoyez-auto-alt-text' !== $hook) {
            return;
        }

        // Enqueue Tailwind CSS from CDN
        wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', [], null);

        // Enqueue custom scripts
        wp_enqueue_script(
            'forvoyez-admin-script',
            FORVOYEZ_PLUGIN_URL . 'assets/js/admin-script.js',
            ['jquery'],
            FORVOYEZ_VERSION,
            true
        );
        wp_enqueue_script(
            'forvoyez-api-settings',
            FORVOYEZ_PLUGIN_URL . 'assets/js/api-settings.js',
            ['jquery'],
            FORVOYEZ_VERSION,
            true
        );

        // include css style
        wp_enqueue_style('forvoyez-admin-style', FORVOYEZ_PLUGIN_URL . 'assets/css/admin-style.css', [], FORVOYEZ_VERSION);

        // Localize script
        wp_localize_script('forvoyez-admin-script', 'forvoyezData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('forvoyez_nonce'),
        ]);

        // Add Tailwind configuration
        $this->add_tailwind_config();
    }

    /**
     * Enqueue custom scripts for the admin page.
     */
    private function enqueue_custom_scripts()
    {
        wp_enqueue_script(
            'forvoyez-admin-script',
            FORVOYEZ_PLUGIN_URL . 'assets/js/admin-script.js',
            ['jquery'],
            FORVOYEZ_VERSION,
            true
        );
        wp_enqueue_script(
            'forvoyez-api-settings',
            FORVOYEZ_PLUGIN_URL . 'assets/js/api-settings.js',
            ['jquery'],
            FORVOYEZ_VERSION,
            true
        );
    }

    /**
     * Add Tailwind configuration to admin head.
     */
    private function add_tailwind_config()
    {
        $tailwind_config = "<script>tailwind.config = {theme: {extend: {colors: {'forvoyez-primary': '#4a90e2','forvoyez-secondary': '#50e3c2',},},},}</script>";
        $tailwind_styles = "<style type='text/tailwindcss'>@layer utilities {.content-auto {content-visibility: auto;}}</style>";

        add_action('admin_head', function () use ($tailwind_config, $tailwind_styles) {
            echo $tailwind_config . $tailwind_styles;
        });
    }

    /**
     * Render the admin page.
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'forvoyez-auto-alt-text-for-images'));
        }
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        include FORVOYEZ_PLUGIN_DIR . 'templates/main-page.php';
    }

    /**
     * Display API key configuration status.
     */
    public static function display_status_configuration()
    {
        $api_key = forvoyez_get_api_key();
        if (empty($api_key)) {
            echo '<p class="text-red-600 font-semibold">' . __('Your ForVoyez API key is not configured. Please configure it to enable automatic alt text generation.', 'forvoyez-auto-alt-text-for-images') . '</p>';
        }
    }

    /**
     * Display incomplete images.
     *
     * @param int $paged Current page number.
     * @param int $per_page Number of items per page.
     * @param array $filters Applied filters.
     * @return array HTML content and number of displayed images.
     */
    public function display_incomplete_images($paged = 1, $per_page = 25, $filters = [])
    {
        $args = $this->get_query_args($paged, $per_page, $filters);
        $query_images = new WP_Query($args);
        $total_images = $query_images->found_posts;
        $displayed_images = $query_images->post_count;

        ob_start();
        $this->render_images_grid($query_images, $total_images);
        $html = ob_get_clean();

        wp_reset_postdata();

        return [
            'html' => $html,
            'displayed_images' => $displayed_images,
        ];
    }

    /**
     * Get WP_Query arguments for incomplete images.
     *
     * @param int $paged Current page number.
     * @param int $per_page Number of items per page.
     * @param array $filters Applied filters.
     * @return array Query arguments.
     */
    private function get_query_args($paged, $per_page, $filters)
    {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'paged' => $paged,
        ];

        if (!empty($filters)) {
            $args['meta_query'] = $this->build_meta_query($filters);
        }

        return $args;
    }

    /**
     * Build meta query based on filters.
     *
     * @param array $filters Applied filters.
     * @return array Meta query.
     */
    private function build_meta_query($filters)
    {
        $meta_query = ['relation' => 'OR'];

        if (in_array('alt', $filters, true)) {
            $meta_query[] = [
                'key' => '_wp_attachment_image_alt',
                'compare' => 'NOT EXISTS',
            ];
        }

        if (in_array('title', $filters, true)) {
            $meta_query[] = [
                'key' => 'post_title',
                'value' => '',
                'compare' => '=',
            ];
        }

        if (in_array('caption', $filters, true)) {
            $meta_query[] = [
                'key' => 'post_excerpt',
                'value' => '',
                'compare' => '=',
            ];
        }

        return $meta_query;
    }

    /**
     * Render images grid.
     *
     * @param WP_Query $query_images Query result containing images.
     * @param int $total_images Total number of images.
     */
    private function render_images_grid($query_images, $total_images)
    {
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4" data-total-images="<?php echo esc_attr($total_images); ?>">
            <?php
            if ($query_images->have_posts()) {
                while ($query_images->have_posts()) {
                    $query_images->the_post();
                    Forvoyez_Image_Renderer::render_image_item($query_images->post);
                }
            } else {
                echo '<p class="col-span-full text-center text-gray-500">' . __('No images found matching the selected criteria.', 'forvoyez-auto-alt-text-for-images') . '</p>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Count total images based on filters.
     *
     * @param array $filters Applied filters.
     * @return int Total number of images.
     */
    private function count_total_images($filters)
    {
        $args = $this->get_query_args(1, -1, $filters);
        $args['fields'] = 'ids'; // Only get post IDs for efficiency
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Get total images count.
     * @return mixed
     */
    private function get_total_images_count()
    {
        return wp_count_posts('attachment')->inherit;
    }

    /**
     * Get processed images count.
     * @return int|mixed
     */
    private function get_processed_images_count()
    {
        return $this->get_total_images_count() - $this->count_images_with_missing_data(['alt']);
    }

    /**
     * Get pending images count.
     * @return int
     */
    private function get_pending_images_count()
    {
        return $this->count_images_with_missing_data(['alt', 'title', 'caption']);
    }

    /**
     * Parse and sanitize filters.
     *
     * @param array $filters Raw filters array.
     * @return array Sanitized filters.
     */
    private function parse_and_sanitize_filters($filters)
    {
        $sanitized = [];
        $allowed_filters = ['alt', 'title', 'caption'];
        foreach ($filters as $filter) {
            if (
                isset($filter['name'], $filter['value']) &&
                $filter['name'] === 'filter[]' &&
                in_array($filter['value'], $allowed_filters, true)
            ) {
                $sanitized[] = sanitize_text_field($filter['value']);
            }
        }
        return $sanitized;
    }

    /**
     * AJAX handler for loading images.
     */
    public function ajax_load_images()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Permission denied', 'forvoyez-auto-alt-text-for-images'), 403);
        }

        $paged = isset($_POST['paged']) ? absint(wp_unslash($_POST['paged'])) : 1;
        $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 25;
        $filters = isset($_POST['filters']) ? $this->parse_and_sanitize_filters(wp_unslash($_POST['filters'])) : [];

        $result = $this->display_incomplete_images($paged, $per_page, $filters);
        $total_images = $this->count_total_images($filters);

        $pagination_html = $this->display_pagination($total_images, $paged, $per_page);

        wp_send_json_success([
            'html' => $result['html'],
            'total_images' => $total_images,
            'displayed_images' => $result['displayed_images'],
            'current_page' => $paged,
            'per_page' => $per_page,
            'pagination_html' => $pagination_html,
        ]);
    }

    /**
     * Generate pagination HTML.
     *
     * @param int $total_images Total number of images.
     * @param int $current_page Current page number.
     * @param int $per_page Number of items per page.
     * @return string Pagination HTML.
     */
    private function display_pagination($total_images, $current_page, $per_page)
    {
        $total_pages = ceil($total_images / $per_page);

        if ($total_pages <= 1) {
            return '';
        }

        $pagination = '<nav class="forvoyez-pagination flex justify-center items-center space-x-2 mt-6">';

        // Previous page
        if ($current_page > 1) {
            $pagination .= $this->pagination_link($current_page - 1, __('&laquo; Previous', 'forvoyez-auto-alt-text-for-images'));
        }

        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        for ($i = $start_page; $i <= $end_page; $i++) {
            $active_class = $i == $current_page ? 'bg-blue-500 text-white' : 'bg-white text-blue-500 hover:bg-blue-100';
            $pagination .= $this->pagination_link($i, $i, $active_class);
        }

        // Next page
        if ($current_page < $total_pages) {
            $pagination .= $this->pagination_link($current_page + 1, __('Next &raquo;', 'forvoyez-auto-alt-text-for-images'));
        }

        $pagination .= '</nav>';

        return $pagination;
    }

    /**
     * Generate a pagination link.
     *
     * @param int $page Page number.
     * @param string $text Link text.
     * @param string $class Additional CSS classes.
     * @return string Pagination link HTML.
     */
    private function pagination_link($page, $text, $class = 'bg-white text-blue-500 hover:bg-blue-100')
    {
        return sprintf(
            '<a href="#" class="pagination-link %s px-3 py-2 rounded" data-page="%d">%s</a>',
            esc_attr($class),
            esc_attr($page),
            esc_html($text)
        );
    }

    /**
     * Get image counts.
     *
     * @return array Image counts.
     */
    public function get_image_counts()
    {
        $all_count = wp_count_posts('attachment')->inherit;
        $missing_alt_count = $this->count_images_with_missing_data(['alt']);
        $missing_all_count = $this->count_images_with_missing_data(['alt', 'title', 'caption']);

        return [
            'all' => $all_count,
            'missing_alt' => $missing_alt_count,
            'missing_all' => $missing_all_count,
        ];
    }

    /**
     * Count images with missing data.
     *
     * @param array $missing_fields Fields to check for missing data.
     * @return int Number of images with missing data.
     */
    private function count_images_with_missing_data($missing_fields)
    {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $this->build_meta_query($missing_fields),
        ];

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * AJAX handler for getting image counts.
     */
    public function ajax_get_image_counts()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Permission denied', 'forvoyez-auto-alt-text-for-images'), 403);
        }
        wp_send_json_success($this->get_image_counts());
    }

    /**
     * Get image IDs based on type.
     *
     * @param string $type Type of images to retrieve ('all', 'missing_all', 'missing_alt').
     * @return array Array of image IDs.
     */
    public function get_image_ids($type = 'all')
    {
        global $wpdb;

        $query = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE " . $wpdb->prepare('%s', 'image/%');

        if ($type === 'missing_alt') {
            $query = $wpdb->prepare("SELECT p.ID 
              FROM {$wpdb->posts} p 
              LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
              WHERE p.post_type = %s 
              AND p.post_mime_type LIKE %s
              AND (pm.meta_value IS NULL OR pm.meta_value = %s)",
                '_wp_attachment_image_alt',
                'attachment',
                'image/%',
                '');
        } elseif ($type === 'missing_all') {
            $query = $wpdb->prepare("SELECT p.ID 
              FROM {$wpdb->posts} p 
              LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
              WHERE p.post_type = %s 
              AND p.post_mime_type LIKE %s
              AND (pm.meta_value IS NULL OR pm.meta_value = %s OR p.post_title = %s OR p.post_excerpt = %s)",
                '_wp_attachment_image_alt',
                'attachment',
                'image/%',
                '',
                '',
                '');
        }

        $results = array_map('intval', $wpdb->get_col($query));

        return $results;
    }

    /**
     * AJAX handler for getting image IDs.
     */
    public function ajax_get_image_ids()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Permission denied', 'forvoyez-auto-alt-text-for-images'), 403);
        }

        $allowed_types = ['all', 'missing_all', 'missing_alt'];
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'all';
        $type = in_array($type, $allowed_types, true) ? $type : 'all';

        $image_ids = $this->get_image_ids($type);

        wp_send_json_success([
            'image_ids' => $image_ids,
            'count' => count($image_ids),
        ]);
    }

    /**
     * AJAX handler for verifying API key.
     */
    public function ajax_verify_api_key()
    {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'forvoyez-auto-alt-text-for-images'), 403);
        }

        $result = $this->api_manager->verify_api_key();

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}