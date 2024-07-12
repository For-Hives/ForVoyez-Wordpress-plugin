<?php
$settings = new Forvoyez_Settings();
$api_key = $settings->get_api_key();
?>
<div id="forvoyez-api-settings-modal" style="display: none;">
    <div class="forvoyez-modal-content">
        <h2>ForVoyez API Settings</h2>
        <p>Enter your ForVoyez API key below:</p>
        <div class="forvoyez-input-api-key">
            <input type="password" id="forvoyez_api_key" name="forvoyez_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
            <span id="forvoyez-toggle-visibility" class="dashicons dashicons-visibility"></span>
        </div>

        <div class="forvoyez-api-settings-buttons">
            <button id="forvoyez-save-api-key" class="button button-primary">Save API Key</button>
            <button id="forvoyez-close-modal" class="button">Close</button>
        </div>
    </div>
</div>