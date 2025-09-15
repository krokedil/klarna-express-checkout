<?php
namespace Krokedil\KlarnaExpressCheckout\Requests\Notification;

use Krokedil\KlarnaExpressCheckout\Requests\Base;

class CreateSigningKey extends Base {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments Arguments to pass to the request
	 *
	 * @return void
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'POST';
		$this->endpoint = 'v2/notification/signing-keys';
		$this->log_title = 'KEC: Create Notification Signing Key';
	}
}
