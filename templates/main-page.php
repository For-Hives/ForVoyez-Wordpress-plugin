<?php
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
?>
<div class="wrap">
    <h1 class="text-2xl font-bold mb-4"><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="border-b border-gray-200 mt-10">
        <nav class="-mb-px flex" aria-label="<?php esc_attr_e('Tabs', 'forvoyez-auto-alt-text-for-images'); ?>">
            <a href="?page=forvoyez-auto-alt-text&tab=dashboard" class="<?php echo $active_tab == 'dashboard' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                <?php _e('Dashboard', 'forvoyez-auto-alt-text-for-images'); ?>
            </a>
            <a href="?page=forvoyez-auto-alt-text&tab=manage" class="<?php echo $active_tab == 'manage' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                <?php _e('Manage Images', 'forvoyez-auto-alt-text-for-images'); ?>
            </a>
            <a href="?page=forvoyez-auto-alt-text&tab=configuration" class="<?php echo $active_tab == 'configuration' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm">
                <?php _e('Configuration', 'forvoyez-auto-alt-text-for-images'); ?>
            </a>
        </nav>
    </div>

    <div class="mt-6">
        <?php
        switch ($active_tab) {
            case 'manage':
                include FORVOYEZ_PLUGIN_DIR . 'templates/admin-page.php';
                break;
            case 'configuration':
                include FORVOYEZ_PLUGIN_DIR . 'templates/configuration-tab.php';
                break;
            default:
                include FORVOYEZ_PLUGIN_DIR . 'templates/dashboard-tab.php';
                break;
        }
        ?>
    </div>
</div>