<?php
/**
 * Class Forvoyez_Settings
 *
 * Handles the plugin settings, including API key encryption and decryption.
 *
 * @package ForVoyez
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit( 'Direct access to this file is not allowed.' );

class Forvoyez_Settings {
    use Forvoyez_Ajax_Verify;

    private $encryption_key;

    public function __construct() {
        $this->encryption_key = $this->generate_site_specific_key();
    }

    public function init() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_forvoyez_save_api_key', array( $this, 'ajax_save_api_key' ) );
        add_action( 'wp_ajax_forvoyez_save_context', array( $this, 'ajax_save_context' ) );
        add_action( 'wp_ajax_forvoyez_save_language', array( $this, 'ajax_save_language' ) );
        add_action( 'wp_ajax_forvoyez_toggle_auto_analyze', array( $this, 'ajax_toggle_auto_analyze' ) );
    }

    public function register_settings() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }

        register_setting(
            'forvoyez_settings',
            'forvoyez_encrypted_api_key',
            array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
            )
        );

        register_setting(
            'forvoyez_settings',
            'forvoyez_context',
            array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
            )
        );

        register_setting(
            'forvoyez_settings',
            'forvoyez_language',
            array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'en',
            )
        );

        register_setting(
            'forvoyez_settings',
            'forvoyez_auto_analyze_enabled',
            array(
				'type'    => 'boolean',
				'default' => false,
            )
        );
    }

    public function ajax_save_context() {
        if ( !$this->verify_ajax_request() ) {
            return;
        }

        $context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : '';
        update_option( 'forvoyez_context', $context );

        wp_send_json_success(
            array(
				'message' => esc_html__( 'Context saved successfully', 'auto-alt-text-for-images' ),
            )
        );
    }

    public function ajax_save_language() {
        if ( !$this->verify_ajax_request() ) {
            return;
        }

        $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'en';
        update_option( 'forvoyez_language', $language );

        wp_send_json_success(
            array(
				'message' => esc_html__( 'Language saved successfully', 'auto-alt-text-for-images' ),
            )
        );
    }

    public function ajax_save_api_key() {
        if ( !$this->verify_ajax_request() ) {
            return;
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        if ( empty( $api_key ) ) {
            wp_send_json_error(
                array(
					'message' => esc_html__( 'API key cannot be empty', 'auto-alt-text-for-images' ),
                ),
                400
            );
        }

        $encrypted_api_key = $this->encrypt( $api_key );
        update_option( 'forvoyez_encrypted_api_key', $encrypted_api_key );

        wp_send_json_success(
            array(
				'message' => esc_html__( 'API key saved successfully', 'auto-alt-text-for-images' ),
            )
        );
    }

    public function ajax_toggle_auto_analyze() {
        if ( !$this->verify_ajax_request() ) {
            return;
        }

        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array(
					'message' => esc_html__( 'Permission denied', 'auto-alt-text-for-images' ),
                ),
                403
            );
            return;
        }

        $enabled = isset( $_POST['enabled'] ) ? $_POST['enabled'] === 'true' : false;
        $result  = update_option( 'forvoyez_auto_analyze_enabled', $enabled ? 'true' : 'false' );

        if ( $result ) {
            wp_send_json_success(
                array(
					'message' => esc_html__( 'Automatic image analysis setting updated successfully', 'auto-alt-text-for-images' ),
                )
            );
        } else {
            wp_send_json_error(
                array(
					'message' => esc_html__( 'Failed to update automatic image analysis setting', 'auto-alt-text-for-images' ),
                )
            );
        }
    }

    public function get_api_key() {
        $encrypted_api_key = get_option( 'forvoyez_encrypted_api_key' );
        if ( empty( $encrypted_api_key ) ) {
            return '';
        }
        return $this->decrypt( $encrypted_api_key );
    }

    public function get_context() {
        return get_option( 'forvoyez_context', '' );
    }

    public function get_language() {
        return get_option( 'forvoyez_language', '' );
    }

    private function encrypt( $data ) {
        $iv        = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            $this->encryption_key,
            0,
            $iv
        );

        return base64_encode( $encrypted . '::' . $iv );
    }

    private function decrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }

        $decoded = base64_decode( $data );
        if ( $decoded === false ) {
            return '';
        }

        [$encrypted_data, $iv] = array_pad( explode( '::', $decoded, 2 ), 2, null );
        if ( $iv === null ) {
            return '';
        }

        $decrypted = openssl_decrypt(
            $encrypted_data,
            'aes-256-cbc',
            $this->encryption_key,
            0,
            $iv
        );

        return $decrypted !== false ? $decrypted : '';
    }

    private function generate_site_specific_key() {
        $site_url        = get_site_url();
        $auth_key        = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
        $secure_auth_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';

        $raw_key = $site_url . $auth_key . $secure_auth_key;
        return hash( 'sha256', $raw_key, true );
    }

    public function sanitize_api_key( $api_key ) {
        return sanitize_text_field( $api_key );
    }
}
