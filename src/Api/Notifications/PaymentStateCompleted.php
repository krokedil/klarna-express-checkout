<?php
namespace Krokedil\KlarnaExpressCheckout\Api\Notifications;

defined( 'ABSPATH' ) || exit;

class PaymentStateCompleted extends Handler {
	/**
	 * The event type for the notification.
	 *
	 * @var string
	 */
	protected $event_type = 'payment.request.state-change.completed';

	/**
	 * The version for the notification.
	 *
	 * @var string
	 */
	protected $event_version = 'v2';

	/**
	 * @inheritDoc
	 */
	public function handle_notification( $payload ) {
		// Ensure the state is 'COMPLETED' before processing.
		if ( 'COMPLETED' !== $payload['state'] ) {
			return; // Maybe throw an error or log this incident.
		}

		$payment_request_id        = $payload['payment_request_id'] ?? null;
		$interoperability_token    = $payload['interoperability_token'] ?? null;

		if ( ! $payment_request_id || ! $interoperability_token ) {
			throw new \WP_Exception( 'Missing required fields in the payload.' );
		}

		// Get the order by payment request ID.
		$order = $this->get_wc_order_by_payment_request_id( $payment_request_id );

		// Set the order address data.
		$this->set_address( $order, $payload );

		//do_action( 'kec_process_order', $order, $interoperability_token, [], $payload['state'], $payload );
		$redirect_url           = $order->get_checkout_order_received_url();
		$ap_partner_integration = $this->get_acquiring_partner_integration();
		if ( $ap_partner_integration ) {
			$redirect_url = $ap_partner_integration->process_order_state(
				$order,
				$interoperability_token,
				[],
				$payload['state'],
				$payload
			);
		} else {
			do_action( 'kec_process_order', $order, $interoperability_token, [], $payload['state'], $payload );
		}

		// Store the redirect URL in order to redirect the customer when they get to the confirmation page.
		$order->update_meta_data( '_kec_redirect_url', $redirect_url );

		// Save the order.
		$order->save();

		return null;
	}

	/**
	 * Set the customer address to the order from the payload data.
	 *
	 * @param \WC_Order $order The WooCommerce order to update. Passed by reference.
	 * @param array $payload The payload from the notification.
	 *
	 * @return self The current instance for method chaining.
	 */
	protected function set_address( &$order, $payload ) {
		$billing_customer  = $payload['klarna_customer']['customer_profile'] ?? [];
		$billing_address   = $payload['klarna_customer']['customer_profile']['address'] ?? [];
		$shipping_customer = $payload['shipping']['recipient'] ?? [];
		$shipping_address  = $payload['shipping']['address'] ?? [];

		$this->set_address_field( $order, $billing_customer['given_name'] ?? '', 'first_name', 'billing' )
			 ->set_address_field( $order, $billing_customer['family_name'] ?? '', 'last_name', 'billing' )
			 ->set_address_field( $order, $billing_customer['email'] ?? '', 'email', 'billing' )
			 ->set_address_field( $order, $billing_customer['phone'] ?? '', 'phone', 'billing' )
			 ->set_address_field( $order, $billing_address['street_address'] ?? '', 'address_1', 'billing' )
			 ->set_address_field( $order, $billing_address['street_address2'] ?? '', 'address_2', 'billing' )
			 ->set_address_field( $order, $billing_address['postal_code'] ?? '', 'postcode', 'billing' )
			 ->set_address_field( $order, $billing_address['city'] ?? '', 'city', 'billing' )
			 ->set_address_field( $order, $billing_address['region'] ?? '', 'state', 'billing' )
			 ->set_address_field( $order, $billing_address['country'] ?? '', 'country', 'billing' )
			 ->set_address_field( $order, $shipping_customer['given_name'] ?? '', 'first_name', 'shipping' )
			 ->set_address_field( $order, $shipping_customer['family_name'] ?? '', 'last_name', 'shipping' )
			 ->set_address_field( $order, $shipping_customer['email'] ?? '', 'email', 'shipping' )
			 ->set_address_field( $order, $shipping_customer['phone'] ?? '', 'phone', 'shipping' )
			 ->set_address_field( $order, $shipping_address['street_address'] ?? '', 'address_1', 'shipping' )
			 ->set_address_field( $order, $shipping_address['street_address2'] ?? '', 'address_2', 'shipping' )
			 ->set_address_field( $order, $shipping_address['postal_code'] ?? '', 'postcode', 'shipping' )
			 ->set_address_field( $order, $shipping_address['city'] ?? '', 'city', 'shipping' )
			 ->set_address_field( $order, $shipping_address['region'] ?? '', 'state', 'shipping' )
			 ->set_address_field( $order, $shipping_address['country'] ?? '', 'country', 'shipping' );

		return $this;
	}

	/**
	 * Set a specific address field to the order if it exists in the provided address data.
	 *
	 * @param \WC_Order $order The WooCommerce order to update. Passed by reference.
	 * @param mixed $value The value to set.
	 * @param string $field The order field to update.
	 * @param string $address_type The type of address ('billing' or 'shipping').
	 *
	 * @return self
	 */
	protected function set_address_field( &$order, $value, $field, $address_type ) {
		if ( ! empty( $value ) ) {
			$method = "set_{$address_type}_{$field}";
			if ( method_exists( $order, $method ) ) { // Ensure the method exists before we call it.
				$order->$method( $value );
			} else { // Fallback to using meta data if the method doesn't exist.
				$order->update_meta_data( "{$address_type}_{$field}", $value );
			}
		}

		return $this;
	}
}
