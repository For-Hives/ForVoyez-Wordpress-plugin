<?php
$settings = new Forvoyez_Settings();
$api_key = $settings->get_api_key();
?>

<div class="configuration-tab">
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-gray-900">API Configuration</h2>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Manage your ForVoyez API settings here</p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
            <div class="mb-6">
                <h3 class="text-md font-medium text-gray-900 mb-2">About ForVoyez API</h3>
                <p class="text-sm text-gray-600 mb-2">
                    ForVoyez API provides powerful image analysis capabilities for your WordPress site.
                    To use this plugin, you need to obtain an API key from ForVoyez.
                </p>
                <p class="text-sm text-gray-600 mb-2">
                    To get started:
                </p>
                <ol class="list-decimal list-inside text-sm text-gray-600 mb-4 ml-4">
                    <li>Visit the <a href="https://forvoyez.com/signup" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">ForVoyez signup page</a> to create an account.</li>
                    <li>Once logged in, navigate to your <a href="https://forvoyez.com/dashboard" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">ForVoyez dashboard</a>.</li>
                    <li>Generate an API key in the API section of your dashboard.</li>
                    <li>Copy the API key and paste it in the field below.</li>
                </ol>
                <p class="text-sm text-gray-600">
                    For more information, please refer to the <a href="https://doc.forvoyez.com" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">ForVoyez API documentation</a>.
                </p>
            </div>
            <div class="mb-4">
                <label for="forvoyez-api-key" class="block text-sm font-medium text-gray-700">API Key</label>
                <div class="mt-1 flex rounded-md shadow-sm">
                    <input type="password" name="forvoyez_api_key" id="forvoyez-api-key" class="forvoyez-api-key-input flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300" placeholder="Enter your API key" value="<?php echo esc_attr($api_key); ?>">
                    <button type="button" class="forvoyez-toggle-visibility inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
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