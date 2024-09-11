<?php
use Krokedil\KlarnaExpressCheckout\Settings;
use WP_Mock\Tools\TestCase;

class SettingsTest extends TestCase {
	/**
	 * @var Settings
	 */
	private $settings;

	public function setUp(): void {
		parent::setUp();
		WP_Mock::userFunction( 'get_option' )
			->with( 'test_key', array() )
			->andReturn(
				array(
					'kec_enabled'            => 'yes',
					'kec_credentials_secret' => 'test_credentials_secret',
					'kec_theme'              => 'dark',
					'kec_shape'              => 'default',
					'kec_placement'          => 'both',
				)
			);
		$this->settings = new Settings( 'test_key' );
	}

	public function tearDown(): void {
		parent::tearDown();
		unset( $this->settings );
	}

	public function testIsEnabled() {
		$this->assertTrue( $this->settings->is_enabled() );
	}

	public function testGetCredentialsSecret() {
		$this->assertEquals( 'test_credentials_secret', $this->settings->get_credentials_secret() );
	}

	public function testGetTheme() {
		$this->assertEquals( 'dark', $this->settings->get_theme() );
	}

	public function testGetShape() {
		$this->assertEquals( 'default', $this->settings->get_shape() );
	}

	public function testGetPlacement() {
		$this->assertEquals( 'both', $this->settings->get_placements() );
	}

	public function testGetSettings() {
		$result = $this->settings->get_settings();

		$this->assertArrayHasKey( 'kec_theme', $result );
		$this->assertArrayHasKey( 'kec_shape', $result );
		$this->assertArrayHasKey( 'kec_placement', $result );
	}

	public function testGetSettingFields() {
		$result = $this->settings->get_setting_fields();

		$this->assertArrayHasKey( 'kec_settings', $result );
		$this->assertArrayHasKey( 'kec_theme', $result );
		$this->assertArrayHasKey( 'kec_shape', $result );
		$this->assertArrayHasKey( 'kec_placement', $result );
	}

	public function testAddSettings() {
		$oldSettings = array(
			'test_setting' => array(),
		);

		$newSettings = $this->settings->add_settings( $oldSettings );

		$this->assertArrayHasKey( 'test_setting', $newSettings );
		$this->assertArrayHasKey( 'kec_settings', $newSettings );
		$this->assertArrayHasKey( 'kec_theme', $newSettings );
		$this->assertArrayHasKey( 'kec_placement', $newSettings );
	}
}
