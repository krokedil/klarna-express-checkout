<?php
namespace Krokedil\KlarnaExpressCheckout\Requests\Notification;

use Krokedil\KlarnaExpressCheckout\Requests\Base;

class DeleteWebhook extends Base {
	/**
	 * Class constructor
	 *
	 * @param array $arguments Arguments to pass to the request
	 *
	 * @return void
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method    = 'DELETE';

		$webhook_id      = $this->arguments[ 'webhook_id' ] ?? '';
		$this->endpoint  = "v2/notification/webhooks/$webhook_id";
		$this->log_title = 'KEC: Delete Notification Webhook';
	}
}
