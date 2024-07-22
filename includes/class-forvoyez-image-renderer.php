<?php
defined('ABSPATH') || exit;

class Forvoyez_Image_Renderer
{
    public static function render_image_item($image)
    {
        $image_url = wp_get_attachment_url($image->ID);
        $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $is_analyzed = get_post_meta($image->ID, '_forvoyez_analyzed', true);
        $disabled_class = $is_analyzed ? 'opacity-50' : '';
        $all_complete = !empty($image_alt) && !empty($image->post_title) && !empty($image->post_excerpt);
        ?>
        <div class="forvoyez-image-item <?php echo $disabled_class; ?> relative p-2 border rounded-md" data-image-id="<?php echo esc_attr($image->ID); ?>">
            <input type="checkbox" class="forvoyez-image-checkbox absolute top-2 left-2 form-checkbox" value="<?php echo esc_attr($image->ID); ?>">
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" class="w-full h-48 object-cover rounded">
            <div class="forvoyez-metadata-icons absolute bottom-2 left-2 bg-white bg-opacity-80 rounded p-1">
                <?php if ($all_complete) : ?>
                    <span class="text-green-500" title="All metadata complete">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <?php else : ?>
                    <?php if (empty($image_alt)) : ?>
                        <span class="text-red-500 mr-1" title="Missing Alt Text">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <?php endif; ?>
                    <?php if (empty($image->post_title)) : ?>
                        <span class="text-red-500 mr-1" title="Missing Title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </span>
                    <?php endif; ?>
                    <?php if (empty($image->post_excerpt)) : ?>
                        <span class="text-red-500" title="Missing Caption">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                    </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="forvoyez-action-buttons absolute bottom-2 right-2 flex">
                <button class="forvoyez-analyze-button bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded mr-2" title="Analyze with ForVoyez">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                </button>
                <button class="forvoyez-see-more bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-1 px-2 rounded" title="See Details">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
            <div class="forvoyez-loader absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 hidden">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
            </div>
            <div class="forvoyez-image-details hidden mt-2 p-2 bg-gray-100 rounded-md">
                <p><strong>Title:</strong> <?php echo esc_html($image->post_title ?: 'Not set'); ?></p>
                <p><strong>Alt Text:</strong> <?php echo esc_html($image_alt ?: 'Not set'); ?></p>
                <p><strong>Caption:</strong> <?php echo esc_html($image->post_excerpt ?: 'Not set'); ?></p>
            </div>
        </div>
        <?php
    }

    public static function display_filters($total_images, $displayed_images, $per_page, $current_filters)
    {
        ?>
        <div class="forvoyez-filters bg-white p-4 mb-6 border border-gray-200 rounded-lg shadow-sm">
            <form method="get" action="" class="space-y-4">
                <input type="hidden" name="page" value="forvoyez-auto-alt-text">
                <div class="flex flex-wrap items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center">
                            <span class="mr-2">Items per page:</span>
                            <select name="per_page" class="form-select rounded border-gray-300">
                                <option value="25" <?php selected($per_page, 25); ?>>25</option>
                                <option value="50" <?php selected($per_page, 50); ?>>50</option>
                                <option value="100" <?php selected($per_page, 100); ?>>100</option>
                                <option value="-1" <?php selected($per_page, -1); ?>>All</option>
                            </select>
                        </label>
                    </div>
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="filter[]" value="alt" <?php checked(in_array('alt', $current_filters)); ?> class="form-checkbox text-blue-500">
                            <span class="ml-2">Missing Alt</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="filter[]" value="title" <?php checked(in_array('title', $current_filters)); ?> class="form-checkbox text-blue-500">
                            <span class="ml-2">Missing Title</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="filter[]" value="caption" <?php checked(in_array('caption', $current_filters)); ?> class="form-checkbox text-blue-500">
                            <span class="ml-2">Missing Caption</span>
                        </label>
                    </div>
                    <div>
                        <input type="submit" value="Apply Filters" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    </div>
                </div>
                <div class="flex justify-end items-center text-sm text-gray-600">
                    <span>Images Displayed: <strong><?php echo $displayed_images; ?></strong> / <?php echo $total_images; ?></span>
                </div>
            </form>
        </div>
        <?php
    }
}