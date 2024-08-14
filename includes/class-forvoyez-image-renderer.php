<?php
/**
 * Class Forvoyez_Image_Renderer
 *
 * Responsible for rendering image items in the admin interface.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

defined('ABSPATH') || exit('Direct access to this file is not allowed.');

class Forvoyez_Image_Renderer {

    /**
     * Render an individual image item.
     *
     * @param WP_Post $image The image post object.
     * @return void
     */
    public static function render_image_item($image) {
        // Fetch image data
        $image_url = wp_get_attachment_url($image->ID);
        $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);

        // Check if all metadata is complete
        $all_complete = self::is_metadata_complete($image_alt, $image->post_title, $image->post_excerpt);
        $disabled_class = $all_complete ? 'opacity-50' : '';

        // Start rendering
        ?>
        <li class="col-span-1 flex flex-col divide-y divide-gray-200 rounded-lg bg-white text-center shadow <?php echo esc_attr($disabled_class); ?>" data-image-id="<?php echo esc_attr($image->ID); ?>">
            <div class="flex flex-1 flex-col p-2">
                <div class="relative w-full h-48">
                    <?php self::render_image($image_url, $image_alt); ?>
                    <?php self::render_details_view($image, $image_alt); ?>
                    <?php self::render_checkbox($image->ID); ?>
                    <?php self::render_metadata_icons($image_alt, $image->post_title, $image->post_excerpt, $all_complete); ?>
                    <?php self::render_loader(); ?>
                </div>
            </div>
            <?php self::render_action_buttons(); ?>
        </li>
        <?php
    }

    /**
     * Check if all metadata for an image is complete.
     *
     * @param string $image_alt The image alt text.
     * @param string $post_title The image title.
     * @param string $post_excerpt The image caption.
     * @return bool True if all metadata is complete, false otherwise.
     */
    private static function is_metadata_complete($image_alt, $post_title, $post_excerpt) {
        return !empty($image_alt) && !empty($post_title) && !empty($post_excerpt);
    }

    /**
     * Render the image.
     *
     * @param string $image_url The URL of the image.
     * @param string $image_alt The alt text for the image.
     * @return void
     */
    private static function render_image($image_url, $image_alt) {
        ?>
        <img class="w-full h-full object-cover rounded-lg" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
        <?php
    }

    /**
     * Render the details view for an image.
     *
     * @param WP_Post $image The image post object.
     * @param string $image_alt The image alt text.
     * @return void
     */
    private static function render_details_view($image, $image_alt) {
        ?>
        <div class="hidden absolute inset-0 bg-white p-2 pt-8 overflow-y-auto details-view">
            <p class="text-sm text-gray-500 text-left">
                <strong><?php esc_html_e('Title:', 'forvoyez-auto-alt-text-for-images'); ?></strong> <span class="title-content"><?php echo esc_html($image->post_title ?: __('Not set', 'forvoyez-auto-alt-text-for-images')); ?></span>
            </p>
            <p class="text-sm text-gray-500 text-left">
                <strong><?php esc_html_e('Alt Text:', 'forvoyez-auto-alt-text-for-images'); ?></strong> <span class="alt-content"><?php echo esc_html($image_alt ?: __('Not set', 'forvoyez-auto-alt-text-for-images')); ?></span>
            </p>
            <p class="text-sm text-gray-500 text-left">
                <strong><?php esc_html_e('Caption:', 'forvoyez-auto-alt-text-for-images'); ?></strong> <span class="caption-content"><?php echo esc_html($image->post_excerpt ?: __('Not set', 'forvoyez-auto-alt-text-for-images')); ?></span>
            </p>
        </div>
        <?php
    }

    /**
     * Render the checkbox for an image.
     *
     * @param int $image_id The ID of the image.
     * @return void
     */
    private static function render_checkbox($image_id) {
        ?>
        <input type="checkbox" class="absolute top-2 left-2 form-checkbox h-5 w-5 text-blue-600 rounded transition duration-150 ease-in-out" value="<?php echo esc_attr($image_id); ?>" data-forvoyez-image-checkbox>
        <?php
    }

    /**
     * Render the metadata icons for an image.
     *
     * @param string $image_alt The image alt text.
     * @param string $post_title The image title.
     * @param string $post_excerpt The image caption.
     * @param bool $all_complete Whether all metadata is complete.
     * @return void
     */
    public static function render_metadata_icons($image_alt, $post_title, $post_excerpt, $all_complete) {
        ?>
        <div class="absolute top-0 right-0 flex space-x-1 bg-white p-2 rounded shadow-lg metadata-icons">
            <?php self::render_metadata_icon('alt', $image_alt, 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'); ?>
            <?php self::render_metadata_icon('title', $post_title, 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z'); ?>
            <?php self::render_metadata_icon('caption', $post_excerpt, 'M4 6h16M4 12h16M4 18h7'); ?>
            <span class="bg-green-500 text-white rounded-full p-1 <?php echo $all_complete ? '' : 'hidden'; ?> all-complete" title="<?php esc_attr_e('All Complete', 'forvoyez-auto-alt-text-for-images'); ?>">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </span>
        </div>
        <?php
    }

    /**
     * Render a single metadata icon.
     *
     * @param string $type The type of metadata (alt, title, or caption).
     * @param string $value The value of the metadata.
     * @param string $path The SVG path for the icon.
     * @return void
     */
    private static function render_metadata_icon($type, $value, $path) {
        ?>
        <span class="bg-red-500 text-white rounded-full p-1 <?php echo empty($value) ? '' : 'hidden'; ?> <?php echo $type; ?>-missing" title="<?php echo esc_attr(sprintf(__('Missing %s', 'forvoyez-auto-alt-text-for-images'), ucfirst($type))); ?>">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $path; ?>" />
            </svg>
        </span>
        <?php
    }

    /**
     * Render the loader for an image.
     *
     * @return void
     */
    private static function render_loader() {
        ?>
        <div class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 hidden loader">
            <svg class="animate-spin h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
        <?php
    }

    /**
     * Render the action buttons for an image.
     *
     * @return void
     */
    public static function render_action_buttons() {
        ?>
        <div>
            <div class="-mt-px flex divide-x divide-gray-200">
                <?php self::render_action_button('analyze',  "Analyse", 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'); ?>
                <?php self::render_action_button('see-more', "Details", 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single action button.
     *
     * @param string $type The type of button (analyze or see-more).
     * @param string $label The button label.
     * @param string $icon_path The SVG path for the button icon.
     * @return void
     */
    private static function render_action_button($type, $label, $icon_path) {
        ?>
        <div class="flex w-0 flex-1">
            <button class="<?php echo $type; ?>-button relative -mr-px inline-flex w-0 flex-1 items-center justify-center gap-x-3 rounded-bl-lg border border-transparent py-4 text-xs text-gray-900 hover:bg-gray-50 hover:border-gray-200">
                <span><?php echo esc_html_e($label, 'forvoyez-auto-alt-text-for-images') ?></span>
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $icon_path; ?>" />
                </svg>
            </button>
        </div>
        <?php
    }
}