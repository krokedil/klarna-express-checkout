<?php
use Krokedil\KlarnaExpressCheckout\AJAX;
use Krokedil\KlarnaExpressCheckout\Assets;
use Krokedil\KlarnaExpressCheckout\ClientTokenParser;
use Krokedil\KlarnaExpressCheckout\KlarnaExpressCheckout;
use Krokedil\KlarnaExpressCheckout\Settings;

use WP_Mock\Tools\TestCase;

class KlarnaExpressCheckoutTest extends TestCase {
	public function testExample() {
		// Create an instance of KlarnaExpressCheckout
		$klarna = new KlarnaExpressCheckout( 'test_key' );

		$this->assertInstanceOf( KlarnaExpressCheckout::class, $klarna );
		$this->assertInstanceOf( Settings::class, $klarna->settings() );
		$this->assertInstanceOf( ClientTokenParser::class, $klarna->client_token_parser() );
		$this->assertInstanceOf( Assets::class, $klarna->assets() );
		$this->assertInstanceOf( AJAX::class, $klarna->ajax() );
	}

	public function testGetPaymentButtonId() {
		$this->assertEquals( 'kec-pay-button', KlarnaExpressCheckout::get_payment_button_id() );
	}
}
