<div class="wrap p-6 min-h-screen">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Auto Alt Text for Images</h1>
    <button id="forvoyez-open-settings" class="forvoyez-open-api-settings bg-forvoyez-primary hover:bg-forvoyez-primary-dark text-white font-bold py-2 px-4 rounded mb-4">
        Configure API Settings
    </button>
    <?php Forvoyez_Admin::display_status_configuration(); ?>

    <div class="bg-white p-4 mb-6 border border-gray-200 rounded-lg shadow-sm">
        <form id="forvoyez-filter-form" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <span class="mr-2 text-sm font-medium text-gray-700">Items per page:</span>
                        <select id="forvoyez-per-page" name="per_page"
                                class="form-select rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="-1">All</option>
                        </select>
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="filter[]" value="alt"
                               class="form-checkbox h-5 w-5 text-blue-600 transition duration-150 ease-in-out">
                        <span class="ml-2 text-sm text-gray-700">Missing Alt</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="filter[]" value="title"
                               class="form-checkbox h-5 w-5 text-blue-600 transition duration-150 ease-in-out">
                        <span class="ml-2 text-sm text-gray-700">Missing Title</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="filter[]" value="caption"
                               class="form-checkbox h-5 w-5 text-blue-600 transition duration-150 ease-in-out">
                        <span class="ml-2 text-sm text-gray-700">Missing Caption</span>
                    </label>
                </div>
                <div>
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                        Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="forvoyez-bulk-actions mb-4 flex gap-4 items-center w-full justify-between">
        <div>
            <label class="inline-flex items-center">
                <input type="checkbox" id="forvoyez-select-all" class="form-checkbox h-5 w-5 text-forvoyez-primary">
                <span class="ml-2 text-gray-700">Select All</span>
            </label>
            <button id="forvoyez-bulk-analyze" class="bg-forvoyez-secondary hover:bg-forvoyez-secondary-dark text-white font-bold py-2 px-4 rounded ml-4">
                Analyze Selected Images
            </button>
        </div>
        <div class="">
            <div id="forvoyez-image-counter" class="text-sm text-gray-600 mb-4"></div>
        </div>
    </div>

    <div id="forvoyez-loader" class="hidden inset-0 flex items-center pt-16 pb-32 justify-center z-50">
        <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-forvoyez-primary"></div>
    </div>

    <div id="forvoyez-images-container">
        <!-- Images will be loaded here dynamically -->
    </div>
</div>
<?php include FORVOYEZ_PLUGIN_DIR . 'templates/api-settings-modal.php'; ?>