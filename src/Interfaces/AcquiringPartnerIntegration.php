<?php
namespace Krokedil\KlarnaExpressCheckout\Interfaces;

defined( 'ABSPATH' ) || exit;

interface AcquiringPartnerIntegration {
	/**
	 * Get the acquiring partner key.
	 *
	 * @return string The acquiring partner key.
	 */
	public function get_key();

	/**
	 * Process the state change for a Klarna Express order.
	 *
	 * @param \WC_Order $order The order to process.
	 * @param string    $network_session_token The network session token.
	 * @param array     $network_data  The network data.
	 * @param string    $state The state of the Express order.
	 * @param array     $payload The full payload from Klarna.
	 *
	 * @return string The URL to redirect the customer to.
	 */
	public function process_order_state( \WC_Order $order, string $network_session_token, array $network_data, string $state, array $payload );
}
