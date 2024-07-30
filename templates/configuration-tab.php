<div class="configuration-tab">
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-gray-900">API Configuration</h2>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Manage your ForVoyez API settings here</p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
            <div class="mb-4">
                <label for="forvoyez-api-key" class="block text-sm font-medium text-gray-700">API Key</label>
                <div class="mt-1 flex rounded-md shadow-sm">
                    <input type="password" name="forvoyez_api_key" id="forvoyez-api-key" class="forvoyez-api-key-input flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300" placeholder="Enter your API key">
                    <button type="button" class="forvoyez-toggle-visibility inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="mt-4">
                <button type="button" class="forvoyez-save-api-key inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save API Key
                </button>
            </div>
        </div>
    </div>
</div>

<?php include FORVOYEZ_PLUGIN_DIR . 'templates/api-settings-modal.php'; ?>