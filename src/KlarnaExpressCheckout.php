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
		$this->settings            = new Settings( $options_key );
		$this->client_token_parser = new ClientTokenParser( $this->settings() );
		$this->assets              = new Assets( $this->settings() );
		$this->ajax                = new AJAX( $this->client_token_parser() );
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
