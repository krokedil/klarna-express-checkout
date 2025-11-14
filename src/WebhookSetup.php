<?php
namespace Krokedil\KlarnaExpressCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookSetup
 *
 * @package Krokedil\KlarnaExpressCheckout
 */
class WebhookSetup {
	const CREATE_WEBHOOK_ACTION  = 'create_webhook';
	const SIMULATE_WEBHOOK_ACTION = 'simulate_webhook';
	const DELETE_WEBHOOK_ACTION  = 'delete_webhook';

	/**
	 * Instance of the KlarnaExpressCheckout class.
	 *
	 * @var KlarnaExpressCheckout
	 */
	private $kec;

	/**
	 * Any errors generated during the webhook setup.
	 *
	 * @var array
	 */
	private $errors = array();

	/**
	 * Any messages generated during the webhook setup.
	 *
	 * @var array
	 */
	private $messages = array();

	/**
	 * Class constructor.
	 *
	 * @param KlarnaExpressCheckout $kec Instance of the main KEC class.
	 *
	 * @return void
	 */
	public function __construct( $kec ) {
		$this->kec = $kec;
		add_action( 'admin_init', array( $this, 'maybe_process_webhook_action' ) );
	}

	/**
	 * Maybe process a webhook action.
	 *
	 * @return void
	 */
	public function maybe_process_webhook_action() {
		if ( ! isset( $_GET['kec_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$actions = array( self::CREATE_WEBHOOK_ACTION, self::SIMULATE_WEBHOOK_ACTION, self::DELETE_WEBHOOK_ACTION );
		$action = sanitize_text_field( wp_unslash( $_GET['kec_action'] ) );
		if ( ! in_array( $action, $actions, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}


		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_GET['nonce'] ), "kec_$action" ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		try {
			// Process the action based on the kec_action parameter.
			switch ( $action ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				case self::CREATE_WEBHOOK_ACTION:
					$this->create_webhook();
					break;
				case self::SIMULATE_WEBHOOK_ACTION:
					$this->simulate_webhook();
					break;
				case self::DELETE_WEBHOOK_ACTION:
					$this->delete_webhook();
					break;
			}

			$this->redirect_to_settings();
		} catch ( \Exception $e ) {
			$this->add_error( $e->getMessage() );
		}
	}

	/**
	 * Add a success message.
	 *
	 * @param string $message The message to show.
	 *
	 * @return void
	 */
	private function add_message( $message ) {
		$this->messages[] = $message;
	}

	/**
	 * Add an error message.
	 *
	 * @param string $message The message to show.
	 *
	 * @return void
	 */
	private function add_error( $message ) {
		$this->errors[] = $message;
	}

	/**
	 * Trigger a test webhook to be sent to the store.
	 *
	 * @return void
	 */
	private function simulate_webhook() {
		$webhook = $this->kec->settings()->get_webhook();

		if ( empty( $webhook ) ) {
			return;
		}

		$response = Requests::simulate_webhook( $webhook['webhook_id'], 'payment.request.state-change.submitted', 'v2' );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( __( 'Could not send test webhook', 'klarna-express-checkout' ) );
		}

		// Show an admin notice that the webhook was sent.
		$this->add_message( __( 'Test webhook sent successfully.', 'klarna-express-checkout' ) );
	}

	/**
	 * Delete the webhook in Klarna.
	 *
	 * @return void
	 */
	private function delete_webhook() {
		$signing_key = $this->kec->settings()->get_signing_key();
		$webhook     = $this->kec->settings()->get_webhook();

		if ( empty( $signing_key ) && empty( $webhook ) ) {
			return;
		}

		// Delete both the webhook and signing key in Klarna.
		if ( ! empty( $webhook ) ) {
			$delete_webhook_response = Requests::delete_webhook( $webhook['webhook_id'] );

			// If the webhook could not be deleted, throw an exception.
			if ( is_wp_error( $delete_webhook_response ) ) {
				throw new \Exception( __( 'Could not remove webhook', 'klarna-express-checkout' ) );
			}

			delete_option( 'kec_webhook' );
		}

		if ( ! empty( $signing_key ) ) {
			$delete_signing_key_response = Requests::delete_signing_key( $signing_key['signing_key_id'] );

			// If the signing key could not be deleted, throw an exception.
			if ( is_wp_error( $delete_signing_key_response ) ) {
				throw new \Exception( __( 'Could not remove signing key', 'klarna-express-checkout' ) );
			}

			delete_option( 'kec_signing_key' );
		}

		$this->add_message( __( 'Webhook removed successfully.', 'klarna-express-checkout' ) );
	}

	/**
	 * Create the signing key for the webhook in Klarna.
	 *
	 * @return array|\WP_Error The response from Klarna.
	 */
	public function create_signing_key() {
		$response = Requests::create_signing_key();

		if ( is_wp_error( $response ) ) {
			throw new \Exception( __( 'Could not create signing key', 'klarna-express-checkout' ) );
		}

		// Save the signing key to the options, and the options array.
		$this->kec->settings()->update_setting( 'kec_signing_key', $response );

		return $response;
	}

	/**
	 * Create the webhook in Klarna.
	 *
	 * @return void
	 */
	public function create_webhook() {
		// If we already have the webhook, don't create new once.
		$stored_webhook = $this->kec->settings()->get_webhook();
		if ( ! empty( $stored_webhook ) ) {
			return;
		}

		// Create the signing key needed for the webhook if we don't already have one.
		$signing_key = $this->kec->settings()->get_signing_key();
		if ( empty( $signing_key ) ) {
			$signing_key = $this->create_signing_key();
		}

		// If we had a WP_Error, don't continue.
		if ( is_wp_error( $signing_key ) ) {
			throw new \Exception( __( 'Could not create a signing key with Klarna for the webhook.', 'klarna-express-checkout' ) );
		}

		$url           = get_rest_url( null, '/klarna/v1/kec/notifications' );
		$event_types   = array(
			'payment.request.state-change.completed',
			'payment.request.state-change.expired'
		);
		$event_version = 'v2';
		$signing_key   = $this->kec->settings()->get_signing_key();

		$response = Requests::create_webhook( $url, $event_types, $event_version, $signing_key['signing_key_id'] );

		if( is_wp_error( $response ) ) {
			throw new \Exception( __( 'Could not create webhook with Klarna', 'klarna-express-checkout' ) );
		}

		// Update the settings with the new webhook.
		$this->kec->settings()->update_setting( 'kec_webhook', $response );

		// Show an admin notice that the webhook were created.
		$this->add_message( __( 'Webhook created successfully.', 'klarna-express-checkout' ) );
	}

	/**
	 * Redirect to the settings page.
	 *
	 * @return void
	 */
	private function redirect_to_settings() {
		$settings_url = KP_WC()->get_setting_link();
		if ( ! empty( $this->errors ) ) {
			$settings_url = add_query_arg( 'kec_errors', base64_encode( wp_json_encode( $this->errors ) ), $settings_url );
		}
		if ( ! empty( $this->messages ) ) {
			$settings_url = add_query_arg( 'kec_messages', base64_encode( wp_json_encode( $this->messages ) ), $settings_url );
		}

		// Append the anchor to the settings URL to jump to the KEC settings section.
		$settings_url = "$settings_url#klarna-payments-settings-kec_settings";
		wp_safe_redirect( $settings_url );
		exit;
	}
}
