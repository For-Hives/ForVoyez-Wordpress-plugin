class Forvoyez_Admin {
public function init() {
add_action('admin_menu', array($this, 'add_menu_item'));
add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
}

public function add_menu_item() {
add_options_page(
'Auto Alt Text Settings',
'Auto Alt Text',
'manage_options',
'forvoyez-auto-alt-text',
array($this, 'render_admin_page')
);
}

public function enqueue_admin_scripts($hook) {
if ('settings_page_forvoyez-auto-alt-text' !== $hook) {
return;
}

wp_enqueue_style('forvoyez-admin-styles', FORVOYEZ_PLUGIN_URL . 'assets/css/admin-style.css', array(), '1.0.0');
wp_enqueue_script('forvoyez-admin-script', FORVOYEZ_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), '1.0.0', true);
wp_enqueue_script('forvoyez-api-settings', FORVOYEZ_PLUGIN_URL . 'assets/js/api-settings.js', array('jquery'), '1.0.0', true);

wp_localize_script('forvoyez-admin-script', 'forvoyezData', array(
'ajaxurl' => admin_url('admin-ajax.php'),
'nonce' => wp_create_nonce('forvoyez_nonce')
));
}

public function render_admin_page() {
include FORVOYEZ_PLUGIN_DIR . 'templates/admin-page.php';
}
}