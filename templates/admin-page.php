<?php
// If this file is called directly, abort.
if (!defined('ABSPATH')) {
	exit('Direct access to this file is not allowed.');
}

$token_info = forvoyez_get_token_info();
if ($token_info['success']):
	$credits = $token_info['user']['credits']; ?>
<div class="mb-4 flex items-center">
    <span class="text-sm font-medium text-gray-700 mr-2"><?php esc_html_e(
    	'Available Credits:',
    	'auto-alt-text-for-images',
    ); ?></span>
    <span class="px-2 py-1 inline-flex text-xs leading-4 font-semibold rounded-full
        <?php echo $credits > 20
        	? 'bg-green-100 text-green-800'
        	: ($credits > 5
        		? 'bg-yellow-100 text-yellow-800'
        		: 'bg-red-100 text-red-800'); ?>">
        <?php echo esc_html($credits); ?>
    </span>
    <?php if ($credits < 10): ?>
    <span class="ml-2 text-xs text-red-600">
        <?php esc_html_e(
        	'Low credits! Please recharge.',
        	'auto-alt-text-for-images',
        ); ?>
    </span>
    <?php endif; ?>
</div>
<?php
endif;
?>

<div class="wrap p-6 min-h-screen relative [&_button,a]:shadow-none [&_button,a]:outline-none">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">
    <?php esc_html_e('Auto Alt Text for Images', 'auto-alt-text-for-images'); ?>
    </h1>
	<div class="py-4 w-full flex justify-between items-start">
		<div class="flex flex-col gap-2 items-center">
            <h3 class="text-start w-full font-bold">
                <?php esc_html_e('Legend :', 'auto-alt-text-for-images'); ?>
            </h3>
			<div class="flex gap-6 items-center">
				<div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        <?php esc_html_e(
                        	'Missing Alt Text :',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </p>
                    <span class="bg-red-500 text-white rounded-full p-1" title="
                    <?php esc_attr_e(
                    	'Missing Alt Text',
                    	'auto-alt-text-for-images',
                    ); ?>
                    ">
						<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
						</svg>
					</span>
				</div>
				<div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        <?php esc_html_e(
                        	'Missing title :',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </p>
                    <span class="bg-red-500 text-white rounded-full p-1" title="
                    <?php esc_attr_e(
                    	'Missing Title',
                    	'auto-alt-text-for-images',
                    ); ?>
                    ">
						<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
						</svg>
					</span>
				</div>
				<div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        <?php esc_html_e(
                        	'Missing caption :',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </p>
                    <span class="bg-red-500 text-white rounded-full p-1" title="
                    <?php esc_attr_e(
                    	'Missing Caption',
                    	'auto-alt-text-for-images',
                    ); ?>
                    ">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                        </svg>
                    </span>
				</div>
				<div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        <?php esc_html_e(
                        	'All complete :',
                        	'auto-alt-text-for-images',
                        ); ?>
                    </p>
                    <span class="bg-green-500 text-white rounded-full p-1" title="
                    <?php esc_attr_e(
                    	'All Complete',
                    	'auto-alt-text-for-images',
                    ); ?>
                    ">
						<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
						</svg>
					</span>
				</div>
			</div>
		</div>
	</div>
	<?php Forvoyez_Admin::display_status_configuration(); ?>

	<div class="bg-white p-4 mb-6 border border-gray-200 rounded-lg shadow-sm">
		<form id="forvoyez-filter-form" class="space-y-4">
			<div class="flex flex-wrap items-center justify-between gap-4">
				<div class="flex items-center space-x-4">
					<label class="flex items-center">
                        <span class="mr-2 text-sm font-medium text-gray-700">
                        <?php esc_html_e(
                        	'Items per page:',
                        	'auto-alt-text-for-images',
                        ); ?>
                        </span>
						<select id="forvoyez-per-page" name="per_page"
								class="form-select rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
                            <option value="-1">
                            <?php esc_html_e(
                            	'All',
                            	'auto-alt-text-for-images',
                            ); ?>
                            </option>
						</select>
					</label>
				</div>
				<div>
                    <button type="button" id="forvoyez-analyze-missing" class="bg-gray-700 hover:bg-gray-900 cursor-not-allowed text-white font-bold py-2 px-4 rounded" disabled>
                        <?php esc_html_e(
                        	'Analyze all missing alt/caption/title images',
                        	'auto-alt-text-for-images',
                        ); ?>
                        (<span id="forvoyez-missing-count">0</span>)
                    </button>
                    <button type="button" id="forvoyez-analyze-missing-alt" class="bg-gray-700 hover:bg-gray-900 cursor-not-allowed text-white font-bold py-2 px-4 rounded" disabled>
                        <?php esc_html_e(
                        	'Analyze all missing alt images',
                        	'auto-alt-text-for-images',
                        ); ?>
                        (<span id="forvoyez-missing-alt-count">0</span>)
                    </button>
                    <button type="button" id="forvoyez-analyze-all" class="bg-gray-700 hover:bg-gray-900 cursor-not-allowed text-white font-bold py-2 px-4 rounded" disabled>
                        <?php esc_html_e(
                        	'Analyze all images',
                        	'auto-alt-text-for-images',
                        ); ?>
                        (<span id="forvoyez-all-count">0</span>)
                    </button>
				</div>
			</div>
		</form>
		<div class="mt-4 w-full bg-gray-200 rounded-full h-2.5 hidden" id="forvoyez-progress-container">
			<div class="bg-blue-600 h-2.5 rounded-full" id="forvoyez-progress-bar" style="width: 0%"></div>
		</div>
		<div class="w-full mt-2 hidden italic text-gray-700" id="forvoyez-progress-bar-count"></div>
		<div class="forvoyez-bulk-actions mt-4 flex gap-4 items-center w-full justify-between">
			<div>
				<label class="inline-flex items-center">
					<input type="checkbox" id="forvoyez-select-all" class="form-checkbox h-5 w-5 text-forvoyez-primary">
                    <span class="ml-2 text-gray-700">
                    <?php esc_html_e(
                    	'Select All',
                    	'auto-alt-text-for-images',
                    ); ?>
                    </span>
				</label>
                <button id="forvoyez-bulk-analyze" class="bg-forvoyez-secondary hover:bg-forvoyez-secondary-dark text-white font-bold py-2 px-4 rounded ml-4">
                    <?php esc_html_e(
                    	'Analyze Selected Images',
                    	'auto-alt-text-for-images',
                    ); ?>
                </button>
			</div>
			<div class="h-full flex justify-end items-end">
				<div id="forvoyez-image-counter" class="text-sm text-gray-600 mb-4"></div>
			</div>
		</div>
	</div>

	<div class="relative">
		<div id="forvoyez-loader" class="hidden absolute rounded !pointer-events-none top-0 left-0 w-full h-full flex items-start pt-16 pb-32 justify-center z-50">
			<div class="animate-spin rounded-full mt-16 h-16 w-16 border-b-2 border-forvoyez-primary"></div>
		</div>
		<div id="forvoyez-images-container">
			<!-- Images will be loaded here dynamically -->
		</div>
	</div>

	<div id="forvoyez-pagination" class="forvoyez-pagination">
		<!-- Pagination will be loaded here dynamically -->
	</div>

	<div id="forvoyez-confirm-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full" style="z-index: 1000;">
		<div class="relative top-1/2 -translate-y-1/2 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">
            <?php esc_html_e('Confirm Analysis', 'auto-alt-text-for-images'); ?>
            </h2>
			<p id="forvoyez-confirm-message" class="mb-4 text-gray-600"></p>
			<div class="mt-6 flex justify-end">
                <button id="forvoyez-confirm-action" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded mr-2">
                    <?php esc_html_e('Confirm', 'auto-alt-text-for-images'); ?>
                </button>
                <button id="forvoyez-cancel-action" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                    <?php esc_html_e('Cancel', 'auto-alt-text-for-images'); ?>
                </button>
			</div>
		</div>
	</div>
</div>
<?php require FORVOYEZ_PLUGIN_DIR . 'templates/api-settings-modal.php'; ?>
