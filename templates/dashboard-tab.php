<?php
// If this file is called directly, abort.
if ( !defined( 'ABSPATH' ) ) {
	exit( 'Direct access to this file is not allowed.' );
} ?>
<div class="dashboard-tab">
    <div class="bg-white shadow overflow-hidden sm:rounded-lgmb-6">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-gray-900">
            <?php
			esc_html_e(
				'Dashboard',
				'auto-alt-text-for-images',
			);
?>
            </h2>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php
esc_html_e(
	'Overview of your Auto Alt Text for Images',
	'auto-alt-text-for-images',
);
?>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                    <?php
		esc_html_e(
			'Total Images',
			'auto-alt-text-for-images',
		);
?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php
echo esc_html(
	$this->get_total_images_count(),
);
?>
                    </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                    <?php
esc_html_e(
	'Images Completed',
	'auto-alt-text-for-images',
);
?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php
echo esc_html(
	$this->get_processed_images_count(),
);
?>
                    </dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-gray-500">
                    <?php
esc_html_e(
	'Images Without alt / caption',
	'auto-alt-text-for-images',
);
?>
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                    <?php
echo esc_html(
	$this->get_pending_images_count(),
);
?>
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
            <?php
			esc_html_e(
				'Pre-requisites',
				'auto-alt-text-for-images',
			);
?>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php
esc_html_e(
	'Make sure you have the following before you start:',
	'auto-alt-text-for-images',
);
?>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
                <li>
                <?php
	esc_html_e(
		"Go to the 'Settings' tab",
		'auto-alt-text-for-images',
	);
?>
                </li>
                <li>
                <?php
esc_html_e(
	'Enter your ForVoyez API key',
	'auto-alt-text-for-images',
);
?>
                </li>
                <li>
                <?php
esc_html_e(
	"Click on 'Save API Key'",
	'auto-alt-text-for-images',
);
?>
                </li>
            </ol>
        </div>
    </div>
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mt-6">
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
            <?php
			esc_html_e(
				'Quick Start',
				'auto-alt-text-for-images',
			);
?>
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php
esc_html_e(
	'Follow these steps to generate alt text for your images:',
	'auto-alt-text-for-images',
);
?>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
                <li>
                <?php
	esc_html_e(
		"Go to the 'Manage Images' tab",
		'auto-alt-text-for-images',
	);
?>
                </li>
                <li>
                <?php
esc_html_e(
	'Select the images you want to process',
	'auto-alt-text-for-images',
);
?>
                </li>
                <li>
                <?php
esc_html_e(
	"Click on 'Generate Alt Text' to start the process",
	'auto-alt-text-for-images',
);
?>
                </li>
                <li>
                <?php
esc_html_e(
	'Review and edit the generated alt text as needed',
	'auto-alt-text-for-images',
);
?>
                </li>
            </ol>
        </div>
    </div>
</div>