<?php
defined('ABSPATH') || exit;

class Forvoyez_Image_Renderer
{
    public static function render_image_item($image)
    {
        $image_url = wp_get_attachment_url($image->ID);
        $image_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        $all_complete = !empty($image_alt) && !empty($image->post_title) && !empty($image->post_excerpt);
        $disabled_class = $all_complete ? 'opacity-50' : '';
        ?>
        <li class="col-span-1 flex flex-col divide-y divide-gray-200 rounded-lg bg-white text-center shadow <?php echo $disabled_class; ?>" data-image-id="<?php echo esc_attr($image->ID); ?>">
            <div class="flex flex-1 flex-col p-2">
                <div class="relative w-full h-48">
                    <img class="w-full h-full object-cover rounded-lg" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
                    <div class="hidden absolute inset-0 bg-white p-2 overflow-y-auto details-view">
                        <p class="text-sm text-gray-500">
                            <strong>Title:</strong> <span class="title-content"><?php echo esc_html($image->post_title ?: 'Not set'); ?></span>
                        </p>
                        <p class="text-sm text-gray-500">
                            <strong>Alt Text:</strong> <span class="alt-content"><?php echo esc_html($image_alt ?: 'Not set'); ?></span>
                        </p>
                        <p class="text-sm text-gray-500">
                            <strong>Caption:</strong> <span class="caption-content"><?php echo esc_html($image->post_excerpt ?: 'Not set'); ?></span>
                        </p>
                    </div>
                    <input type="checkbox" class="absolute top-2 left-2 form-checkbox h-5 w-5 text-blue-600 rounded transition duration-150 ease-in-out" value="<?php echo esc_attr($image->ID); ?>">
                    <div class="absolute top-0 right-0 flex space-x-1 bg-white p-2 rounded shadow-lg metadata-icons">
                    <span class="bg-red-500 text-white rounded-full p-1 <?php echo empty($image_alt) ? '' : 'hidden'; ?> alt-missing" title="Missing Alt Text">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                        <span class="bg-red-500 text-white rounded-full p-1 <?php echo empty($image->post_title) ? '' : 'hidden'; ?> title-missing" title="Missing Title">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                    </span>
                        <span class="bg-red-500 text-white rounded-full p-1 <?php echo empty($image->post_excerpt) ? '' : 'hidden'; ?> caption-missing" title="Missing Caption">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </span>
                        <span class="bg-green-500 text-white rounded-full p-1 <?php echo $all_complete ? '' : 'hidden'; ?> all-complete" title="All Complete">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-75 hidden loader">
                        <svg class="animate-spin h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
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
                            <div id="see-more-button-details" class="w-full h-full inline-flex items-center justify-center gap-x-3">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Details
                            </div>
                            <div id="see-more-button-images" class="images w-full h-full hidden items-center justify-center gap-x-3">
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Images
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </li>
        <?php
    }
}
