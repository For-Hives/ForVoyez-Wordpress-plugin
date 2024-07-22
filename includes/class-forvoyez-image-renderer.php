<?php
defined('ABSPATH') || exit;

class Forvoyez_Image_Renderer
{
    public static function render_image_item($image)
    {
        $image_url = wp_get_attachment_url($image->ID);
        $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $is_analyzed = get_post_meta($image->ID, '_forvoyez_analyzed', true);
        $disabled_class = $is_analyzed ? 'opacity-100' : '';
        $all_complete = !empty($image_alt) && !empty($image->post_title) && !empty($image->post_excerpt);
        ?>
        <li class="col-span-1 flex flex-col divide-y divide-gray-200 rounded-lg bg-white text-center shadow <?php echo $disabled_class; ?>" data-image-id="<?php echo esc_attr($image->ID); ?>">
            <div class="flex flex-1 flex-col p-2 pb-8">
                <div class="relative w-full h-48 mb-4">
                    <img class="w-full h-full object-cover rounded-lg" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
                    <input type="checkbox" class="absolute top-2 left-2 form-checkbox h-5 w-5 text-blue-600 rounded transition duration-150 ease-in-out" value="<?php echo esc_attr($image->ID); ?>">
                    <div class="absolute top-2 right-2 flex space-x-1">
                        <?php if (!$all_complete) : ?>
                            <?php if (empty($image_alt)) : ?>
                                <span class="bg-red-500 text-white rounded-full p-1" title="Missing Alt Text">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <?php endif; ?>
                            <?php if (empty($image->post_title)) : ?>
                                <span class="bg-red-500 text-white rounded-full p-1" title="Missing Title">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                            </span>
                            <?php endif; ?>
                            <?php if (empty($image->post_excerpt)) : ?>
                                <span class="bg-red-500 text-white rounded-full p-1" title="Missing Caption">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                </svg>
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <h3 class="mt-2 text-sm font-medium text-gray-900"><?php echo esc_html($image->post_title ?: 'No Title'); ?></h3>
                <dl class="mt-1 flex flex-grow flex-col justify-between">
                    <dd class="text-sm text-gray-500"><?php echo esc_html(wp_trim_words($image->post_excerpt, 10, '...')); ?></dd>
                </dl>
            </div>
            <div>
                <div class="-mt-px flex divide-x divide-gray-200">
                    <div class="flex w-0 flex-1">
                        <button class="analyze-button relative -mr-px inline-flex w-0 flex-1 items-center justify-center gap-x-3 rounded-bl-lg border border-transparent py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            Analyze
                        </button>
                    </div>
                    <div class="-ml-px flex w-0 flex-1">
                        <button class="see-more-button relative inline-flex w-0 flex-1 items-center justify-center gap-x-3 rounded-br-lg border border-transparent py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Details
                        </button>
                    </div>
                </div>
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
