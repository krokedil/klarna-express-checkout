<?php
namespace Krokedil\KlarnaExpressCheckout\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Krokedil\KlarnaExpressCheckout\Assets;
use Krokedil\KlarnaExpressCheckout\KlarnaExpressCheckout;

/**
 * Class TwoStepBlocksIntegration.
 *
 * Handles the integration with the WooCommerce cart blocks for the two step express checkout.
 *
 * @codeCoverageIgnore
 */
class TwoStepBlocksIntegration implements IntegrationInterface {


	/**
	 * @inheritDoc
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_name() {
		return 'kec-two-step';
	}

	/**
	 * @inheritDoc
	 */
	public function get_script_data() {
		return array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_script_handles() {
		return array( 'kec-two-step-block' );
	}

	/**
	 * @inheritDoc
	 */
	public function initialize() {
		$script_path = Assets::get_assets_path() . 'js/kec-two-step-block.js';
		// Register the script.
		wp_register_script(
			'kec-two-step-block',
			$script_path,
			array( 'wp-element', 'wp-i18n', 'wp-blocks', 'wc-blocks-registry', 'jquery' ),
			KlarnaExpressCheckout::VERSION,
			true
		);
	}
}
