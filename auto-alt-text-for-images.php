<?php
/**
 * Auto Alt Text for Images
 *
 * This plugin automatically generates alt text and SEO metadata for images using the ForVoyez API.
 *
 * @package     ForVoyez
 * @author      ForVoyez
 * @copyright   2024 ForVoyez
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Auto Alt Text for Images
 * Plugin URI:  https://doc.forvoyez.com/wordpress-plugin
 * Description: Automatically generate alt text and SEO metadata for images using ForVoyez API.
 * Version:     1.1.44
 * Requires at least: 5.6
 * Requires PHP: 8.0
 * Author:      ForVoyez
 * Author URI:  https://forvoyez.com
 * Text Domain: auto-alt-text-for-images
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( !defined( 'ABSPATH' ) ) {
	exit( 'Direct access to this file is not allowed.' );
}

// Define plugin constants
define( 'FORVOYEZ_VERSION', '1.1.44' );
define( 'FORVOYEZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORVOYEZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FORVOYEZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files
$forvoyez_required_files = array(
	'includes/forvoyez-helpers.php',
	'includes/class-forvoyez-admin.php',
	'includes/class-forvoyez-api-manager.php',
	'includes/class-forvoyez-image-processor.php',
	'includes/class-forvoyez-settings.php',
	'includes/class-forvoyez-image-renderer.php',
);

foreach ( $forvoyez_required_files as $forvoyez_file ) {
	require_once FORVOYEZ_PLUGIN_DIR . $forvoyez_file;
}

/**
 * Initialize the plugin components.
 *
 * This function is hooked into the 'plugins_loaded' action to ensure
 * that it's only executed after all plugins have been loaded.
 *
 * @since 1.0.0
 */
function forvoyez_init() {
	$settings        = new Forvoyez_Settings();
	$api_manager     = new Forvoyez_API_Manager( forvoyez_get_api_key(), forvoyez_get_language(), forvoyez_get_context() );
	$image_processor = new Forvoyez_Image_Processor( $api_manager );
	$admin           = new Forvoyez_Admin( $api_manager, $settings, $image_processor );

	$settings->init();
	$api_manager->init();
	$image_processor->init();
	$admin->init();

	add_action( 'add_attachment', 'forvoyez_clear_image_cache' );
	add_action( 'edit_attachment', 'forvoyez_clear_image_cache' );
	add_action( 'delete_attachment', 'forvoyez_clear_image_cache' );
	add_action( 'admin_enqueue_scripts', 'forvoyez_enqueue_media_scripts' );
	add_filter( 'attachment_fields_to_edit', 'forvoyez_add_analyze_button', 10, 2 );
}
add_action( 'plugins_loaded', 'forvoyez_init' );

/**
 * Clear the image cache.
 *
 * This function clears the cache for image data.
 *
 * @since 1.0.0
 */
function forvoyez_clear_image_cache() {
	wp_cache_delete( 'forvoyez_image_ids_all' );
	wp_cache_delete( 'forvoyez_image_ids_missing_alt' );
	wp_cache_delete( 'forvoyez_image_ids_missing_all' );
	wp_cache_delete( 'forvoyez_incomplete_images_count' );
}

/**
 * Load the plugin text domain for translation.
 * @return void
 */
function forvoyez_load_textdomain() {
	load_plugin_textdomain(
		'auto-alt-text-for-images',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages/',
	);
}
add_action( 'plugins_loaded', 'forvoyez_load_textdomain' );

/**
 * Activate the plugin.
 *
 * Sets up necessary options when the plugin is activated.
 *
 * @since 1.0.0
 */
function forvoyez_activate() {
	update_option( 'forvoyez_plugin_activated', true );
	update_option( 'forvoyez_plugin_version', FORVOYEZ_VERSION );
	update_option( 'forvoyez_flush_rewrite_rules', true );
}
register_activation_hook( __FILE__, 'forvoyez_activate' );

/**
 * Deactivate the plugin.
 *
 * Cleans up options when the plugin is deactivated.
 *
 * @since 1.0.0
 */
function forvoyez_deactivate() {
	delete_option( 'forvoyez_plugin_activated' );
	delete_option( 'forvoyez_flush_rewrite_rules' );
	// Optionally, keep the version number for future reference
	// delete_option('forvoyez_plugin_version');
}
register_deactivation_hook( __FILE__, 'forvoyez_deactivate' );

/**
 * Flush rewrite rules if necessary.
 *
 * This function checks if the rewrite rules need to be flushed
 * and does so if required. It's hooked to the 'init' action.
 *
 * @since 1.0.0
 */
function forvoyez_maybe_flush_rewrite_rules() {
	if ( get_option( 'forvoyez_flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
		delete_option( 'forvoyez_flush_rewrite_rules' );
	}
}
add_action( 'init', 'forvoyez_maybe_flush_rewrite_rules' );

/**
 * Enqueue the media scripts.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function forvoyez_enqueue_media_scripts($hook) {
	// Include media scripts on all admin pages that might include the media modal
	$media_pages = array('upload.php', 'post.php', 'post-new.php', 'page.php', 'page-new.php');
	$is_media_page = in_array($hook, $media_pages);

	if ($is_media_page || wp_script_is('media-editor')) {
		// Enqueue script
		forvoyez_register_i18n_script();
		wp_enqueue_script(
			'forvoyez-media-script',
			plugin_dir_url(__FILE__) . 'assets/js/media-script.js',
			array('jquery', 'wp-backbone', 'media-editor', 'media-views', 'forvoyez-i18n'),
			FORVOYEZ_VERSION,
			true
		);

		// Localize script with all necessary data
		wp_localize_script(
			'forvoyez-media-script',
			'forvoyezData',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'verifyAjaxRequestNonce' => wp_create_nonce('forvoyez_verify_ajax_request_nonce'),
				'analyzeNonce' => wp_create_nonce('forvoyez_analyze_media_image_nonce'),
				'mediaPage' => $is_media_page,
			)
		);

		// Add inline CSS for media library integration
		wp_add_inline_style('wp-admin', '
            .forvoyez-button-container {
                margin-top: 8px;
            }
            .forvoyez-status {
                margin-top: 5px;
                font-size: 12px;
            }
            .forvoyez-status.success {
                color: #4CAF50;
            }
            .forvoyez-status.error {
                color: #F44336;
            }
            .forvoyez-status.loading {
                color: #2196F3;
            }
            @keyframes forvoyez-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .forvoyez-spinner {
                display: inline-block;
                width: 12px;
                height: 12px;
                border: 2px solid rgba(0, 0, 0, 0.1);
                border-left-color: #09f;
                border-radius: 50%;
                animation: forvoyez-spin 1s linear infinite;
                margin-right: 5px;
                vertical-align: middle;
            }
            .forvoyez-credits-widget {
                box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
            }
            .forvoyez-analyze-bulk-wrapper {
                margin: 10px 0;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #e5e5e5;
                border-radius: 4px;
                display: none;
            }
            .wp_attachment_details .compat-field-_forvoyez_analysis td.field {
                width: 100%;
            }
            @keyframes forvoyez-pulse {
                0% { opacity: 0.6; }
                50% { opacity: 1; }
                100% { opacity: 0.6; }
            }
            .forvoyez-animate-pulse {
                animation: forvoyez-pulse 1.5s infinite ease-in-out;
            }
        ');

		// Support for the media modal
		if (wp_script_is('media-views')) {
			wp_add_inline_script('forvoyez-media-script', '
                jQuery(document).ready(function($) {
                    // Add ForVoyez button to the media modal attachment details
                    if (wp.media && wp.media.frame) {
                        // Listen for attachment selection changes
                        wp.media.frame.on("select", function() {
                            // Short delay to ensure the sidebar is rendered
                            setTimeout(function() {
                                addForVoyezButtonToMediaModal();
                            }, 100);
                        });
                        
                        // Listen for media modal opening
                        wp.media.frame.on("open", function() {
                            // Short delay to ensure the sidebar is rendered
                            setTimeout(function() {
                                addForVoyezButtonToMediaModal();
                            }, 100);
                        });
                        
                        // Add button when attachment is selected
                        function addForVoyezButtonToMediaModal() {
                            // Check if the button already exists
                            if ($(".media-sidebar .forvoyez-analyze-button").length === 0) {
                                // Get the attachment
                                var selection = wp.media.frame.state().get("selection");
                                var attachment = selection.first();
                                
                                if (attachment) {
                                    // Get token info for credits display
                                    $.ajax({
                                        url: forvoyezData.ajaxurl,
                                        type: "POST",
                                        data: {
                                            action: "forvoyez_get_credits",
                                            nonce: forvoyezData.verifyAjaxRequestNonce
                                        },
                                        success: function(response) {
                                            var credits = "?";
                                            var creditClass = "credits-normal";
                                            
                                            if (response.success) {
                                                credits = response.data.credits;
                                                
                                                if (credits <= 5) {
                                                    creditClass = "credits-danger";
                                                } else if (credits <= 20) {
                                                    creditClass = "credits-warning";
                                                }
                                            }
                                            
                                            // Create the analyze button and add it to the sidebar.
                                            // Translations are written as text (assets/js/i18n.js).
                                            var t = window.forvoyezI18n.t;
                                            var $header = $("<div>", { style: "display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;" }).append(
                                                $("<h2>", { style: "margin: 0; font-size: 12px; font-weight: bold;", text: t("ForVoyez Analysis") }),
                                                $("<div>", { style: "font-size: 11px;" }).append(
                                                    document.createTextNode(t("Credits:") + " "),
                                                    $("<span>", { "class": "forvoyez-credit-count " + creditClass, text: String(credits) })
                                                )
                                            );
                                            var $section = $("<div>", { "class": "forvoyez-analyze-container attachment-compat", style: "padding: 0 16px 16px 16px;" }).append(
                                                $header,
                                                $("<button>", { type: "button", "class": "button forvoyez-analyze-button", "data-image-id": attachment.id, text: t("Analyze with ForVoyez") }),
                                                $("<div>", { "class": "forvoyez-status", style: "margin-top: 5px;" })
                                            );

                                            $(".media-sidebar").append($section);
                                        }
                                    });
                                }
                            }
                        }
                    }
                });
            ');
		}
	}
}

/**
 * Add bulk action to Media Library
 */
function forvoyez_register_bulk_actions($bulk_actions) {
	$bulk_actions['forvoyez_analyze_images'] = __('Analyze with ForVoyez', 'auto-alt-text-for-images');
	return $bulk_actions;
}
add_filter('bulk_actions-upload', 'forvoyez_register_bulk_actions');

/**
 * Add to auto-alt-text-for-images.php - Handle Bulk Action
 */
function forvoyez_handle_bulk_action($redirect_to, $doaction, $post_ids) {
	if ($doaction !== 'forvoyez_analyze_images') {
		return $redirect_to;
	}

	// Only process images
	$image_ids = array_filter($post_ids, 'wp_attachment_is_image');

	// Add a nonce for security
	$nonce = wp_create_nonce('forvoyez_bulk_analyze_nonce');

	// Add query args
	$redirect_to = add_query_arg([
		'forvoyez_bulk_analyze' => count($image_ids),
		'forvoyez_bulk_nonce' => $nonce,
		'forvoyez_image_ids' => implode(',', $image_ids),
	], $redirect_to);

	return $redirect_to;
}
add_filter('handle_bulk_actions-upload', 'forvoyez_handle_bulk_action', 10, 3);

/**
 * Status of one metadata field in the attachment details: icon and label.
 *
 * @param bool   $is_set  Whether the field is filled.
 * @param string $label   Name of the field.
 * @param string $has     Tooltip when the field is filled.
 * @param string $missing Tooltip when the field is empty.
 *
 * @return string Escaped HTML.
 */
function forvoyez_metadata_status_html($is_set, $label, $has, $missing) {
	return sprintf(
		'<span class="dashicons %1$s" style="color: %2$s;" title="%3$s"></span> <span class="status-label">%4$s</span>',
		$is_set ? 'dashicons-yes-alt' : 'dashicons-warning',
		$is_set ? '#4CAF50' : '#FF9800',
		esc_attr($is_set ? $has : $missing),
		esc_html($label),
	);
}

/**
 * Add an "Analyze with ForVoyez" button to the media library with integrated credit counter.
 *
 * @param $form_fields
 * @param $post
 *
 * @return mixed
 */
function forvoyez_add_analyze_button($form_fields, $post) {
	if (wp_attachment_is_image($post->ID)) {
		// Get metadata status
		$has_alt_text = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
		$has_title = !empty($post->post_title) && $post->post_title !== wp_basename($post->guid);
		$has_caption = !empty($post->post_excerpt);

		// Get credit info
		$token_info = forvoyez_get_token_info();
		$credits = $token_info['user']['credits'] ?? '?';

		// Credit status class
		$credit_class = 'credits-normal';
		if ($credits !== '?') {
			if ($credits <= 5) {
				$credit_class = 'credits-danger';
			} elseif ($credits <= 20) {
				$credit_class = 'credits-warning';
			}
		}

		// Generate status indicators with text labels
		$alt_status = forvoyez_metadata_status_html(
			$has_alt_text,
			__('Alt text', 'auto-alt-text-for-images'),
			__('Has alt text', 'auto-alt-text-for-images'),
			__('Missing alt text', 'auto-alt-text-for-images'),
		);
		$title_status = forvoyez_metadata_status_html(
			$has_title,
			__('Title', 'auto-alt-text-for-images'),
			__('Has title', 'auto-alt-text-for-images'),
			__('Missing title', 'auto-alt-text-for-images'),
		);
		$caption_status = forvoyez_metadata_status_html(
			$has_caption,
			__('Caption', 'auto-alt-text-for-images'),
			__('Has caption', 'auto-alt-text-for-images'),
			__('Missing caption', 'auto-alt-text-for-images'),
		);

		$form_fields['forvoyez_analyze'] = array(
			'label' => '',
			'input' => 'html',
			'html' => '
                <style>
                    .forvoyez-analyze-button {
                        background-color: #007cba;
                        color: #fff;
                        border: none;
                        padding: 5px 10px;
                        border-radius: 3px;
                        cursor: pointer;
                        transition: background-color 0.2s;
                    }
                    .forvoyez-analyze-button:hover {
                        background-color: #005a87;
                    }
                    .forvoyez-analyze-button:disabled {
                        background-color: #cccccc;
                        cursor: not-allowed;
                    }
                    .forvoyez-status {
                        margin-top: 5px;
                        font-size: 12px;
                    }
                    .forvoyez-status.success {
                        color: #4CAF50;
                    }
                    .forvoyez-status.error {
                        color: #F44336;
                    }
                    .forvoyez-status.loading {
                        color: #2196F3;
                    }
                    .forvoyez-metadata-status {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 12px;
                        align-items: center;
                        margin-bottom: 8px;
                    }
                    .forvoyez-metadata-item {
                        display: inline-flex;
                        align-items: center;
                        gap: 4px;
                    }
                    .forvoyez-credit-count {
                        padding: 1px 6px;
                        border-radius: 10px;
                        font-size: 11px;
                        font-weight: bold;
                        margin-left: 8px;
                    }
                    .credits-normal {
                        background-color: #d4edda;
                        color: #155724;
                    }
                    .credits-warning {
                        background-color: #fff3cd;
                        color: #856404;
                    }
                    .credits-danger {
                        background-color: #f8d7da;
                        color: #721c24;
                    }
                    .status-label {
                        font-size: 11px;
                        color: #666;
                    }
                    .forvoyez-description {
                        font-size: 12px;
                        color: #666;
                        margin: 8px 0;
                        line-height: 1.4;
                    }
                    @keyframes forvoyez-spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                    .forvoyez-spinner {
                        display: inline-block;
                        width: 12px;
                        height: 12px;
                        border: 2px solid rgba(0, 0, 0, 0.1);
                        border-left-color: #09f;
                        border-radius: 50%;
                        animation: forvoyez-spin 1s linear infinite;
                        margin-right: 5px;
                        vertical-align: middle;
                    }
                    @keyframes forvoyez-pulse {
                        0% { opacity: 0.6; }
                        50% { opacity: 1; }
                        100% { opacity: 0.6; }
                    }
                    .forvoyez-animate-pulse {
                        animation: forvoyez-pulse 1.5s infinite ease-in-out;
                    }
                    .forvoyez-row {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 8px;
                    }
                </style>
                <div class="forvoyez-button-container">
                    <div class="forvoyez-row">
                        <h4 style="margin: 0 0 8px 0;">' . esc_html__('Image Metadata Status:', 'auto-alt-text-for-images') . '</h4>
                        <div class="forvoyez-metadata-item">
                            ' . esc_html__('ForVoyez Credits:', 'auto-alt-text-for-images') . ' <span class="forvoyez-credit-count ' . esc_attr($credit_class) . '">' . esc_html($credits) . '</span>
                        </div>
                    </div>
                    <div class="forvoyez-metadata-status" data-image-id="' . esc_attr($post->ID) . '">
                        <div class="forvoyez-metadata-item alt-status" title="' . esc_attr__('Alt Text Status', 'auto-alt-text-for-images') . '">' . $alt_status . '</div>
                        <div class="forvoyez-metadata-item title-status" title="' . esc_attr__('Title Status', 'auto-alt-text-for-images') . '">' . $title_status . '</div>
                        <div class="forvoyez-metadata-item caption-status" title="' . esc_attr__('Caption Status', 'auto-alt-text-for-images') . '">' . $caption_status . '</div>
                    </div>
                    <div class="forvoyez-description">
                        ' . esc_html__('This tool uses AI to analyze your image and generate optimized metadata like alt text, title, and caption. Click the button below to automatically generate SEO-friendly metadata for this image.', 'auto-alt-text-for-images') . '
                    </div>
                    <button type="button" class="button forvoyez-analyze-button" data-image-id="' . esc_attr($post->ID) . '">' . esc_html__('Analyze with ForVoyez', 'auto-alt-text-for-images') . '</button>
                    <div class="forvoyez-status"></div>
                </div>
            ',
		);
	}
	return $form_fields;
}

/**
 * Add to auto-alt-text-for-images.php - Add Bulk Analysis Notice and UI
 */
function forvoyez_bulk_analysis_notice() {
	if (!isset($_GET['forvoyez_bulk_analyze']) || !isset($_GET['forvoyez_bulk_nonce'])) {
		return;
	}

	$count = intval($_GET['forvoyez_bulk_analyze']);
	$nonce = sanitize_text_field(wp_unslash($_GET['forvoyez_bulk_nonce']));

	// Verify nonce
	if (!wp_verify_nonce($nonce, 'forvoyez_bulk_analyze_nonce')) {
		return;
	}

	// Get image IDs
	$image_ids = isset($_GET['forvoyez_image_ids']) ?
		explode(',', sanitize_text_field(wp_unslash($_GET['forvoyez_image_ids']))) :
		[];

	if (empty($image_ids)) {
		return;
	}

	// Get token info for credits display
	$token_info = forvoyez_get_token_info();
	$credits = $token_info['user']['credits'] ?? '?';
	$credit_class = 'credits-normal';

	if ($credits !== '?') {
		if ($credits <= 5) {
			$credit_class = 'credits-danger';
		} elseif ($credits <= 20) {
			$credit_class = 'credits-warning';
		}
	}

	// Warning if not enough credits
	$warning = '';
	if ($credits !== '?' && $credits < $count) {
		$warning = sprintf(/* translators: 1: number of credits needed, 2: number of credits available */ __('Warning: This operation requires %1$d credits, but you only have %2$d credits available.', 'auto-alt-text-for-images'), $count, $credits);
	}

	// Create the notice
	?>
    <div class="notice notice-info">
        <p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of images */
					_n(
						'ForVoyez: Ready to analyze %d image.',
						'ForVoyez: Ready to analyze %d images.',
						$count,
						'auto-alt-text-for-images'
					),
					$count
				)
			);
			?>
            <span class="forvoyez-credits-info" style="margin-left: 10px;">
                <?php esc_html_e('ForVoyez Credits:', 'auto-alt-text-for-images'); ?> <span class="forvoyez-credit-count <?php echo esc_attr($credit_class); ?>"><?php echo esc_html($credits); ?></span>
            </span>
        </p>
		<?php if ($warning) : ?><div class="notice-warning" style="padding: 8px; margin-bottom: 10px; border-left: 4px solid #ffb900;"><?php echo esc_html($warning); ?></div><?php endif; ?>
        <div class="forvoyez-bulk-actions" style="margin: 10px 0;">
            <button id="forvoyez-start-bulk" class="button button-primary"
                    data-nonce="<?php echo esc_attr(wp_create_nonce('forvoyez_verify_ajax_request_nonce')); ?>"
                    data-ids="<?php echo esc_attr(implode(',', $image_ids)); ?>">
				<?php esc_html_e('Start Analysis', 'auto-alt-text-for-images'); ?>
            </button>
            <a href="<?php echo esc_url(remove_query_arg(['forvoyez_bulk_analyze', 'forvoyez_bulk_nonce', 'forvoyez_image_ids'])); ?>" class="button">
				<?php esc_html_e('Cancel', 'auto-alt-text-for-images'); ?>
            </a>
        </div>
        <div id="forvoyez-bulk-progress" style="display: none; margin: 10px 0;">
            <div style="margin-bottom: 5px;">
                <span id="forvoyez-progress-count">0 / <?php echo (int) $count; ?></span>
                <span id="forvoyez-progress-status" style="margin-left: 10px;"></span>
            </div>
            <div style="height: 20px; background: #f1f1f1; border-radius: 4px; overflow: hidden;">
                <div id="forvoyez-progress-bar" style="width: 0%; height: 100%; background: #007cba;"></div>
            </div>
        </div>
        <div id="forvoyez-bulk-results" style="display: none; margin-top: 10px;">
            <div id="forvoyez-results-summary"></div>
            <button id="forvoyez-close-notice" class="button" style="margin-top: 10px;">
				<?php esc_html_e('Dismiss', 'auto-alt-text-for-images'); ?>
            </button>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Style definitions
            $('<style>')
                .text(`
                .forvoyez-credit-count {
                    display: inline-block;
                    padding: 1px 6px;
                    border-radius: 10px;
                    font-size: 11px;
                    font-weight: bold;
                }
                .credits-normal { background: #d4edda; color: #155724; }
                .credits-warning { background: #fff3cd; color: #856404; }
                .credits-danger { background: #f8d7da; color: #721c24; }
            `)
                .appendTo('head');

            // Handle bulk analysis
            $('#forvoyez-start-bulk').on('click', function() {
                const $button = $(this);
                // Read the raw attribute: jQuery .data() turns a single id
                // such as "11" into the Number 11, which has no split().
                const imageIds = String($button.attr('data-ids') || '').split(',').filter(Boolean);
                const nonce = $button.data('nonce');

                // Disable button and show progress
                $button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'auto-alt-text-for-images')); ?>');
                $('#forvoyez-bulk-progress').show();
                $('.forvoyez-bulk-actions').hide();

                // Process images in batches
                const batchSize = 5;
                let processedCount = 0;
                let successCount = 0;
                let errorCount = 0;

                function processBatch(startIndex) {
                    const endIndex = Math.min(startIndex + batchSize, imageIds.length);
                    const batch = imageIds.slice(startIndex, endIndex);

                    if (batch.length === 0) {
                        // All done
                        $('#forvoyez-progress-status').text('<?php echo esc_js(__('Complete!', 'auto-alt-text-for-images')); ?>');
                        $('#forvoyez-bulk-results').show();
                        // String literals, not a template literal: esc_js() does not
                        // protect ${ } or backticks in a translation.
                        $('#forvoyez-results-summary').html(
                            '<div class="notice-success" style="padding: 8px; margin-bottom: 10px; border-left: 4px solid #46b450;">' +
                            <?php echo wp_json_encode(esc_html__('Analysis completed!', 'auto-alt-text-for-images')); ?> + ' ' +
                            successCount + ' ' + <?php echo wp_json_encode(esc_html__('successful', 'auto-alt-text-for-images')); ?> + ', ' +
                            errorCount + ' ' + <?php echo wp_json_encode(esc_html__('failed', 'auto-alt-text-for-images')); ?> + '.' +
                            '</div>'
                        );

                        // Update credit count
                        if (window.updatedCredits) {
                            $('.forvoyez-credit-count').text(window.updatedCredits);
                        }

                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'forvoyez_process_image_batch',
                            image_ids: batch,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Update counts
                                response.data.results.forEach(function(result) {
                                    if (result.success) {
                                        successCount++;
                                    } else {
                                        errorCount++;
                                    }
                                });

                                // Store updated credits if available
                                if (response.data.credits) {
                                    window.updatedCredits = response.data.credits;
                                }
                            } else {
                                // Count all as errors
                                errorCount += batch.length;
                            }

                            // Update progress
                            processedCount += batch.length;
                            const percent = Math.round((processedCount / imageIds.length) * 100);
                            $('#forvoyez-progress-bar').css('width', percent + '%');
                            $('#forvoyez-progress-count').text(processedCount + ' / ' + imageIds.length);
                            $('#forvoyez-progress-status').text(
                                successCount + ' <?php echo esc_js(__('successful', 'auto-alt-text-for-images')); ?>, ' +
                                errorCount + ' <?php echo esc_js(__('failed', 'auto-alt-text-for-images')); ?>'
                            );

                            // Process next batch
                            processBatch(endIndex);
                        },
                        error: function() {
                            // Count as errors
                            errorCount += batch.length;

                            // Update progress
                            processedCount += batch.length;
                            const percent = Math.round((processedCount / imageIds.length) * 100);
                            $('#forvoyez-progress-bar').css('width', percent + '%');
                            $('#forvoyez-progress-count').text(processedCount + ' / ' + imageIds.length);
                            $('#forvoyez-progress-status').text(
                                successCount + ' <?php echo esc_js(__('successful', 'auto-alt-text-for-images')); ?>, ' +
                                errorCount + ' <?php echo esc_js(__('failed', 'auto-alt-text-for-images')); ?>'
                            );

                            // Process next batch
                            processBatch(endIndex);
                        }
                    });
                }

                // Start processing
                processBatch(0);
            });

            // Close notice
            $('#forvoyez-close-notice').on('click', function() {
                $(this).closest('.notice').fadeOut();
                window.location.href = '<?php echo esc_url(remove_query_arg(['forvoyez_bulk_analyze', 'forvoyez_bulk_nonce', 'forvoyez_image_ids'])); ?>';
            });
        });
    </script>
	<?php
}
add_action('admin_notices', 'forvoyez_bulk_analysis_notice');