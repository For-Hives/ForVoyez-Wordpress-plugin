<div id="forvoyez-api-settings-modal" style="display: none;">
    <div class="forvoyez-modal-content">
        <h2>ForVoyez API Settings</h2>
        <p>Enter your ForVoyez API key below:</p>
        <input type="password" id="forvoyez_api_key" name="forvoyez_api_key" value="<?php echo esc_attr(get_option('forvoyez_api_key')); ?>" class="regular-text">
        <button id="forvoyez-save-api-key" class="button button-primary">Save API Key</button>
        <button id="forvoyez-close-modal" class="button">Close</button>
    </div>
</div>