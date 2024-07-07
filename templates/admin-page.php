<div class="wrap">
    <h1>Auto Alt Text for Images</h1>
    <button id="forvoyez-open-settings" class="button button-primary">Configure API Settings</button>
    <?php Forvoyez_Admin::display_api_status(); ?>
    <?php Forvoyez_Admin::display_incomplete_images(); ?>
</div>
<?php include FORVOYEZ_PLUGIN_DIR . 'templates/api-settings-modal.php'; ?>