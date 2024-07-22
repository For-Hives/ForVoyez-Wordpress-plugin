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
        <li class="relative <?php echo $disabled_class; ?> w-64 h-80" data-image-id="<?php echo esc_attr($image->ID); ?>">
            <div class="group h-64 w-full overflow-hidden rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" class="h-full w-full object-cover group-hover:opacity-75">
                <input type="checkbox" class="absolute top-2 left-2 form-checkbox h-5 w-5 text-blue-600 rounded transition duration-150 ease-in-out"
                       value="<?php echo esc_attr($image->ID); ?>">
                <div class="absolute top-2 right-2 flex space-x-1">
                    <?php if (!$all_complete) : ?>
                        <?php if (empty($image_alt)) : ?>
                            <span class="bg-red-500 text-white rounded-full p-1" title="Missing Alt Text">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <?php endif; ?>
                        <?php if (empty($image->post_title)) : ?>
                            <span class="bg-red-500 text-white rounded-full p-1" title="Missing Title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <?php endif; ?>
                        <?php if (empty($image->post_excerpt)) : ?>
                            <span class="bg-red-500 text-white rounded-full p-1" title="Missing Caption">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-2 flex justify-between">
                <button class="analyze-button bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                        title="Analyze with ForVoyez">
                    Analyze
                </button>
                <button class="see-more-button bg-gray-200 hover:bg-gray-300 text-gray-800 py-1 px-3 rounded transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50"
                        title="See Details">
                    Details
                </button>
            </div>

            <div class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full" id="details-modal-<?php echo esc_attr($image->ID); ?>">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3 text-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Image Details</h3>
                        <div class="mt-2 px-7 py-3">
                            <p class="text-sm text-gray-500">
                                <strong>Title:</strong> <?php echo esc_html($image->post_title ?: 'Not set'); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <strong>Alt Text:</strong> <?php echo esc_html($image_alt ?: 'Not set'); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <strong>Caption:</strong> <?php echo esc_html($image->post_excerpt ?: 'Not set'); ?>
                            </p>
                        </div>
                        <div class="items-center px-4 py-3">
                            <button id="close-modal-<?php echo esc_attr($image->ID); ?>"
                                    class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </li>
        <?php
    }

    public static function display_filters($total_images, $displayed_images, $per_page, $current_filters)
    {
        ?>
        <div class="bg-white p-4 mb-6 border border-gray-200 rounded-lg shadow-sm">
            <form method="get" action="" class="space-y-4">
                <input type="hidden" name="page" value="forvoyez-auto-alt-text">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center">
                            <span class="mr-2 text-sm font-medium text-gray-700">Items per page:</span>
                            <select name="per_page"
                                    class="form-select rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <option value="25" <?php selected($per_page, 25); ?>>25</option>
                                <option value="50" <?php selected($per_page, 50); ?>>50</option>
                                <option value="100" <?php selected($per_page, 100); ?>>100</option>
                                <option value="-1" <?php selected($per_page, -1); ?>>All</option>
                            </select>
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="filter[]" value="alt" <?php checked(in_array('alt', $current_filters)); ?>
                                   class="form-checkbox h-5 w-5 text-blue-600 transition duration-150 ease-in-out">
                            <span class="ml-2 text-sm text-gray-700">Missing Alt</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="filter[]" value="title" <?php checked(in_array('title', $current_filters)); ?>
                                   class="form-checkbox h-5 w-5 text-blue-600 transition duration-150 ease-in-out">
                            <span class="ml-2 text-sm text-gray-700">Missing Title</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="filter[]" value="caption" <?php checked(in_array('caption', $current_filters)); ?>
                                   class="form-checkbox h-5 w-5 text-blue-600 transition duration-150 ease-in-out">
                            <span class="ml-2 text-sm text-gray-700">Missing Caption</span>
                        </label>
                    </div>
                    <div>
                        <input type="submit" value="Apply Filters"
                               class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
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
