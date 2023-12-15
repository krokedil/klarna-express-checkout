<?php
namespace Krokedil\KlarnaExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Main class for Klarna Express Checkout
 *
 * @package Krokedil\KlarnaExpressCheckout
 */
class KlarnaExpressCheckout {
	const VERSION = '1.0.0';

	/**
	 * Reference to the Assets class.
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Reference to the AJAX class.
	 *
	 * @var AJAX
	 */
	private $ajax;

	/**
	 * Reference to the ClientTokenParser class.
	 *
	 * @var ClientTokenParser
	 */
	private $client_token_parser;

	/**
	 * Reference to the Settings class.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The ID of the payment button element.
	 *
	 * @var string
	 */
	private static $payment_button_id = 'kec-pay-button';

	/**
	 * KlarnaExpressCheckout constructor.
	 *
	 * @param string $options_key The option key to get the KEC settings from.
	 */
	public function __construct( $options_key = 'woocommerce_klarna_payments_settings' ) {
		$this->settings = new Settings( $options_key );

		$this->client_token_parser = new ClientTokenParser( $this->settings() );
		$this->assets              = new Assets( $this->settings() );
		$this->ajax                = new AJAX( $this->client_token_parser() );

		add_action( 'init', array( $this, 'maybe_unhook_kp_actions' ), 15 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'add_kec_button' ), 31 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'unset_token' ), 1 );
	}

	/**
	 * Set the client token to the WooCommerce session for the current cart contents only.
	 *
	 * @param string $token The cart token.
	 */
	public static function set_client_token( $token ) {
		// Ensure session is initialized.
		if ( ! WC()->session ) {
			return false;
		}

		// Set the client token in a session using the cart_hash as a key.
		WC()->session->set( 'kec_client_token', $token );
	}

	/**
	 * Get the client token from the WooCommerce session.
	 *
	 * @return string|bool
	 */
	public static function get_client_token() {
		// Ensure session is initialized.
		if ( ! WC()->session ) {
			return false;
		}

		// Get the client token from the session using the cart_hash as a key.
		return WC()->session->get( 'kec_client_token' );
	}

	/**
	 * Get the ID of the payment button element.
	 *
	 * @return string
	 */
	public static function get_payment_button_id() {
		return self::$payment_button_id;
	}

	/**
	 * Unset the client token from the WooCommerce session.
	 *
	 * @return void
	 */
	public static function unset_token() {
		// Ensure session is initialized.
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->__unset( 'kec_client_token' );
	}


	/**
	 * Maybe unhook Klarna Payments actions.
	 *
	 * @return void
	 */
	public function maybe_unhook_kp_actions() {
		$client_token = self::get_client_token();
		if ( empty( $client_token ) ) {
			return;
		}

		// Ensure Klarna Payments exists.
		if ( ! function_exists( 'KP_WC' ) ) {
			return;
		}

		// Get the instances of the Klarna Payments classes we need to unhook actions from.
		$kp_session  = KP_WC()->session;
		$kp_checkout = KP_WC()->checkout;
		$kp_gateway  = WC()->payment_gateways()->get_available_payment_gateways()['klarna_payments'];

		// Unhook session actions.
		remove_action( 'woocommerce_after_calculate_totals', array( $kp_session, 'get_session' ), 999999 );

		// Unhook checkout actions.
		remove_filter( 'woocommerce_update_order_review_fragments', array( $kp_checkout, 'add_token_fragment' ) );
		remove_action( 'woocommerce_review_order_before_submit', array( $kp_checkout, 'html_client_token' ) );
		remove_action( 'woocommerce_pay_order_before_submit', array( $kp_checkout, 'html_client_token' ) );

		// Unhook gateway actions.
		remove_action( 'wc_get_template', array( $kp_gateway, 'override_kp_payment_option' ) );

		// Ensure we don't enqueue the KP scripts for the checkout page.
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_kp_scripts' ), 15 );
	}

	/**
	 * Add the Klarna Express Checkout button to the product page.
	 *
	 * @return void
	 */
	public function add_kec_button() {
		// Only show the button if KEC is enabled.
		if ( ! $this->settings()->is_enabled() ) {
			return;
		}

		// Ensure we only do this once per page load.
		if ( did_action( 'woocommerce_single_product_summary' ) > 1 ) {
			return;
		}

		?>
		<div id="kec-pay-button"></div>
		<?php
	}

	/**
	 * Dequeue the Klarna Payments scripts.
	 *
	 * @return void
	 */
	public function dequeue_kp_scripts() {
		wp_dequeue_script( 'klarna_payments' );
	}

	/**
	 * Get the assets class.
	 *
	 * @return Assets
	 */
	public function assets() {
		return $this->assets;
	}

	/**
	 * Get the AJAX class.
	 *
	 * @return AJAX
	 */
	public function ajax() {
		return $this->ajax;
	}

	/**
	 * Get the ClientTokenParser class.
	 *
	 * @return ClientTokenParser
	 */
	public function client_token_parser() {
		return $this->client_token_parser;
	}

	/**
	 * Get the settings class.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}
}
