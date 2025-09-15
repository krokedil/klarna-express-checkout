<?php
namespace Krokedil\KlarnaExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * OneStepCheckout class for Klarna Express Checkout
 *
 * @package Krokedil\KlarnaExpressCheckout
 */
class OneStepCheckout {
	/**
	 * Get the body for the initiate request when the button is pressed.
	 *
	 * @return array
	 */
	public static function get_initiate_body() {
		return array(
			'collectCustomerProfile' => array(
				'profile:name',
				'profile:email',
				'profile:phone',
				'profile:locale',
				'profile:billing_address',
				'profile:country',
			),
			'shippingConfig' => array(
				'mode' => 'EDITABLE',
				//'supportedCountries' => array_keys( WC()->countries->get_shipping_countries() ),
			),
			'amount' => self::format_price( WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax() ),
			'currency' => get_woocommerce_currency(),
			'supplementaryPurchaseData' => array(
				'lineItems' => self::get_cart_items(),
			),
		);
	}

	/**
	 * Set customer address data and calculate the shipping methods for Klarna Express Checkout.
	 * Return the response body for the shippingaddresschange event.
	 *
	 * @param array $shipping_address The shipping address data from Klarna.
	 *
	 * @return array
	 */
	public static function get_shipping_address_change_body( $shipping_address ) {
		// Set the customer address data.
		if ( ! empty( $shipping_address['country'] ?? '' ) ) {
			WC()->customer->set_shipping_country( $shipping_address['country'] ?? '' );
		}

		if ( ! empty( $shipping_address['region'] ?? '' ) ) {
			WC()->customer->set_shipping_state( $shipping_address['region'] ?? '' );
		}

		// Set the customer address data.
		if ( ! empty( $shipping_address['postalCode'] ?? '' ) ) {
			WC()->customer->set_shipping_postcode( $shipping_address['postalCode'] ?? '' );
		}

		if ( ! empty( $shipping_address['city'] ?? '' ) ) {
			WC()->customer->set_shipping_city( $shipping_address['city'] ?? '' );
		}

		// Calculate shipping.
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$packages = WC()->shipping->get_packages();
		$shipping_options = self::get_shipping_options( $packages );

		$selected_shipping_option_reference = WC()->session->get( 'chosen_shipping_methods', array() );
		$selected_shipping_option_reference = ( ! empty( $selected_shipping_option_reference ) ) ? $selected_shipping_option_reference[ 0 ] : '';

		$selected_shipping_option = array_filter( $shipping_options, function( $option ) use ( $selected_shipping_option_reference ) {
			return $option['shippingOptionReference'] === $selected_shipping_option_reference;
		} );

		// If we did not get a selected shipping option, use the first one.
		$selected_shipping_option = ( empty( $selected_shipping_option ) && ! empty( $shipping_options ) ) ? $shipping_options[ 0 ] : reset( $selected_shipping_option );

		$line_items = self::get_cart_items();

		// If we have a selected shipping option, add it to the line items.
		if ( ! empty( $selected_shipping_option ) ) {
			$line_items[] = array(
				'name'              => $selected_shipping_option['displayName'],
				'shippingReference' => $selected_shipping_option['shippingOptionReference'],
				'quantity'          => 1,
				'totalAmount'       => $selected_shipping_option['amount'],
				'totalTaxAmount'    => self::format_price( WC()->cart->get_shipping_tax() ),
			);
		}

		return array(
			'amount' => self::format_price( WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax() ) + $selected_shipping_option['amount'] ?? 0,
			'currency' => get_woocommerce_currency(),
			'lineItems' => $line_items,
			'selectedShippingOptionReference' => $selected_shipping_option['shippingOptionReference'] ?? '',
			'shippingOptions' => $shipping_options,
		);
	}

	/**
	 * Update the selected shipping option and return the response body for the shippingoptionchanged event.
	 *
	 * @param string $selected_option The selected shipping option reference.
	 *
	 * @return array
	 */
	public static function get_changed_shipping_option_response( $selected_option ) {
		// Set the chosen shipping method in the session.
		WC()->session->set( 'chosen_shipping_methods', array( $selected_option ) );

		// Calculate totals.
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		// Get the selected shipping rate from WooCommerce.
		$selected_shipping_methods = WC()->cart->get_shipping_methods();

		/** @var \WC_Shipping_Rate $selected_shipping_method */
		$selected_shipping_method = is_array( $selected_shipping_methods ) && ! empty( $selected_shipping_methods ) ? reset( $selected_shipping_methods ) : null;


		$line_items = self::get_cart_items();

		// If we have a selected shipping option, add it to the line items.
		if ( ! empty( $selected_shipping_method ) ) {
			$line_items[] = array(
				'name'              => $selected_shipping_method->get_label(),
				'shippingReference' => $selected_shipping_method->get_id(),
				'quantity'          => 1,
				'totalAmount'       => self::format_price( $selected_shipping_method->get_cost() + $selected_shipping_method->get_shipping_tax() ),
				'totalTaxAmount'    => self::format_price( WC()->cart->get_shipping_tax() ),
			);
		}

		return array(
			'amount' => self::format_price( WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax() + $selected_shipping_method->get_cost() + $selected_shipping_method->get_shipping_tax() ),
			'lineItems' => $line_items
		);
	}

	/**
	 * Get cart items from the cart contents.
	 *
	 * @return array
	 */
	private static function get_cart_items() {
		$line_items = array();
		foreach( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product->exists() || $product->is_type( 'line_item' ) ) {
				continue;
			}

			$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
			$line_item = array(
				'name'              => $product->get_name(),
				'lineItemReference' => $product->get_sku(),
				'quantity'          => $cart_item['quantity'],
				'totalAmount'       => self::format_price( $cart_item['line_total'] + $cart_item['line_tax'] ), // Convert to cents.
				'totalTaxAmount'    => self::format_price( $cart_item['line_tax'] ), // Convert to cents.
			);

			if ( ! empty( $image_url ) ) {
				$line_item['imageUrl'] = $image_url;
			}

			if ( ! empty( $product->get_global_unique_id() ) ) {
				$line_item['productIdentifier'] = $product->get_global_unique_id();
			}

			$line_items[] = $line_item;
		}

		return $line_items;
	}

	/**
	 * Get the shipping options from the shipping packages.
	 *
	 * @param array $shipping_packages The shipping packages.
	 *
	 * @return array
	 */
	public static function get_shipping_options( $shipping_packages ) {
		$shipping_options = array();
		foreach ( $shipping_packages as $package ) {
			if ( empty( $package['rates'] ) ) {
				continue;
			}

			foreach ( $package['rates'] as $rate ) {
				/** @var \WC_Shipping_Rate $rate */
				$shipping_options[] = array(
					'shippingOptionReference' => $rate->get_id(),
					'amount'                  => self::format_price( $rate->get_cost() + $rate->get_shipping_tax() ),
					'displayName'             => $rate->get_label(),
				);
			}
		}

		return $shipping_options;
	}

	/**
	 * Format a price for Klarna.
	 *
	 * @param float $price The price to format.
	 *
	 * @return int The formatted price in cents.
	 */
	private static function format_price( $price ) {
		return intval( number_format( $price * 100, 0, ".", "" ) );
	}
}
