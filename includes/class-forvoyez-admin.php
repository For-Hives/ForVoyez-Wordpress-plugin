<?php
/**
 * Class Forvoyez_Admin
 *
 * Handles all admin-related functionality for the Forvoyez plugin.
 */
defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_Admin {
	/**
	 * @var Forvoyez_API_Manager
	 */
	private $api_manager;

	/**
	 * Constructor.
	 *
	 * @param Forvoyez_API_Manager $api_manager API manager instance.
	 */
	public function __construct(Forvoyez_API_Manager $api_manager) {
		$this->api_manager = $api_manager;
	}

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action('admin_menu', [$this, 'add_menu_item']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
		add_action('wp_ajax_forvoyez_load_images', [$this, 'ajax_load_images']);
		add_action('wp_ajax_forvoyez_get_image_counts', [
			$this,
			'ajax_get_image_counts',
		]);
		add_action('wp_ajax_forvoyez_get_image_ids', [
			$this,
			'ajax_get_image_ids',
		]);
		add_action('wp_ajax_forvoyez_verify_api_key', [
			$this,
			'ajax_verify_api_key',
		]);
		add_action('wp_ajax_forvoyez_get_credits', [$this, 'ajax_get_credits']);
		add_action('admin_footer', [$this, 'maybe_show_low_credits_warning']);
	}

	/**
	 * Add menu item to WordPress admin.
	 */
	public function add_menu_item() {
		$page_hook = add_menu_page(
			esc_html__('Auto Alt Text for Images', 'auto-alt-text-for-images'),
			esc_html__('Auto Alt Text', 'auto-alt-text-for-images'),
			'manage_options',
			'auto-alt-text-for-images',
			[$this, 'render_admin_page'],
			'dashicons-format-image',
			30,
		);

		// Add action to generate nonce
		add_action('load-' . $page_hook, [$this, 'add_admin_page_nonce']);
	}

	public function add_admin_page_nonce() {
		add_filter('admin_body_class', function ($classes) {
			return $classes . ' forvoyez-admin-page';
		});
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts($hook) {
		if ('toplevel_page_auto-alt-text-for-images' !== $hook) {
			return;
		}

		// Enqueue Tailwind CSS from CDN
		wp_enqueue_script(
			'tailwindcss',
			'https://cdn.tailwindcss.com',
			[],
			FORVOYEZ_VERSION,
			false,
		);

		// Enqueue custom Tailwind config
		wp_enqueue_script(
			'forvoyez-tailwind-config',
			FORVOYEZ_PLUGIN_URL . 'assets/js/tailwind-config.js',
			['tailwindcss'],
			FORVOYEZ_VERSION,
			false,
		);

		// Enqueue custom Tailwind utilities
		wp_enqueue_style(
			'forvoyez-tailwind-utilities',
			FORVOYEZ_PLUGIN_URL . 'assets/css/tailwind-utilities.css',
			[],
			FORVOYEZ_VERSION,
		);

		// Enqueue custom scripts
		wp_enqueue_script(
			'forvoyez-admin-script',
			FORVOYEZ_PLUGIN_URL . 'assets/js/admin-script.js',
			['jquery'],
			FORVOYEZ_VERSION,
			true,
		);
		wp_enqueue_script(
			'forvoyez-api-settings',
			FORVOYEZ_PLUGIN_URL . 'assets/js/api-settings.js',
			['jquery'],
			FORVOYEZ_VERSION,
			true,
		);

		// Nouvel ajout: script de gestion des crédits
		wp_enqueue_script(
			'forvoyez-credits-manager',
			FORVOYEZ_PLUGIN_URL . 'assets/js/credits-manager.js',
			['jquery', 'forvoyez-admin-script'],
			FORVOYEZ_VERSION,
			true,
		);

		// Localize script
		wp_localize_script('forvoyez-admin-script', 'forvoyezData', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('forvoyez_nonce'),
			'saveApiKeyNonce' => wp_create_nonce('forvoyez_save_api_key_nonce'),
			'loadImagesNonce' => wp_create_nonce('forvoyez_load_images_nonce'),
			'getImageCountsNonce' => wp_create_nonce(
				'forvoyez_get_image_counts_nonce',
			),
			'getImageIdsNonce' => wp_create_nonce(
				'forvoyez_get_image_ids_nonce',
			),
			'verifyApiKeyNonce' => wp_create_nonce(
				'forvoyez_verify_api_key_nonce',
			),
			'loadMoreImagesNonce' => wp_create_nonce(
				'forvoyez_load_more_images_nonce',
			),
			'verifyAjaxRequestNonce' => wp_create_nonce(
				'forvoyez_verify_ajax_request_nonce',
			),
			'analyseImageNonce' => wp_create_nonce(
				'forvoyez_analyse_image_nonce',
			),
			'processImageBatchNonce' => wp_create_nonce(
				'forvoyez_process_image_batch_nonce',
			),
			'saveContextNonce' => wp_create_nonce(
				'forvoyez_save_context_nonce',
			),
			'saveLanguageNonce' => wp_create_nonce(
				'forvoyez_save_language_nonce',
			),
			'toggleAutoAnalyzeNonce' => wp_create_nonce(
				'forvoyez_toggle_auto_analyze_nonce',
			),
		]);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		if (!current_user_can('manage_options')) {
			wp_die(
				esc_html__(
					'You do not have sufficient permissions to access this page.',
					'auto-alt-text-for-images',
				),
			);
		}

		// Generate nonce for this request
		$nonce = wp_create_nonce('forvoyez_admin_page');

		$active_tab = isset($_GET['tab'])
			? sanitize_text_field(wp_unslash($_GET['tab']))
			: 'dashboard';

		// Include the template file
		include FORVOYEZ_PLUGIN_DIR . 'templates/main-page.php';
	}

	/**
	 * Display API key configuration status.
	 */
	public static function display_status_configuration() {
		$api_key = forvoyez_get_api_key();
		if (empty($api_key)) {
			echo '<p class="text-red-600 font-semibold">' .
				esc_html__(
					'Your ForVoyez API key is not configured. Please configure it to enable automatic alt text generation.',
					'auto-alt-text-for-images',
				) .
				'</p>';
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
	public function display_incomplete_images(
		$paged = 1,
		$per_page = 25,
		$filters = [],
	) {
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
	private function get_query_args($paged, $per_page, $filters) {
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
	private function build_meta_query($filters) {
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
	private function render_images_grid($query_images, $total_images) {
		?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4" data-total-images="
        <?php echo esc_attr($total_images); ?>
        ">
            <?php if ($query_images->have_posts()) {
            	while ($query_images->have_posts()) {
            		$query_images->the_post();
            		Forvoyez_Image_Renderer::render_image_item(
            			$query_images->post,
            		);
            	}
            } else {
            	echo '<p class="col-span-full text-center text-gray-500">' .
            		esc_html__(
            			'No images found matching the selected criteria.',
            			'auto-alt-text-for-images',
            		) .
            		'</p>';
            } ?>
        </div>
        <?php
	}

	/**
	 * Count total images based on filters.
	 *
	 * @param array $filters Applied filters.
	 * @return int Total number of images.
	 */
	private function count_total_images($filters) {
		$args = $this->get_query_args(1, -1, $filters);
		$args['fields'] = 'ids'; // Only get post IDs for efficiency
		$query = new WP_Query($args);

		return $query->found_posts;
	}

	/**
	 * Get total images count.
	 * @return mixed
	 */
	private function get_total_images_count() {
		return wp_count_posts('attachment')->inherit;
	}

	/**
	 * Get processed images count.
	 * @return int|mixed
	 */
	private function get_processed_images_count() {
		return $this->get_total_images_count() -
			$this->count_images_with_missing_data(['alt']);
	}

	/**
	 * Get pending images count.
	 * @return int
	 */
	private function get_pending_images_count() {
		return $this->count_images_with_missing_data([
			'alt',
			'title',
			'caption',
		]);
	}

	/**
	 * Parse and sanitize filters.
	 *
	 * @param array $filters Raw filters array.
	 * @return array Sanitized filters.
	 */
	protected function parse_and_sanitize_filters($raw_filters) {
		$sanitized_filters = [];
		$allowed_filters = ['alt', 'title', 'caption'];

		foreach ($raw_filters as $filter) {
			if (
				isset($filter['name']) &&
				$filter['name'] === 'filter[]' &&
				isset($filter['value'])
			) {
				$sanitized_filter = sanitize_text_field($filter['value']);
				if (in_array($sanitized_filter, $allowed_filters)) {
					$sanitized_filters[] = $sanitized_filter;
				}
			}
		}

		return $sanitized_filters;
	}

	/**
	 * AJAX handler for loading images.
	 */
	public function ajax_load_images() {
		check_ajax_referer('forvoyez_load_images_nonce', 'nonce');
		if (!current_user_can('upload_files')) {
			wp_send_json_error(
				esc_html__('Permission denied', 'auto-alt-text-for-images'),
				403,
			);
		}

		$paged = isset($_POST['paged'])
			? absint(wp_unslash($_POST['paged']))
			: 1;
		$per_page = isset($_POST['per_page'])
			? absint(wp_unslash($_POST['per_page']))
			: 25;
		$raw_filters = isset($_POST['filters'])
			? wp_unslash($_POST['filters'])
			: [];
		$filters = $this->parse_and_sanitize_filters($raw_filters);

		$result = $this->display_incomplete_images($paged, $per_page, $filters);
		$total_images = $this->count_total_images($filters);

		$pagination_html = $this->display_pagination(
			$total_images,
			$paged,
			$per_page,
		);

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
	private function display_pagination(
		$total_images,
		$current_page,
		$per_page,
	) {
		$total_pages = ceil($total_images / $per_page);

		if ($total_pages <= 1) {
			return '';
		}

		$pagination =
			'<nav class="forvoyez-pagination flex justify-center items-center space-x-2 mt-6">';

		// Previous page
		if ($current_page > 1) {
			$pagination .= $this->pagination_link(
				$current_page - 1,
				esc_html__('&laquo; Previous', 'auto-alt-text-for-images'),
			);
		}

		// Page numbers
		$start_page = max(1, $current_page - 2);
		$end_page = min($total_pages, $current_page + 2);

		for ($i = $start_page; $i <= $end_page; $i++) {
			$active_class =
				$i == $current_page
					? 'bg-blue-500 text-white'
					: 'bg-white text-blue-500 hover:bg-blue-100';
			$pagination .= $this->pagination_link($i, $i, $active_class);
		}

		// Next page
		if ($current_page < $total_pages) {
			$pagination .= $this->pagination_link(
				$current_page + 1,
				esc_html__('Next &raquo;', 'auto-alt-text-for-images'),
			);
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
	private function pagination_link(
		$page,
		$text,
		$class = 'bg-white text-blue-500 hover:bg-blue-100',
	) {
		return sprintf(
			'<a href="#" class="pagination-link %s px-3 py-2 rounded" data-page="%d">%s</a>',
			esc_attr($class),
			esc_attr($page),
			esc_html($text),
		);
	}

	/**
	 * Get image counts.
	 *
	 * @return array Image counts.
	 */
	public function get_image_counts() {
		$all_count = wp_count_posts('attachment')->inherit;
		$missing_alt_count = $this->count_images_with_missing_data(['alt']);
		$missing_all_count = $this->count_images_with_missing_data([
			'alt',
			'title',
			'caption',
		]);

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
	private function count_images_with_missing_data($missing_fields) {
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
	public function ajax_get_image_counts() {
		check_ajax_referer('forvoyez_get_image_counts_nonce', 'nonce');
		if (!current_user_can('upload_files')) {
			wp_send_json_error(
				esc_html__('Permission denied', 'auto-alt-text-for-images'),
				403,
			);
		}
		wp_send_json_success($this->get_image_counts());
	}

	/**
	 * Get image IDs based on type.
	 *
	 * @param string $type Type of images to retrieve ('all', 'missing_all', 'missing_alt').
	 * @return array Array of image IDs.
	 */
	public function get_image_ids($type = 'all') {
		global $wpdb;

		// Define a unique cache key based on the type
		$cache_key = 'forvoyez_image_ids_' . $type;

		// Try to get the results from cache
		$results = wp_cache_get($cache_key);

		// If the results are not in cache, query the database
		if (false === $results) {
			if ($type === 'all') {
				$results = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s",
						'attachment',
						'image/%',
					),
				);
			} elseif ($type === 'missing_alt') {
				$results = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                    WHERE p.post_type = %s
                    AND p.post_mime_type LIKE %s
                    AND (pm.meta_value IS NULL OR pm.meta_value = %s)",
						'_wp_attachment_image_alt',
						'attachment',
						'image/%',
						'',
					),
				);
			} elseif ($type === 'missing_all') {
				$results = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID
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
						'',
					),
				);
			} else {
				// Handle invalid type
				return [];
			}

			// Cache the results for future use
			wp_cache_set($cache_key, $results, '', 3600); // Cache for 1 hour
		}

		return array_map('intval', $results);
	}

	/**
	 * AJAX handler for getting image IDs.
	 */
	public function ajax_get_image_ids() {
		check_ajax_referer('forvoyez_get_image_ids_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error(
				esc_html__('Permission denied', 'auto-alt-text-for-images'),
				403,
			);
		}

		$allowed_types = ['all', 'missing_all', 'missing_alt'];
		$type = isset($_POST['type'])
			? sanitize_text_field(wp_unslash($_POST['type']))
			: 'all';
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
	public function ajax_verify_api_key() {
		check_ajax_referer('forvoyez_verify_api_key_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(
				esc_html__('Permission denied', 'auto-alt-text-for-images'),
				403,
			);
		}

		$result = $this->api_manager->verify_api_key();

		if ($result['success']) {
			wp_send_json_success($result['message']);
		} else {
			wp_send_json_error($result['message']);
		}
	}

	/**
	 * Display low credits warning in admin footer if needed.
	 */
	public function maybe_show_low_credits_warning() {
		// Only show on plugin pages
		$screen = get_current_screen();
		if (
			!$screen ||
			(strpos($screen->id, 'forvoyez') === false &&
				strpos($screen->id, 'auto-alt-text') === false)
		) {
			return;
		}

		$token_info = forvoyez_get_token_info();
		if ($token_info['success'] && isset($token_info['user']['credits'])) {
			$credits = $token_info['user']['credits'];
			if ($credits < 10) { ?>
				<div class="notice notice-warning" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; width: 300px; border-left: 4px solid #f0b849; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
					<p>
						<strong><?php esc_html_e(
      	'ForVoyez - Low Credits Warning',
      	'auto-alt-text-for-images',
      ); ?></strong><br>
						<?php echo sprintf(
      	esc_html__(
      		'You only have %d credits remaining. Please consider recharging to continue using the ForVoyez service.',
      		'auto-alt-text-for-images',
      	),
      	$credits,
      ); ?>
					</p>
				</div>
				<?php }
		}
	}

	/**
	 * AJAX callback to get current credits.
	 */
	public function ajax_get_credits() {
		check_ajax_referer('forvoyez_verify_ajax_request_nonce', 'nonce');

		if (!current_user_can('upload_files')) {
			wp_send_json_error([
				'message' => __('Permission denied', 'auto-alt-text-for-images')
			], 403);
			return;
		}

		// Récupérer les infos du token avec une mise en cache minimale
		$token_info = forvoyez_get_token_info();

		if ($token_info['success']) {
			// Récupérer les valeurs
			$credits = $token_info['user']['credits'];
			$is_subscribed = $token_info['subscription']['isSubscribed'] ?? false;
			$plan_name = $is_subscribed ? $token_info['subscription']['plan']['name'] : '';
			$status = $token_info['subscription']['statusFormatted'] ?? '';
			$renews_at = $token_info['subscription']['renewsAt'] ?? '';

			// Renvoyer les données complètes
			wp_send_json_success([
				'credits' => $credits,
				'is_subscribed' => $is_subscribed,
				'plan_name' => $plan_name,
				'status' => $status,
				'renews_at' => $renews_at,
				'has_low_credits' => $credits < 10,
				'token_ok' => true
			]);
		} else {
			wp_send_json_error([
				'message' => $token_info['error']['message'] ??
				             __('Could not retrieve credit information', 'auto-alt-text-for-images'),
				'token_ok' => false,
				'credits' => 0,
				'is_subscribed' => false
			]);
		}
	}
}
