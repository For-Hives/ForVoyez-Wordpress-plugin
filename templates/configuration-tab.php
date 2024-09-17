<?php

// If this file is called directly, abort.
if ( !defined( 'ABSPATH' ) ) {
	exit( 'Direct access to this file is not allowed.' );
}

$settings = new Forvoyez_Settings();
$api_key  = $settings->get_api_key();
?>

<div class="configuration-tab">
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-lg leading-6 font-medium text-gray-900">
            <?php
            esc_html_e(
            	'API Configuration',
            	'forvoyez-auto-alt-text-for-images',
            );
            ?>
            </h2>
            <p class="mt-1 max-w-2xl text-sm text-gray-500">
            <?php
            esc_html_e(
            	'Manage your ForVoyez API settings here',
            	'forvoyez-auto-alt-text-for-images',
            );
            ?>
            </p>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
            <div class="mb-6">
                <h3 class="text-md font-medium text-gray-900 mb-2">
                <?php
                esc_html_e(
                	'About ForVoyez API',
                	'forvoyez-auto-alt-text-for-images',
                );
                ?>
                </h3>
                <p class="text-sm text-gray-600 mb-2">
                    <?php
                    esc_html_e(
                    	'ForVoyez API provides powerful image analysis capabilities for your WordPress site. To use this plugin, you need to obtain an API key from ForVoyez.',
                    	'forvoyez-auto-alt-text-for-images',
                    );
                    ?>
                </p>
                <p class="text-sm text-gray-600 mb-2">
                    <?php
                    esc_html_e(
                    	'To get started:',
                    	'forvoyez-auto-alt-text-for-images',
                    );
                    ?>
                </p>
                <ol class="list-decimal list-inside text-sm text-gray-600 mb-4 ml-4">
                    <li>
                        <?php
                        echo wp_kses_post(sprintf(
                            // translators: %s: URL of the ForVoyez signup page
                            __('Visit the <a href="%s" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">ForVoyez signup page</a> to create an account.', 'forvoyez-auto-alt-text-for-images'),
                            esc_url('https://forvoyez.com/signup')
                        ));
                        ?>
                    </li>
                    <li>
                        <?php
                            echo wp_kses_post(sprintf(
                                // translators: %s: URL of the ForVoyez dashboard
                                __('Once logged in, navigate to your <a href="%s" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">ForVoyez dashboard</a>.', 'forvoyez-auto-alt-text-for-images'),
                                esc_url('https://forvoyez.com/dashboard')
                            ));
                        ?>
                    </li>
                    <li>
                    <?php
                    esc_html_e(
                    	'Generate an API key in the API section of your dashboard.',
                    	'forvoyez-auto-alt-text-for-images',
                    );
                    ?>
                    </li>
                    <li>
                    <?php
                    esc_html_e(
                    	'Copy the API key and paste it in the field below.',
                    	'forvoyez-auto-alt-text-for-images',
                    );
                    ?>
                    </li>
                </ol>
                <p class="text-sm text-gray-600">
                    <?php
                    echo wp_kses_post(sprintf(
                            // translators: %s: URL of the ForVoyez API documentation
                            __('For more information, please refer to the <a href="%s" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">ForVoyez API documentation</a>.', 'forvoyez-auto-alt-text-for-images'),
                            esc_url('https://doc.forvoyez.com')
                    ));
                    ?>
                </p>
            </div>
            <div class="mb-4">
                <label for="forvoyez-api-key" class="block text-sm font-medium text-gray-700">
                <?php
                esc_html_e(
                	'API Key',
                	'forvoyez-auto-alt-text-for-images',
                );
                ?>
                </label>
                <div class="mt-1 flex rounded-md shadow-sm">
                    <input type="password" name="forvoyez_api_key" id="forvoyez-api-key"
                            class="forvoyez-api-key-input justify-start text-start flex min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm border-gray-300"
                            placeholder="<?php
                            esc_attr_e(
                            	'Enter your API key',
                            	'forvoyez-auto-alt-text-for-images',
                            );
                            ?>
                            " value="<?php echo esc_attr( $api_key ); ?>">
                    <button type="button"
                            class="forvoyez-toggle-visibility inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm"
                            aria-label="
                            <?php
                            esc_attr_e(
                            	'Toggle API key visibility',
                            	'forvoyez-auto-alt-text-for-images',
                            );
                            ?>
                            ">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                            <path fill-rule="evenodd"
                                    d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                    clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="mt-4">
                <button type="button"
                        class="forvoyez-save-api-key inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <?php
                    esc_html_e(
                    	'Save API Key',
                    	'forvoyez-auto-alt-text-for-images',
                    );
                    ?>
                </button>
            </div>
            <div class="mt-4 p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700">
                <p>
                    <?php
                    esc_html_e(
                    	'This plugin uses the ForVoyez API service to generate alt text and metadata for images. By using this plugin, you agree to the ForVoyez Terms of Service and Privacy Policy.',
                    	'forvoyez-auto-alt-text-for-images',
                    );
                    ?>
                </p>
                <p class="mt-2">
                    <?php
                    printf(
                    	wp_kses(
                    		/* translators: %1$s: URL to Terms of Service, %2$s: URL to Privacy Policy, %3$s: URL to Legal Notice */
                    		__(
                    			'Please review the <a href="%1$s" target="_blank" rel="noopener noreferrer" class="underline">Terms of Service</a>, <a href="%2$s" target="_blank" rel="noopener noreferrer" class="underline">Privacy Policy</a>, and <a href="%3$s" target="_blank" rel="noopener noreferrer" class="underline">Legal Notice</a>.',
                    			'forvoyez-auto-alt-text-for-images',
                    		),
                    		array(
                    			'a' => array(
                    				'href'   => array(),
                    				'target' => array(),
                    				'rel'    => array(),
                    				'class'  => array(),
                    			),
                    		),
                    	),
                    	'https://forvoyez.com/app/legals/terms',
                    	'https://forvoyez.com/app/legals/privacy-policy',
                    	'https://forvoyez.com/app/legals/legal-notice',
                    );
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>