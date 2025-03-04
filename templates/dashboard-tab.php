<?php
// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit('Direct access to this file is not allowed.');
} ?>
<div class="dashboard-tab">
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-gray-900">
            <?php esc_html_e('Dashboard', 'auto-alt-text-for-images'); ?>
            </h2>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php esc_html_e(
            	'Overview of your Auto Alt Text for Images',
            	'auto-alt-text-for-images',
            ); ?>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                    <?php esc_html_e(
                    	'Total Images',
                    	'auto-alt-text-for-images',
                    ); ?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php echo esc_html($this->get_total_images_count()); ?>
                    </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                    <?php esc_html_e(
                    	'Images Completed',
                    	'auto-alt-text-for-images',
                    ); ?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php echo esc_html($this->get_processed_images_count()); ?>
                    </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                    <?php esc_html_e(
                    	'Images Without alt / caption',
                    	'auto-alt-text-for-images',
                    ); ?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php echo esc_html($this->get_pending_images_count()); ?>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
            <?php esc_html_e('Pre-requisites', 'auto-alt-text-for-images'); ?>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php esc_html_e(
            	'Make sure you have the following before you start:',
            	'auto-alt-text-for-images',
            ); ?>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
                <li>
                <?php esc_html_e(
                	"Go to the 'Settings' tab",
                	'auto-alt-text-for-images',
                ); ?>
                </li>
                <li>
                <?php esc_html_e(
                	'Enter your ForVoyez API key',
                	'auto-alt-text-for-images',
                ); ?>
                </li>
                <li>
                <?php esc_html_e(
                	"Click on 'Save API Key'",
                	'auto-alt-text-for-images',
                ); ?>
                </li>
            </ol>
        </div>
    </div>
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
            <?php esc_html_e('Quick Start', 'auto-alt-text-for-images'); ?>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php esc_html_e(
            	'Follow these steps to generate alt text for your images:',
            	'auto-alt-text-for-images',
            ); ?>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
                <li>
                <?php esc_html_e(
                	"Go to the 'Manage Images' tab",
                	'auto-alt-text-for-images',
                ); ?>
                </li>
                <li>
                <?php esc_html_e(
                	'Select the images you want to process',
                	'auto-alt-text-for-images',
                ); ?>
                </li>
                <li>
                <?php esc_html_e(
                	"Click on 'Generate Alt Text' to start the process",
                	'auto-alt-text-for-images',
                ); ?>
                </li>
                <li>
                <?php esc_html_e(
                	'Review and edit the generated alt text as needed',
                	'auto-alt-text-for-images',
                ); ?>
                </li>
            </ol>
        </div>
    </div>
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            <?php esc_html_e(
            	'Automatic Image Analysis',
            	'auto-alt-text-for-images',
            ); ?>
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php esc_html_e(
            	'ForVoyez can automatically analyze your images upon upload.',
            	'auto-alt-text-for-images',
            ); ?>
        </p>
    </div>
    <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
        <dl class="sm:divide-y sm:divide-gray-200">
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">
                    <?php esc_html_e('Status', 'auto-alt-text-for-images'); ?>
                </dt>
                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php echo get_option(
                    	'forvoyez_auto_analyze_enabled',
                    	false,
                    )
                    	? esc_html__('Enabled', 'auto-alt-text-for-images')
                    	: esc_html__('Disabled', 'auto-alt-text-for-images'); ?>
                </dd>
            </div>
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">
                    <?php esc_html_e(
                    	'Configuration',
                    	'auto-alt-text-for-images',
                    ); ?>
                </dt>
                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php esc_html_e(
                    	'You can enable or disable this feature in the Configuration tab.',
                    	'auto-alt-text-for-images',
                    ); ?>
                </dd>
            </div>
        </dl>
    </div>
</div>
<div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900">
            <?php esc_html_e('ForVoyez Credits', 'auto-alt-text-for-images'); ?>
        </h3>
        <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php esc_html_e(
            	'Your current ForVoyez credit balance and subscription status',
            	'auto-alt-text-for-images',
            ); ?>
        </p>
    </div>
    <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
        <?php
        $token_info = forvoyez_get_token_info();
        if ($token_info['success']):

        	$credits = $token_info['user']['credits'];
        	$is_subscribed =
        		$token_info['subscription']['isSubscribed'] ?? false;
        	$plan_name = $is_subscribed
        		? $token_info['subscription']['plan']['name']
        		: '';
        	?>
            <dl class="sm:divide-y sm:divide-gray-200">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                        <?php esc_html_e(
                        	'Remaining Credits',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            <?php echo $credits > 20
                            	? 'bg-green-100 text-green-800'
                            	: ($credits > 5
                            		? 'bg-yellow-100 text-yellow-800'
                            		: 'bg-red-100 text-red-800'); ?>">
                            <?php echo esc_html($credits); ?>
                        </span>
                    </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                        <?php esc_html_e(
                        	'Subscription Status',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                        <?php if ($is_subscribed): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <?php esc_html_e(
                                	'Active',
                                	'auto-alt-text-for-images',
                                ); ?>
                            </span>
                            - <?php echo esc_html($plan_name); ?>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                <?php esc_html_e(
                                	'No Active Subscription',
                                	'auto-alt-text-for-images',
                                ); ?>
                            </span>
                        <?php endif; ?>
                    </dd>
                </div>
                <?php if ($credits < 10): ?>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-red-500">
                        <?php esc_html_e(
                        	'Warning',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </dt>
                    <dd class="mt-1 text-sm text-red-600 sm:mt-0 sm:col-span-2">
                        <?php esc_html_e(
                        	'You are running low on credits. Please visit the ForVoyez website to purchase more credits.',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </dd>
                </div>
                <?php endif; ?>
            </dl>
        <?php
        else:
        	 ?>
            <div class="px-4 py-5 sm:px-6">
                <div class="text-sm text-gray-500">
                    <?php if (isset($token_info['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">
                                        <?php echo esc_html(
                                        	$token_info['error']['message'] ??
                                        		__(
                                        			'Unable to retrieve token information',
                                        			'auto-alt-text-for-images',
                                        		),
                                        ); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php esc_html_e(
                        	'Could not retrieve credit information. Please check your API key configuration.',
                        	'auto-alt-text-for-images',
                        ); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php
        endif;
        ?>
    </div>
</div>
</div>
