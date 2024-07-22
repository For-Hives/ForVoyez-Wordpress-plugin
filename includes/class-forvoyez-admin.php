<?php
defined('ABSPATH') || exit;

class Forvoyez_Admin
{
    public function init()
    {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_forvoyez_load_images', array($this, 'ajax_load_images'));
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

        // Enqueue Tailwind CSS from CDN
        wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', array(), null);

        // Enqueue your custom scripts
        wp_enqueue_script('forvoyez-admin-script', FORVOYEZ_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('forvoyez-api-settings', FORVOYEZ_PLUGIN_URL . 'assets/js/api-settings.js', array('jquery'), '1.0.0', true);

        // Localize script
        wp_localize_script('forvoyez-admin-script', 'forvoyezData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('forvoyez_nonce')
        ));

        // Add Tailwind configuration
        $this->add_tailwind_config();
    }

    private function add_tailwind_config()
    {
        $tailwind_config = "
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            'forvoyez-primary': '#4a90e2',
                            'forvoyez-secondary': '#50e3c2',
                        },
                    },
                },
            }
        </script>
        ";

        // Add custom Tailwind styles
        $tailwind_styles = "
        <style type=\"text/tailwindcss\">
            @layer utilities {
                .content-auto {
                    content-visibility: auto;
                }
            }
        </style>
        ";

        add_action('admin_head', function() use ($tailwind_config, $tailwind_styles) {
            echo $tailwind_config;
            echo $tailwind_styles;
        });
    }

    public function render_admin_page()
    {
        include FORVOYEZ_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public static function display_status_configuration()
    {
        $api_key = forvoyez_get_api_key();
        if (!empty($api_key)) {
            echo '<p class="text-green-600 font-semibold">Your ForVoyez API key is configured, you are ready to go!</p>';
        } else {
            echo '<p class="text-red-600 font-semibold">Your ForVoyez API key is not configured. Please configure it to enable automatic alt text generation.</p>';
        }
    }

    public function display_incomplete_images($paged = 1, $per_page = 25, $filters = array())
    {
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

        ob_start();
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4" data-total-images="<?php echo esc_attr($total_images); ?>">
            <?php
            if ($query_images->have_posts()) {
                while ($query_images->have_posts()) {
                    $query_images->the_post();
                    Forvoyez_Image_Renderer::render_image_item($query_images->post);
                }
            } else {
                echo '<p class="col-span-full text-center text-gray-500">No images found matching the selected criteria.</p>';
            }
            ?>
        </div>
        <?php
        $pagination = $this->display_pagination($query_images, $paged, $per_page, $filters);
        echo $pagination;

        wp_reset_postdata();

        return ob_get_clean();
    }

    private function display_pagination($query, $current_page, $per_page, $filters)
    {
        $total_pages = $query->max_num_pages;

        if ($total_pages <= 1) {
            return '';
        }

        $pagination = '<nav class="forvoyez-pagination flex justify-center items-center space-x-2 mt-6">';

        // Previous page
        if ($current_page > 1) {
            $pagination .= '<a href="#" class="pagination-link bg-white text-blue-500 hover:bg-blue-100 px-3 py-2 rounded" data-page="' . ($current_page - 1) . '">&laquo; Previous</a>';
        }

        // Page numbers
        $start_page = max(1, $current_page - 2);
        $end_page = min($total_pages, $current_page + 2);

        for ($i = $start_page; $i <= $end_page; $i++) {
            $active_class = ($i == $current_page) ? 'bg-blue-500 text-white' : 'bg-white text-blue-500 hover:bg-blue-100';
            $pagination .= '<a href="#" class="pagination-link ' . $active_class . ' px-3 py-2 rounded" data-page="' . $i . '">' . $i . '</a>';
        }

        // Next page
        if ($current_page < $total_pages) {
            $pagination .= '<a href="#" class="pagination-link bg-white text-blue-500 hover:bg-blue-100 px-3 py-2 rounded" data-page="' . ($current_page + 1) . '">Next &raquo;</a>';
        }

        $pagination .= '</nav>';

        return $pagination;
    }

    private function count_total_images($filters) {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
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

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    public function ajax_load_images() {
        check_ajax_referer('forvoyez_nonce', 'nonce');

        $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 25;
        $filters = isset($_POST['filters']) ? $this->parse_filters($_POST['filters']) : array();

        $html = $this->display_incomplete_images($paged, $per_page, $filters);
        $total_images = $this->count_total_images($filters);

        wp_send_json_success(array(
            'html' => $html,
            'total_images' => $total_images,
            'current_page' => $paged,
            'per_page' => $per_page
        ));
    }

    private function parse_filters($filters) {
        $parsed = array();
        foreach ($filters as $filter) {
            if ($filter['name'] === 'filter[]') {
                $parsed[] = $filter['value'];
            }
        }
        return $parsed;
    }
}