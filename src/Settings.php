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
		add_action( 'admin_init', array( $this, 'maybe_process_webhook_action' ) );

		// Automatically add the settings to the Klarna Payments settings page.
		add_filter( 'wc_gateway_klarna_payments_settings', array( $this, 'add_settings' ), 10 );

		add_action( 'woocommerce_admin_field_kec_webhook_button', array( __CLASS__, 'webhook_button' ) );
		add_filter( 'woocommerce_generate_kec_webhook_button_html', array( __CLASS__, 'webhook_button_html' ), 10, 3 );

		// Set the options for where to get the KEC settings from.
		$this->options_key = $options_key;

		// Get the options array from the options key.
		$this->options = $this->get_settings();

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

		$actions = array(
			'create_webhook',
			'simulate_webhook',
			'delete_webhook'
		);
		if ( ! in_array( $_GET['kec_action'], $actions, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_GET['nonce'] ), 'kec_' . sanitize_text_field( $_GET['kec_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		try{
			switch ( $_GET['kec_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				case 'create_webhook':
					$this->create_webhook();
					break;
				case 'simulate_webhook':
					$this->simulate_webhook();
					break;
				case 'delete_webhook':
					$this->delete_webhook();
					break;
			}
		} catch ( \Exception $e ) {
			// Catch any exceptions and show an admin notice.
			$message = $e->getMessage();
			add_action( 'admin_notices', function() use ( $message ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( 'Klarna Express Checkout: ' . $message ); ?></p>
				</div>
				<?php
			} );
		}
	}

	/**
	 * Show a success notice.
	 *
	 * @param string $message The message to show.
	 *
	 * @return void
	 */
	private function show_success_notice( $message ) {
		add_action( 'admin_notices', function() use ( $message ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( 'Klarna Express Checkout: ' . $message ); ?></p>
			</div>
			<?php
		} );
	}

	/**
	 * Trigger a test webhook to be sent to the store.
	 *
	 * @return void
	 */
	private function simulate_webhook() {
		$webhook = $this->get_webhook();

		if ( empty( $webhook ) ) {
			return;
		}

		$response = Requests::simulate_webhook( $webhook['webhook_id'], 'payment.request.state-change.submitted', 'v2' );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Could not send test webhook: ' . $response->get_error_message() );
		}

		// Show an admin notice that the webhook was sent.
		$this->show_success_notice( 'Test webhook sent successfully.' );
	}

	/**
	 * Delete the webhook in Klarna.
	 *
	 * @return void
	 */
	private function delete_webhook() {
		$signing_key = $this->get_signing_key();
		$webhook     = $this->get_webhook();

		if ( empty( $signing_key ) && empty( $webhook ) ) {
			return;
		}

		// Delete both the webhook and signing key in Klarna.
		if ( ! empty( $webhook ) ) {
			$delete_webhook_response = Requests::delete_webhook( $webhook['webhook_id'] );

			if ( is_wp_error( $delete_webhook_response ) ) {
				throw new \Exception( 'Could not delete webhook: ' . $delete_webhook_response->get_error_message() );
			}

			delete_option( 'kec_webhook' );
		}

		if ( ! empty( $signing_key ) ) {
			$delete_signing_key_response = Requests::delete_signing_key( $signing_key['signing_key_id'] );

			if ( is_wp_error( $delete_signing_key_response ) ) {
				throw new \Exception( 'Could not delete signing key: ' . $delete_signing_key_response->get_error_message() );
			}

			delete_option( 'kec_signing_key' );
		}
	}

	/**
	 * Create the signing key for the webhook in Klarna.
	 *
	 * @return array|\WP_Error The response from Klarna.
	 */
	public function create_signing_key() {
		$response = Requests::create_signing_key();

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Could not create signing key: ' . $response->get_error_message() );
		}

		// Save the signing key to the options, and the options array.
		update_option( 'kec_signing_key', $response );
		$this->options['kec_signing_key'] = $response;

		return $response;
	}

	/**
	 * Create the webhook in Klarna.
	 *
	 * @return void
	 */
	public function create_webhook() {
		// If we already have the webhook, don't create new once.
		$stored_webhook = get_option( 'kec_webhook', array() );
		if ( ! empty( $stored_webhook ) ) {
			return;
		}

		// Create the signing key needed for the webhook if we don't already have one.
		$signing_key = $this->get_signing_key();
		if ( empty( $signing_key ) ) {
			$signing_key = $this->create_signing_key();
		}

		// If we had a WP_Error, don't continue.
		if ( is_wp_error( $signing_key ) ) {
			return;
		}

		$url           = get_rest_url( null, '/klarna/v1/webhooks' );
		$event_types   = array(
			'payment.request.state-change.submitted',
			'payment.request.state-change.completed',
			'payment.request.state-change.expired'
		);
		$event_version = 'v2';
		$signing_key   = $this->get_signing_key();

		$response = Requests::create_webhook( $url, $event_types, $event_version, $signing_key['signing_key_id'] );

		if( is_wp_error( $response ) ) {
			// Show an admin notice that the webhook could not be created.
			$message = $response->get_error_message();
			add_action( 'admin_notices', function() use ( $message ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( 'Klarna Express Checkout: Could not create webhook. ' . $message ); ?></p>
				</div>
				<?php
			} );
			return;
		}

		update_option( 'kec_webhook', $response );
		$this->options['kec_webhook'] = $response;

		// Show an admin notice that the webhook were created.
		$this->show_success_notice( 'Webhook created successfully.' );
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
			'kec_theme'              => $settings['kec_theme'] ?? 'dark',
			'kec_shape'              => $settings['kec_shape'] ?? 'default',
			'kec_placement'          => $settings['kec_placement'] ?? 'both',
			'kec_webhook'            => get_option( 'kec_webhook', array() ),
			'kec_signing_key'        => get_option( 'kec_signing_key', array() ),
		);
	}

	/**
	 * Get the enabled status for KEC.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$kp_unavailable_feature_ids = get_option( 'kp_unavailable_feature_ids', array() );
		if ( in_array( 'kec_settings', $kp_unavailable_feature_ids ) ) {
			return false;
		}

		return 'yes' === $this->options['kec_enabled'] ?? 'no';
	}

	/**
	 * Get the credentials secret from Klarna for KEC.
	 *
	 * @return string
	 */
	public function get_credentials_secret() {
		if ( function_exists( 'kp_get_client_id_by_currency' ) ) {
			return kp_get_client_id_by_currency();
		}

		return $this->options['kec_credentials_secret'] ?? '';
	}

	/**
	 * Get the theme for the Klarna Express Checkout.
	 *
	 * @return string
	 */
	public function get_theme() {
		return $this->options['kec_theme'] ?? 'dark';
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
	 * Get the placements for the Klarna Express Checkout.
	 *
	 * @return string
	 */
	public function get_placements() {
		return $this->options['kec_placement'] ?? 'both';
	}

	/**
	 * Get the webhook created in Klarna.
	 *
	 * @return array
	 */
	public function get_webhook() {
		return $this->options['kec_webhook'] ?? array();
	}

	/**
	 * Get the signing key for the webhook.
	 *
	 * @return array
	 */
	public function get_signing_key() {
		return $this->options['kec_signing_key'] ?? array();
	}

	/**
	 * Is Klarna in testmode or not.
	 *
	 * @return bool
	 */
	public function is_testmode() {
		$kp_settings = get_option( 'woocommerce_klarna_payments_settings' );
		$testmode    = $kp_settings['testmode'] ?? 'no';

		return wc_string_to_bool( $testmode );
	}

	/**
	 * Get the setting fields.
	 *
	 * @return array
	 */
	public function get_setting_fields() {
		$created_webhook = get_option( 'kec_webhook', array() );
		$webhook_created = ! empty( $created_webhook );

		return array(
			'kec_settings'  => array(
				'id'          => 'kec_settings',
				'title'       => 'Express Checkout',
				'description' => __( 'Offer a 5x faster check-out process that lowers the threshold for shoppers to complete a purchase.', 'klarna-express-checkout' ),
				'links'       => array(
					array(
						'url'   => 'https://docs.klarna.com/express-checkout/',
						'title' => __( 'Documentation', 'klarna-express-checkout' ),
					),
				),
				'type'        => 'kp_section_start',
			),
			'kec_enabled'   => array(
				'title'   => __( 'Enable/Disable', 'klarna-express-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Klarna Express Checkout', 'klarna-express-checkout' ),
				'default' => 'no',
			),
			'kec_info'      => array(
				'type'        => 'kp_text_info',
				'title'       => __( 'Placements & button style', 'klarna-express-checkout' ),
				'description' => __( 'Tailor the express checkout button to fit your brand by adjusting the button theme, shape and selecting placements.', 'klarna-express-checkout' ),
			),
			'kec_theme'     => array(
				'title'       => __( 'Theme', 'klarna-express-checkout' ),
				'type'        => 'select',
				'description' => __( 'Select the theme for the Klarna Express Checkout.', 'klarna-express-checkout' ),
				'desc_tip'    => true,
				'options'     => array(
					'dark'     => __( 'Dark', 'klarna-express-checkout' ),
					'light'    => __( 'Light', 'klarna-express-checkout' ),
					'outlined' => __( 'Outlined', 'klarna-express-checkout' ),
				),
				'default'     => 'dark',
			),
			'kec_shape'     => array(
				'title'       => __( 'Shape', 'klarna-express-checkout' ),
				'type'        => 'select',
				'description' => __( 'Select the shape for the Klarna Express Checkout.', 'klarna-express-checkout' ),
				'desc_tip'    => true,
				'options'     => array(
					'default' => __( 'Rounded', 'klarna-express-checkout' ),
					'rect'    => __( 'Rectangular', 'klarna-express-checkout' ),
					'pill'    => __( 'Pill', 'klarna-express-checkout' ),
				),
				'default'     => 'default',
			),
			'kec_placement' => array(
				'title'   => __( 'Placements', 'klarna-express-checkout' ),
				'type'    => 'select',
				'default' => 'both',
				'options' => array(
					'both'    => __( 'All (recommended)', 'klarna-express-checkout' ),
					'product' => __( 'Product pages', 'klarna-express-checkout' ),
					'cart'    => __( 'Cart page', 'klarna-express-checkout' ),
				),
			),
			'kec_webhook' => array(
				'type'        => 'kp_text_info',
				'title'       => sprintf( __( 'Webhook %s', 'klarna-express-checkout' ), self::webhook_status_badge( $webhook_created ) ),
				'description' => __( 'For one step Express Checkout to function, Klarna needs to be able to send callbacks to your store. Enable the callbacks and configure a signing key to use for the authentication.', 'klarna-express-checkout' ),
			),
			'kec_webhook_button' => array(
				'type'            => 'kec_webhook_button',
				'webhook_created' => $webhook_created,
			),
			'kec_end'       => array(
				'type'     => 'kp_section_end',
				'previews' => array(
					array(
						'title' => __( 'Preview', 'klarna-express-checkout' ),
						'image' => $this->get_preview_img_url(),
					),
				),
			),
		);
	}

	/**
	 * Get the preview image url.
	 *
	 * @return string
	 */
	public function get_preview_img_url() {
		$shape = $this->get_shape();
		$theme = $this->get_theme();

		if ( '' === $shape ) {
			$shape = 'default';
		}

		if ( '' === $theme || 'default' === $theme ) {
			$theme = 'dark';
		}

		$preview_img = Assets::get_assets_path() . 'img/preview-' . $shape . '-' . $theme . '.png';

		return $preview_img;
	}

	/**
	 * Get the badge to use for the webhook status.
	 *
	 * @param bool $webhook_created If the webhook have been created or not.
	 *
	 * @return string
	 */
	public static function webhook_status_badge( $webhook_created ) {
		if ( $webhook_created ) {
			return '<span class="badge" style="background:#46b450; color:#fff; padding:1px 8px; border-radius:3px; font-size:0.9em; float:right;">' . esc_html__( 'Created', 'text-domain' ) . '</span>';
		} else {
			return '<span class="badge" style="background:#e74c3c; color:#fff; padding:1px 8px; border-radius:3px; font-size:0.9em; float:right;">' . esc_html__( 'Not Created', 'text-domain' ) . '</span>';
		}
	}

	/**
	 * Output the webhook button field.
	 *
	 * @param array $section The arguments for the section.
	 *
	 * @return void
	 */
	public static function webhook_button( $section  ) {
		$settings_link = KP_WC()->get_setting_link();
		// If the webhook are already created, show a delete and simulate button instead.
		if( $section['webhook_created'] ) {
			$simulate_nonce = wp_create_nonce( 'kec_simulate_webhook' );
			$delete_nonce   = wp_create_nonce( 'kec_delete_webhook' );
			?>
			<tr class="kp_settings__text_info">
				<td colspan="2" class="forminp" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
					<a style="margin-top: 10px;" href="<?php echo esc_url( "{$settings_link}&kec_action=simulate_webhook&nonce={$simulate_nonce}#klarna-payments-settings-kec_settings" ); ?>" class="button-link">
						<?php esc_html_e( 'Test Webhook', 'klarna-express-checkout' ); ?>
					</a>
					<a style="margin-top: 10px;" href="<?php echo esc_url( "{$settings_link}&kec_action=delete_webhook&nonce={$delete_nonce}#klarna-payments-settings-kec_settings" ); ?>" class="button-link button-link-delete">
						<?php esc_html_e( 'Delete Webhook', 'klarna-express-checkout' ); ?>
					</a>
				</td>
			</tr>
			<?php
			return;
		}

		$nonce = wp_create_nonce( 'kec_create_webhook' );
		?>
		<tr class="kp_settings__text_info">
			<td colspan="2" class="forminp">
				<p><?php esc_html_e( 'Create the necessary webhook in Klarna for the Express Checkout.', 'klarna-express-checkout' ); ?></p>
				<a style="margin-top: 10px;" href="<?php echo esc_url( "{$settings_link}&kec_action=create_webhook&nonce={$nonce}#klarna-payments-settings-kec_settings" ); ?>" class="button button-primary">
					<?php esc_html_e( 'Create Webhook', 'klarna-express-checkout' ); ?>
				</a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get the HTML as a string for the webhook button field.
	 *
	 * @param string $html The HTML to append the section start to.
	 * @param string $key The key for the section.
	 * @param array  $section The arguments for the section.
	 *
	 * @return string
	 */
	public static function webhook_button_html( $html, $key, $section ) {
		ob_start();
		self::webhook_button( $section );
		$html = ob_get_clean();
		return $html;
	}
}
