<?php
namespace Krokedil\KlarnaExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Assets class for Klarna Express Checkout
 *
 * @package Krokedil\KlarnaExpressCheckout
 */
class Assets {
	/**
	 * The path to the assets directory.
	 *
	 * @var string
	 */
	private $assets_path;

	/**
	 * Settings class instance for the package.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Assets constructor.
	 *
	 * @param Settings $settings The credentials secret from Klarna for KEC.
	 */
	public function __construct( $settings ) {
		$this->assets_path = plugin_dir_url( __FILE__ ) . '../assets/';
		$this->settings    = $settings;

		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 15 );
	}

	/**
	 * Set the container selector for the Klarna Express Checkout container.
	 *
	 * @param string $container The container selector.
	 */
	public function set_container( $container ) {
		$this->container = $container;
	}

	/**
	 * Register scripts.
	 */
	public function register_assets() {
		// Register the style for the cart page.
		wp_register_style( 'kec-cart', $this->assets_path . 'css/kec-cart.css', array(), KlarnaExpressCheckout::VERSION );

		// Register the Klarna Payments library script.
		wp_register_script( 'klarnapayments', 'https://x.klarnacdn.net/kp/lib/v1/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

		wp_register_script( 'kec-cart', $this->assets_path . 'js/kec-cart.js', array(), KlarnaExpressCheckout::VERSION, true );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_assets() {
		if ( is_cart() ) {
			$this->enqueue_cart_assets();
		}
	}

	/**
	 * Enqueue cart scripts.
	 */
	private function enqueue_cart_assets() {
		$params = array(
			'ajax'       => array(
				'get_payload'   => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_get_payload' ),
					'nonce'  => wp_create_nonce( 'kec_get_payload' ),
					'method' => 'POST',
				),
				'auth_callback' => array(
					'url'    => \WC_AJAX::get_endpoint( 'kec_auth_callback' ),
					'nonce'  => wp_create_nonce( 'kec_auth_callback' ),
					'method' => 'POST',
				),
			),
			'client_key' => $this->settings->get_credentials_secret(),
			'theme'      => $this->settings->get_theme(),
			'shape'      => $this->settings->get_shape(),
		);

		wp_localize_script( 'kec-cart', 'kec_cart_params', $params );

		// Enqueue the style for the cart page.
		wp_enqueue_style( 'kec-cart' );

		// Load the Klarna Payments library script before our script.
		wp_enqueue_script( 'klarnapayments' );
		wp_enqueue_script( 'kec-cart' );
	}
}
