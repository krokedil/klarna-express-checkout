<?php

WP_Mock::userFunction(
	'get_option',
	array(
		'args'   => array( 'test_key', array() ),
		'return' => array(
			'kec_credentials_secret' => 'test_credentials_secret',
			'kec_theme'              => 'default',
			'kec_shape'              => 'default',
		),
	)
);

WP_Mock::userFunction(
	'wp_create_nonce',
	array(
		'args'   => 'kec_get_payload',
		'return' => 'test_nonce',
	)
);

WP_Mock::userFunction(
	'wp_create_nonce',
	array(
		'args'   => 'kec_auth_callback',
		'return' => 'test_nonce',
	)
);

WP_Mock::userFunction(
	'plugin_dir_url',
	array(
		'args'   => 'krokedil/klarna-express-checkout/klarna-express-checkout.php',
		'return' => 'http://example.org/wp-content/plugins/krokedil/klarna-express-checkout/',
	)
);
