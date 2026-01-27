<?php
namespace Krokedil\KlarnaExpressCheckout;

use Krokedil\Klarna\Features;
use Krokedil\Klarna\PluginFeatures;
use Krokedil\KlarnaExpressCheckout\Api\Controllers\Notifications;
use Krokedil\KlarnaExpressCheckout\Blocks\OneStepBlocksIntegration;
use Krokedil\KlarnaExpressCheckout\Blocks\TwoStepBlocksIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Main class for Klarna Express Checkout
 *
 * @package Krokedil\KlarnaExpressCheckout
 */
class KlarnaExpressCheckout {
	public const VERSION = '2.1.0';

	/**
	 * Reference to the Session class.
	 *
	 * @var Session
	 */
	private $session;

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
	 * Reference to the WebhookSetup class.
	 *
	 * @var WebhookSetup
	 */
	private $webhook_setup;

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
	 * @param string $locale      The locale to use for the KEC integration. Defaults to using the browser locale.
	 */
	public function __construct( $options_key = 'woocommerce_klarna_payments_settings', $locale = false ) {
		$this->settings = new Settings( $options_key );

		$this->session             = new Session();
		$this->client_token_parser = new ClientTokenParser( $this->settings() );
		$this->assets              = new Assets( $this->settings(), $locale );
		$this->ajax                = new AJAX( $this->client_token_parser() );
		$this->webhook_setup       = new WebhookSetup( $this );

		add_action( 'init', array( $this, 'maybe_unhook_kp_actions' ), 15 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'add_kec_button' ), 31 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'setup_blocks_integration' ) );
		OneStepCheckout::register_hooks( $this->settings->get_kec_flow() );

		// Register the API controller for handling notifications in the Klarna API.
		$this->register_api_controller();
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
	 * Maybe unhook Klarna Payments actions.
	 *
	 * @return void
	 */
	public function maybe_unhook_kp_actions() {
		$client_token = Session::get_client_token();
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
		$kp_gateway  = WC()->payment_gateways()->get_available_payment_gateways()['klarna_payments'] ?? null;

		if ( empty( $kp_session ) || empty( $kp_checkout ) || empty( $kp_gateway ) ) {
			return;
		}

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
		global $product;

		// Only show the button if KEC is enabled.
		if ( ! $this->settings()->is_enabled() ) {
			return;
		}

		// Skip if we should only show the button on the cart page.
		if ( 'cart' === $this->settings()->get_placements() ) {
			return;
		}

		// Ensure we only do this once per page load.
		if ( did_action( 'woocommerce_single_product_summary' ) > 1 ) {
			return;
		}

		// Only show the button if product is in stock.
		if ( ! $product->is_in_stock() ) {
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
	 * Sets up the Blocks integration class.
	 *
	 * @return void
	 */
	public function setup_blocks_integration() {
		// Only if One step is the selected flow and available.
		$flow = $this->settings->get_kec_flow();
		if ( $flow !== 'one_step' || ! PluginFeatures::is_available( Features::KEC_ONE_STEP ) ) {
			return;
		}

		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) || ! version_compare( \Automattic\WooCommerce\Blocks\Package::get_version(), '4.4.0', '>' ) ) {
			return;
		}
		/**
		 * Filter the compatible blocks for Klarna Express Checkout.
		 */
		$compatible_blocks = apply_filters(
			'kec_compatible_blocks',
			array( 'cart', 'mini-cart' )
		);

		foreach ( $compatible_blocks as $block_name ) {
			add_action(
				"woocommerce_blocks_{$block_name}_block_registration",
				function ( $integration_registry ) {
					$integration_registry->register( new OneStepBlocksIntegration() );
					$integration_registry->register( new TwoStepBlocksIntegration() );
				}
			);
		}
	}

	/**
	 * Get the session class.
	 *
	 * @return Session
	 */
	public function session() {
		return $this->session;
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

	/**
	 * Get the webhook setup class.
	 *
	 * @return WebhookSetup
	 */
	public function webhook_setup() {
		return $this->webhook_setup;
	}

	/**
	 * Register the API controller for handling notifications in the Klarna API.
	 *
	 * @return void
	 */
	public function register_api_controller() {
		Notifications::register_controller();
	}
}
