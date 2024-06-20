<?php
namespace Krokedil\KlarnaExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Settings class for the package.
 *
 * @package Krokedil\KlarnaExpressCheckout
 */
class Settings {
	/**
	 * If KEC is enabled or not.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * The options key to get the KEC settings from.
	 *
	 * @var string
	 */
	private $options_key;

	/**
	 * The options array from the options key.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Class constructor.
	 *
	 * @param string $options_key The options key to get the KEC settings from.
	 *
	 * @return void
	 */
	public function __construct( $options_key ) {
		// Automatically add the settings to the Klarna Payments settings page.
		add_filter( 'wc_gateway_klarna_payments_settings', array( $this, 'add_settings' ), 10 );

		// Set the options for where to get the KEC settings from.
		$this->options_key = $options_key;

		// Get the options array from the options key.
		$this->options = $this->get_settings();
	}

	/**
	 * Add the settings to a settings array passed.
	 *
	 * @param array $settings The settings.
	 *
	 * @return array
	 */
	public function add_settings( $settings ) {
		$settings = array_merge( $settings, $this->get_setting_fields() );

		return $settings;
	}

	/**
	 * Get the KEC settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( $this->options_key, array() );

		// Return only the KEC settings.
		return array(
			'kec_enabled'            => $settings['kec_enabled'] ?? 'no',
			'kec_credentials_secret' => $settings['kec_credentials_secret'] ?? '',
			'kec_theme'              => $settings['kec_theme'] ?? 'default',
			'kec_shape'              => $settings['kec_shape'] ?? 'default',
		);
	}

	/**
	 * Get the enabled status for KEC.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->options['kec_enabled'] ?? 'no';
	}

	/**
	 * Get the credentials secret from Klarna for KEC.
	 *
	 * @return string
	 */
	public function get_credentials_secret() {
		if ( function_exists( 'kp_get_client_id' ) ) {
			return kp_get_client_id();
		}

		return $this->options['kec_credentials_secret'] ?? '';
	}

	/**
	 * Get the theme for the Klarna Express Checkout.
	 *
	 * @return string
	 */
	public function get_theme() {
		return $this->options['kec_theme'] ?? 'default';
	}

	/**
	 * Get the shape for the Klarna Express Checkout.
	 *
	 * @return string
	 */
	public function get_shape() {
		return $this->options['kec_shape'] ?? 'default';
	}

	/**
	 * Get the setting fields.
	 *
	 * @return array
	 */
	public function get_setting_fields() {
		return array(
			'kec_settings' => array(
				'id'          => 'kec_settings',
				'title'       => 'Express Checkout',
				'description' => __( 'An improved way to drive shoppers straight to the checkout, with all their preferences already set.', 'klarna-express-checkout' ),
				'links'       => array(
					array(
						'url'   => 'https://docs.klarna.com/express-checkout/',
						'title' => __( 'Learn more', 'klarna-express-checkout' ),
					),
					array(
						'url'   => 'https://docs.klarna.com/express-checkout/',
						'title' => __( 'Documentation', 'klarna-express-checkout' ),
					),
				),
				'type'        => 'kp_section_start',
			),
			'kec_enabled'  => array(
				'title'   => __( 'Enable/Disable', 'klarna-express-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Klarna Express Checkout', 'klarna-express-checkout' ),
				'default' => 'no',
			),
			'kec_theme'    => array(
				'title'       => __( 'Theme', 'klarna-express-checkout' ),
				'type'        => 'select',
				'description' => __( 'Select the theme for the Klarna Express Checkout.', 'klarna-express-checkout' ),
				'desc_tip'    => true,
				'options'     => array(
					'default' => __( 'Default', 'klarna-express-checkout' ),
					'dark'    => __( 'Dark', 'klarna-express-checkout' ),
					'light'   => __( 'Light', 'klarna-express-checkout' ),
				),
				'default'     => 'default',
			),
			'kec_shape'    => array(
				'title'       => __( 'Shape', 'klarna-express-checkout' ),
				'type'        => 'select',
				'description' => __( 'Select the shape for the Klarna Express Checkout.', 'klarna-express-checkout' ),
				'desc_tip'    => true,
				'options'     => array(
					'default' => __( 'Default', 'klarna-express-checkout' ),
					'rect'    => __( 'Rectangular', 'klarna-express-checkout' ),
					'pill'    => __( 'Pill', 'klarna-express-checkout' ),
				),
				'default'     => 'default',
			),
			'kec_end'      => array(
				'type'     => 'kp_section_end',
				'previews' => array(
					array(
						'title' => __( 'Preview', 'klarna-express-checkout' ),
						'image' => WC_KLARNA_PAYMENTS_PLUGIN_URL . '/assets/img/kp-general-preview.png',
					),
				),
			),
		);
	}
}
