<?php
$settings = new Forvoyez_Settings();
$api_key = $settings->get_api_key();
?>
<div id="forvoyez-api-settings-modal" style="display: none;">
    <div class="forvoyez-modal-content">
        <h2>ForVoyez API Settings</h2>
        <p>Enter your ForVoyez API key below:</p>
        <input type="text" id="forvoyez_api_key" name="forvoyez_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
        <button id="forvoyez-save-api-key" class="button button-primary">Save API Key</button>
        <button id="forvoyez-close-modal" class="button">Close</button>
    </div>
</div>