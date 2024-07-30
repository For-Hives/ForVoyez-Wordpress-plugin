<div class="dashboard-tab">
    <div class="bg-white shadow overflow-hidden sm:rounded-lgmb-6">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-gray-900">Dashboard</h2>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Overview of your Auto Alt Text for Images</p>

        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Total Images</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo esc_html($this->get_total_images_count()); ?></dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Images Completed</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo esc_html($this->get_processed_images_count()); ?></dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">Images Without alt / caption</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo esc_html($this->get_pending_images_count()); ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Pre-requisites</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Make sure you have the following before you start:</p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
                <li>Go to the 'Settings' tab</li>
                <li>Enter your ForVoyez API key</li>
                <li>Click on 'Save API Key'</li>
            </ol>
        </div>

    </div>
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Quick Start</h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">Follow these steps to generate alt text for your images:</p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
                <li>Go to the 'Manage Images' tab</li>
                <li>Select the images you want to process</li>
                <li>Click on 'Generate Alt Text' to start the process</li>
                <li>Review and edit the generated alt text as needed</li>
            </ol>
        </div>
    </div>
</div>