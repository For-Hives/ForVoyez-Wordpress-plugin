<?php
$settings = new Forvoyez_Settings();
$api_key = $settings->get_api_key();
?>
<div id="forvoyez-api-settings-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full" style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">ForVoyez API Settings</h2>
        <p class="mb-4 text-gray-600">Enter your ForVoyez API key below:</p>
        <div class="forvoyez-input-api-key relative">
            <input type="password" id="forvoyez_api_key" name="forvoyez_api_key" value="<?php echo esc_attr($api_key); ?>"
                   class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-forvoyez-primary">
            <span id="forvoyez-toggle-visibility" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-700 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </span>
        </div>

        <div class="forvoyez-api-settings-buttons mt-6 flex justify-end">
            <button id="forvoyez-save-api-key" class="bg-forvoyez-primary hover:bg-forvoyez-primary-dark text-white font-bold py-2 px-4 rounded mr-2">
                Save API Key
            </button>
            <button id="forvoyez-close-modal" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                Close
            </button>
        </div>
    </div>
</div>