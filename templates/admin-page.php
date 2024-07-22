<div class="wrap bg-gray-100 p-6">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Auto Alt Text for Images</h1>
    <button id="forvoyez-open-settings" class="forvoyez-open-api-settings bg-forvoyez-primary hover:bg-forvoyez-primary-dark text-white font-bold py-2 px-4 rounded mb-4">
        Configure API Settings
    </button>
    <?php Forvoyez_Admin::display_status_configuration(); ?>

    <form id="forvoyez-filter-form" class="bg-white p-4 mb-6 border border-gray-200 rounded-lg shadow-sm">
        <!-- Add your filter inputs here -->
    </form>

    <div class="forvoyez-bulk-actions mb-4">
        <label class="inline-flex items-center">
            <input type="checkbox" id="forvoyez-select-all" class="form-checkbox h-5 w-5 text-forvoyez-primary">
            <span class="ml-2 text-gray-700">Select All</span>
        </label>
        <button id="forvoyez-bulk-analyze" class="bg-forvoyez-secondary hover:bg-forvoyez-secondary-dark text-white font-bold py-2 px-4 rounded ml-4">
            Analyze Selected Images
        </button>
    </div>

    <div id="forvoyez-loader" class="hidden fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-32 w-32 border-b-2 border-forvoyez-primary"></div>
    </div>

    <div id="forvoyez-images-container">
        <!-- Images will be loaded here dynamically -->
    </div>
</div>
<?php include FORVOYEZ_PLUGIN_DIR . 'templates/api-settings-modal.php'; ?>