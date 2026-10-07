<?php
/**
 * Class Forvoyez_Image_Processor
 *
 * Handles image processing and metadata management for the Forvoyez plugin.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit( 'Direct access to this file is not allowed.' );

class Forvoyez_Image_Processor {

	private $api_client;

	public function __construct() {
		$api_key          = forvoyez_get_api_key();
		$language         = forvoyez_get_language();
		$context          = forvoyez_get_context();
		$this->api_client = new Forvoyez_API_Manager( $api_key, $language, $context );
	}

	public function init() {
		add_action(
			'wp_ajax_forvoyez_analyze_image',
			array(
				$this,
				'ajax_analyze_image',
			)
		);
		add_action(
			'wp_ajax_forvoyez_update_image_metadata',
			array(
				$this,
				'update_image_metadata',
			)
		);
		add_action(
			'wp_ajax_forvoyez_load_more_images',
			array(
				$this,
				'load_more_images',
			)
		);
		add_action(
			'wp_ajax_forvoyez_bulk_analyze_images',
			array(
				$this,
				'bulk_analyze_images',
			)
		);
		add_action(
			'wp_ajax_forvoyez_analyze_single_image',
			array(
				$this,
				'analyze_single_image',
			)
		);
		add_action(
			'wp_ajax_forvoyez_process_image_batch',
			array(
				$this,
				'process_image_batch',
			)
		);

		// Add AJAX handler for media library direct update
		add_action('wp_ajax_forvoyez_analyze_media_image', [$this, 'analyze_and_update_media_image']);

		// Add AJAX handler for bulk image analysis
		add_action('wp_ajax_forvoyez_bulk_analyze_media_images', [$this, 'bulk_analyze_media_images']);

		// Hook into the WordPress upload process
		add_action('add_attachment', array($this, 'schedule_image_analysis'));

		// Add custom cron action
		add_action('forvoyez_analyze_single_image', array($this, 'cron_analyze_single_image'));

		// New hooks for credit updates
		add_action('forvoyez_image_analyzed', array($this, 'trigger_credits_update'));
		add_action('forvoyez_batch_completed', array($this, 'trigger_credits_update_batch'));
	}

	/**
	 * Déclencher une mise à jour des crédits après l'analyse d'une image.
	 *
	 * @param int $image_id ID de l'image analysée
	 */
	public function trigger_credits_update($image_id) {
		// Cette fonction sert principalement à dire au frontend de rafraîchir les crédits
		// Le hook JavaScript 'forvoyez:image-analyzed' s'occupera de la mise à jour côté client
		do_action('forvoyez_credits_updated');
	}

	/**
	 * Déclencher une mise à jour des crédits après l'analyse d'un lot d'images.
	 *
	 * @param int $count Nombre d'images traitées
	 */
	public function trigger_credits_update_batch($count) {
		// Cette fonction sert principalement à dire au frontend de rafraîchir les crédits
		// Le hook JavaScript 'forvoyez:batch-completed' s'occupera de la mise à jour côté client
		do_action('forvoyez_credits_updated');
	}

	private function sanitize_and_validate_metadata( $raw_metadata ) {
		$sanitized_metadata = array();

		if ( isset( $raw_metadata['alt_text'] ) ) {
			$sanitized_metadata['alt_text'] = wp_strip_all_tags( $raw_metadata['alt_text'] );
		}

		if ( isset( $raw_metadata['title'] ) ) {
			$sanitized_metadata['title'] = sanitize_text_field( $raw_metadata['title'] );
		}

		if ( isset( $raw_metadata['caption'] ) ) {
			$sanitized_metadata['caption'] = wp_kses_post( $raw_metadata['caption'] );
		}

		return $sanitized_metadata;
	}

	public function update_image_metadata() {
		$this->verify_ajax_request( 'forvoyez_update_image_metadata' );

		$image_id     = isset( $_POST['image_id'] )
			? absint( wp_unslash( $_POST['image_id'] ) )
			: 0;
		$raw_metadata = isset( $_POST['metadata'] )
			? wp_unslash( $_POST['metadata'] )
			: array();
		$metadata     = $this->sanitize_and_validate_metadata( $raw_metadata );

		if ( !$image_id || empty( $metadata ) ) {
			wp_send_json_error(
				esc_html__( 'Invalid data', 'auto-alt-text-for-images' ),
			);
		}

		if ( !current_user_can( 'edit_post', $image_id ) ) {
			wp_send_json_error(
				esc_html__( 'Permission denied', 'auto-alt-text-for-images' ),
			);
		}

		$this->update_image_meta( $image_id, $metadata );

		wp_send_json_success(
			esc_html__(
				'Metadata updated successfully',
				'auto-alt-text-for-images',
			),
		);
	}

	private function update_image_meta( $image_id, $metadata ) {
		// update_post_meta() and wp_update_post() unslash their input: slash the
		// (already unslashed) values so backslashes are kept.
		if ( isset( $metadata['alt_text'] ) ) {
			update_post_meta(
				$image_id,
				'_wp_attachment_image_alt',
				wp_slash( $metadata['alt_text'] ),
			);
		}

		$post_data = array();
		if ( isset( $metadata['title'] ) ) {
			$post_data['post_title'] = $metadata['title'];
		}
		if ( isset( $metadata['caption'] ) ) {
			$post_data['post_excerpt'] = $metadata['caption'];
		}

		if ( !empty( $post_data ) ) {
			$post_data['ID'] = $image_id;
			wp_update_post( wp_slash( $post_data ) );
		}

		update_post_meta( $image_id, '_forvoyez_analyzed', '1' );
	}

	public function ajax_analyze_image() {
		if ( !current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Permission denied (cant upload)', 403 );
			return;
		}

		$this->verify_ajax_request( 'forvoyez_verify_ajax_request_nonce' );

		$image_id = isset( $_POST['image_id'] )
			? absint( wp_unslash( $_POST['image_id'] ) )
			: 0;

		if ( !$image_id || !wp_attachment_is_image( $image_id ) ) {
			wp_send_json_error(
				esc_html__( 'Invalid image ID', 'auto-alt-text-for-images' ),
			);
		}

		$this->ensure_user_can_edit_image_for_ajax( $image_id );
		$this->ensure_api_key_for_ajax();

		$result = $this->api_client->analyze_image( $image_id );

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'  => esc_html__(
						'Analysis successful',
						'auto-alt-text-for-images',
					),
					'metadata' => $result['metadata'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => $result['error']['message'],
					'code'    => $result['success']
						? null
						: $result['error']['code'] ??
							esc_html__(
								'unknown_error',
								'auto-alt-text-for-images',
							),
				)
			);
		}
	}

	/**
	 * AJAX handler for analyzing and updating single image in media library
	 */
	public function analyze_and_update_media_image() {
		// Verify the request
		$this->verify_ajax_request('forvoyez_verify_ajax_request_nonce');

		// Get image ID
		$image_id = isset($_POST['image_id']) ? absint($_POST['image_id']) : 0;

		if (!$image_id || !wp_attachment_is_image($image_id)) {
			wp_send_json_error([
				'message' => __('Invalid image ID', 'auto-alt-text-for-images'),
			]);
		}

		$this->ensure_user_can_edit_image_for_ajax($image_id);
		$this->ensure_api_key_for_ajax();

		// Perform analysis (analyze_image() saves the non-empty metadata)
		$result = $this->api_client->analyze_image($image_id);

		if ($result['success']) {
			// Get updated image details for UI update
			$image = get_post($image_id);
			$alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);

			// Prepare response with full metadata for UI update
			$response_data = [
				'message' => __('Analysis successful', 'auto-alt-text-for-images'),
				'metadata' => $result['metadata'],
				'updated_image' => [
					'id' => $image_id,
					'title' => $image->post_title,
					'alt_text' => $alt_text,
					'caption' => $image->post_excerpt,
				],
			];

			// Trigger action for plugins/themes to hook into
			do_action('forvoyez_image_analyzed', $image_id, $result['metadata']);

			wp_send_json_success($response_data);
		} else {
			wp_send_json_error([
				'message' => $result['error']['message'],
				'code' => isset($result['error']['code']) ? $result['error']['code'] : 'unknown_error',
			]);
		}
	}

	public function load_more_images() {
		check_ajax_referer( 'forvoyez_load_more_images_nonce', 'nonce' );

		$page     = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 21;

		// If per_page is very large (e.g., 999999), consider it as "All"
		if ( $per_page > 1000 ) {
			$per_page = -1; // This will get all images in WordPress
		}

		$images = $this->get_incomplete_images( 0, $per_page );

		ob_start();
		foreach ( $images as $image ) {
			Forvoyez_Image_Renderer::render_image_item( $image );
		}
		$html = ob_get_clean();

		$total_images = forvoyez_count_incomplete_images();

		wp_send_json_success(
			array(
				'html'             => $html,
				'count'            => count( $images ),
				'total'            => $total_images,
				'displayed_images' => count( $images ),
				'total_images'     => $total_images,
				'current_page'     => $page,
			)
		);
	}

	private function get_incomplete_images( $offset, $limit ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1, // Get all images
			'fields'         => 'ids', // Only get IDs for efficiency
		);

		$query_images  = new WP_Query( $args );
		$all_image_ids = $query_images->posts;

		$incomplete_images = array();
		foreach ( $all_image_ids as $image_id ) {
			$image = get_post( $image_id );
			if ( $this->is_image_incomplete( $image ) ) {
				$incomplete_images[] = $image;
			}
		}

		// Apply offset and limit
		$incomplete_images = array_slice( $incomplete_images, $offset, $limit );

		return $incomplete_images;
	}

	private function is_image_incomplete( $image ) {
		$alt_text          = get_post_meta( $image->ID, '_wp_attachment_image_alt', true );
		$has_default_title = preg_match(
			'/^test-image-\d+\.webp$/',
			$image->post_title,
		);
		$is_incomplete     =
			empty( $image->post_title ) ||
			$has_default_title ||
			empty( $alt_text ) ||
			empty( $image->post_excerpt );

		return $is_incomplete;
	}

	public function bulk_analyze_images() {
		$this->verify_ajax_request( 'forvoyez_bulk_analyze_images' );

		$image_ids = isset( $_POST['image_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['image_ids'] ) )
			: array();
		$image_ids = array_filter( $image_ids, 'wp_attachment_is_image' );

		if ( empty( $image_ids ) ) {
			wp_send_json_error(
				esc_html__(
					'No valid images selected',
					'auto-alt-text-for-images',
				),
			);
		}

		wp_send_json_success(
			array(
				'message'   => esc_html__(
					'Processing started',
					'auto-alt-text-for-images',
				),
				'total'     => count( $image_ids ),
				'image_ids' => $image_ids,
			)
		);
	}

	public function analyze_single_image() {
		$this->verify_ajax_request( 'forvoyez_analyze_single_image' );

		$image_id = isset( $_POST['image_id'] )
			? absint( wp_unslash( $_POST['image_id'] ) )
			: 0;

		if ( !$image_id || !wp_attachment_is_image( $image_id ) ) {
			wp_send_json_error(
				esc_html__( 'Invalid image ID', 'auto-alt-text-for-images' ),
			);
		}

		$this->ensure_user_can_edit_image_for_ajax( $image_id );
		$this->ensure_api_key_for_ajax();

		$result = $this->api_client->analyze_image( $image_id );

		wp_send_json_success( $result );
	}

	public function process_image_batch() {
		$this->verify_ajax_request( 'forvoyez_verify_ajax_request_nonce' );

		$image_ids = isset( $_POST['image_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['image_ids'] ) )
			: array();

		if ( empty( $image_ids ) ) {
			wp_send_json_error(
				esc_html__( 'No images provided', 'auto-alt-text-for-images' ),
			);
		}

		$this->ensure_api_key_for_ajax();

		$results = $this->process_images( $image_ids );

		wp_send_json_success( array( 'results' => $results ) );
	}

	private function process_images( $image_ids ) {
		$results = array();
		foreach ( $image_ids as $image_id ) {
			// Only images the current user may edit are sent to the API.
			$error = $this->get_image_access_error( $image_id );
			if ( $error ) {
				$results[] = array(
					'id'       => $image_id,
					'success'  => false,
					'message'  => $error['message'],
					'code'     => $error['code'],
					'metadata' => null,
				);
				continue;
			}

			$result    = $this->api_client->analyze_image( $image_id );
			$results[] = array(
				'id'       => $image_id,
				'success'  => $result['success'],
				'message'  => $result['success']
					? $result['message']
					: $result['error']['message'],
				'code'     => $result['success']
					? null
					: $result['error']['code'] ??
						esc_html__(
							'unknown_error',
							'auto-alt-text-for-images',
						),
				'metadata' => $result['success'] ? $result['metadata'] : null,
			);
		}

		return $results;
	}

	/**
	 * Verify AJAX request.
	 *
     * @param string $action The action name.
	 * @throws WP_Error If the request is invalid or user doesn't have permission.
	 */
	private function verify_ajax_request( $action ) {
        if ( !check_ajax_referer( $action, 'nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce' );
            exit;
        }

        if ( !current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Permission denied' );
            exit;
        }
    }

	/**
	 * Stop an AJAX request with a clear error when no API key is configured,
	 * so the ForVoyez API is never called without credentials.
	 */
	private function ensure_api_key_for_ajax() {
		if ( !$this->api_client->has_api_key() ) {
			wp_send_json_error(
				array(
					'message' => forvoyez_get_missing_api_key_message(),
					'code'    => 'missing_api_key',
				)
			);
		}
	}

	/**
	 * Why an attachment cannot be analyzed by the current user, if it cannot.
	 *
	 * The AJAX actions only require `upload_files`, so each attachment must
	 * also be an image the user may edit (an Author cannot overwrite the
	 * metadata of other users' images).
	 *
	 * @param int $image_id The attachment ID.
	 * @return array{code: string, message: string}|null Null when allowed.
	 */
	private function get_image_access_error( $image_id ) {
		if ( !$image_id || !wp_attachment_is_image( $image_id ) ) {
			return array(
				'code'    => 'invalid_image',
				'message' => esc_html__( 'Invalid image ID', 'auto-alt-text-for-images' ),
			);
		}

		if ( !current_user_can( 'edit_post', $image_id ) ) {
			return array(
				'code'    => 'permission_denied',
				'message' => esc_html__( 'Permission denied', 'auto-alt-text-for-images' ),
			);
		}

		return null;
	}

	/**
	 * Stop an AJAX request when the current user may not edit the image.
	 *
	 * @param int $image_id The attachment ID.
	 */
	private function ensure_user_can_edit_image_for_ajax( $image_id ) {
		if ( !current_user_can( 'edit_post', $image_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied', 'auto-alt-text-for-images' ),
					'code'    => 'permission_denied',
				)
			);
		}
	}

    /**
     * Analyze image on upload.
     *
     * @param int $attachment_id The ID of the uploaded attachment.
     */
    public function analyze_image_on_upload( $attachment_id ) {
        // Check if the uploaded file is an image
        if ( !wp_attachment_is_image( $attachment_id ) ) {
            return;
        }

        // Never call the API without an API key (an admin notice explains it)
        if ( !$this->api_client->has_api_key() ) {
            return;
        }

        // Analyze the image; analyze_image() saves the non-empty metadata
        $this->api_client->analyze_image( $attachment_id );
    }

	/**
	 * Schedule image analysis on upload.
	 *
	 * @param int $attachment_id The ID of the uploaded attachment.
	 */
	public function schedule_image_analysis($attachment_id) {
	    // Check if automatic analysis is enabled (the option may hold 'false')
	    if (!forvoyez_is_auto_analyze_enabled()) {
	        return;
	    }

	    // Check if the uploaded file is an image
	    if (!wp_attachment_is_image($attachment_id)) {
	        return;
	    }

	    // Never schedule an API call without an API key (an admin notice explains it)
	    if (!$this->api_client->has_api_key()) {
	        return;
	    }

	    // Schedule the analysis to run after a short delay
	    wp_schedule_single_event(time() + 10, 'forvoyez_analyze_single_image', array($attachment_id));
	}

	/**
	 * Analyze a single image (for cron job).
	 *
	 * @param int $attachment_id The ID of the image to analyze.
	 */
	public function cron_analyze_single_image($attachment_id) {
	    // Automatic analysis may have been turned off since the event was scheduled
	    if (!forvoyez_is_auto_analyze_enabled()) {
	        return;
	    }

	    // The key may have been removed since the event was scheduled
	    if (!$this->api_client->has_api_key()) {
	        return;
	    }

	    // Analyze the image; analyze_image() saves the non-empty metadata
	    $this->api_client->analyze_image((int) $attachment_id);
	}

	/**
	 * AJAX handler for analyzing and updating multiple images in the Media Library
	 * Add this to class-forvoyez-image-processor.php
	 */
	public function bulk_analyze_media_images() {
		// Verify the request
		$this->verify_ajax_request('forvoyez_verify_ajax_request_nonce');

		// Get image IDs
		$image_ids = isset($_POST['image_ids']) ? array_map('absint', wp_unslash($_POST['image_ids'])) : [];

		if (empty($image_ids)) {
			wp_send_json_error([
				'message' => __('No images provided', 'auto-alt-text-for-images'),
			]);
		}

		$this->ensure_api_key_for_ajax();

		// Process images in batches
		$batch_size = 5;
		$results = [];
		$success_count = 0;
		$error_count = 0;

		// Split image IDs into batches to avoid timeouts
		$batches = array_chunk($image_ids, $batch_size);

		foreach ($batches as $batch) {
			$batch_results = $this->process_images($batch);

			foreach ($batch_results as $result) {
				if ($result['success']) {
					++$success_count;
				} else {
					++$error_count;
				}
				$results[] = $result;
			}
		}

		// Get updated credit information
		$token_info = forvoyez_get_token_info();
		$credits = isset($token_info['success']) && $token_info['success'] ? $token_info['user']['credits'] : null;

		wp_send_json_success([
			'results' => $results,
			'success_count' => $success_count,
			'error_count' => $error_count,
			'credits' => $credits,
		]);
	}
}
