<?php
namespace Krokedil\KlarnaExpressCheckout\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Base request class.
 */
abstract class Base extends \KP_Requests {
	/**
	 * Sets the base url.
	 *
	 * @param string $country The country code.
	 * @param array  $settings The settings array.
	 */
	protected function get_base_url( $country, $settings ) {
		$testmode     = wc_string_to_bool( $settings['testmode'] ?? 'no' ); // Get the testmode setting.
		$environment  = $testmode ? 'test' : '';

		return "https://api-global.{$environment}.klarna.com/";
	}

	/**
	 * Calculates the auth header for the request.
	 *
	 * @return string
	 */
	public function calculate_auth() {
		return 'basic ' . $this->shared_secret;
	}

	/**
	 * Get the headers for the request.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		$headers = parent::get_request_headers();

		// Create the X-Klarna-Integration-Metadata header for the request.
		$integration_metadata = array(
			'integrator'    => array(
				'name'              => 'WOOCOMMERCE',
				'module_name'       => 'Klarna for WooCommerce',
				'module_version'    => WC_KLARNA_PAYMENTS_VERSION,
			)
		);

		$headers['X-Klarna-Integration-Metadata'] = wp_json_encode( $integration_metadata );

		return $headers;
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	protected function get_request_body() {
		return array();
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	protected function get_request_args() {
		$body = $this->get_request_body();

		$args = array(
			'method'  => $this->method,
			'headers' => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		return $args;
	}
}
