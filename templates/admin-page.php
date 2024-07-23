<div class="wrap p-6 min-h-screen">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Auto Alt Text for Images</h1>
    <div class="py-4 w-full flex justify-between items-start">
        <button id="forvoyez-open-settings" class="forvoyez-open-api-settings bg-forvoyez-primary hover:bg-forvoyez-primary-dark text-white font-bold py-2 px-4 rounded mb-4">
            Configure API Settings
        </button>
        <div class="flex flex-col gap-2 items-center">
            <h3 class="text-start w-full font-bold">
                Legend :
            </h3>
            <div class="flex gap-6 items-center">
                <div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        Missing Alt Text :
                    </p>
                    <span class="bg-red-500 text-white rounded-full p-1" title="Missing Alt Text">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        Missing title :
                    </p>
                    <span class="bg-red-500 text-white rounded-full p-1" title="Missing Title">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                        </svg>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        Missing caption :
                    </p>
                    <span class="bg-red-500 text-white rounded-full p-1" title="Missing Caption">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
            </span>
                </div>
                <div class="flex items-center gap-2">
                    <p class="text-gray-700 italic">
                        All complete :
                    </p>
                    <span class="bg-green-500 text-white rounded-full p-1" title="All Complete">
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
                <div>
                    <button type="button" id="forvoyez-analyze-missing" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Analyse all missing alt/caption/title images (<span id="forvoyez-missing-count">0</span>)
                    </button>
                    <button type="button" id="forvoyez-analyze-missing-alt" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Analyse all missing alt images (<span id="forvoyez-missing-alt-count">0</span>)
                    </button>
                    <button type="button" id="forvoyez-analyze-all" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Analyse all images (<span id="forvoyez-all-count">0</span>)
                    </button>
                </div>
            </div>
        </form>
        <div class="forvoyez-bulk-actions mt-4 flex gap-4 items-center w-full justify-between">
            <div>
                <label class="inline-flex items-center">
                    <input type="checkbox" id="forvoyez-select-all" class="form-checkbox h-5 w-5 text-forvoyez-primary">
                    <span class="ml-2 text-gray-700">Select All</span>
                </label>
                <button id="forvoyez-bulk-analyze" class="bg-forvoyez-secondary hover:bg-forvoyez-secondary-dark text-white font-bold py-2 px-4 rounded ml-4">
                    Analyze Selected Images
                </button>
            </div>
            <div class="h-full flex justify-end items-end">
                <div id="forvoyez-image-counter" class="text-sm text-gray-600 mb-4"></div>
            </div>
        </div>
    </div>

    <div id="forvoyez-loader" class="hidden inset-0 flex items-center pt-16 pb-32 justify-center z-50">
        <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-forvoyez-primary"></div>
    </div>

    <div id="forvoyez-images-container">
        <!-- Images will be loaded here dynamically -->
    </div>
    <div id="forvoyez-pagination" class="forvoyez-pagination">
        <!-- Pagination will be loaded here dynamically -->
    </div>
</div>
<?php include FORVOYEZ_PLUGIN_DIR . 'templates/api-settings-modal.php'; ?>