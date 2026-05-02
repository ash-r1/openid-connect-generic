<?php
/**
 * Class OpenID_Connect_Generic_Client_Test
 *
 * @package   OpenID_Connect_Generic
 */

/**
 * Plugin OIDC/oAuth client class test case.
 */
class OpenID_Connect_Generic_Client_Test extends WP_UnitTestCase {

	/**
	 * Test case setup method.
	 *
	 * @return void
	 */
	public function setUp(): void {

		parent::setUp();

	}

	/**
	 * Test case cleanup method.
	 *
	 * @return void
	 */
	public function tearDown(): void {

		parent::tearDown();

	}

	/**
	 * Test plugin get_redirect_uri() method.
	 *
	 * @group ClientTests
	 */
	public function test_plugin_client_get_redirect_uri() {

		$this->assertTrue( true, 'Needs Unit Tests.' );

	}

	/**
	 * Test get_issuer_from_endpoint extracts base URL correctly.
	 *
	 * @dataProvider issuer_extraction_provider
	 * @group ClientTests
	 * @group IssuerValidation
	 *
	 * @param string $endpoint_url    The endpoint URL to test.
	 * @param string $expected_issuer The expected extracted issuer.
	 */
	public function test_get_issuer_from_endpoint( $endpoint_url, $expected_issuer ) {
		$client = $this->create_client();
		$actual = $client->get_issuer_from_endpoint( $endpoint_url );
		$this->assertEquals( $expected_issuer, $actual );
	}

	/**
	 * Data provider for issuer extraction tests.
	 *
	 * @return array Test cases with endpoint URLs and expected issuers.
	 */
	public function issuer_extraction_provider() {
		return array(
			'auth0_authorize'         => array(
				'https://dev-test.us.auth0.com/authorize',
				'https://dev-test.us.auth0.com',
			),
			'keycloak_with_path'      => array(
				'https://auth.example.com/realms/myrealm/protocol/openid-connect/auth',
				'https://auth.example.com',
			),
			'okta_with_path'          => array(
				'https://dev-123456.okta.com/oauth2/default/v1/authorize',
				'https://dev-123456.okta.com',
			),
			'with_non_standard_port'  => array(
				'https://localhost:8443/oauth/authorize',
				'https://localhost:8443',
			),
			'with_standard_https_port' => array(
				'https://example.com:443/authorize',
				'https://example.com',
			),
			'with_standard_http_port' => array(
				'http://example.com:80/authorize',
				'http://example.com',
			),
			'already_base_url'        => array(
				'https://example.com/',
				'https://example.com',
			),
			'no_trailing_slash'       => array(
				'https://example.com',
				'https://example.com',
			),
			'azure_ad'                => array(
				'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'https://login.microsoftonline.com',
			),
		);
	}

	/**
	 * Test validate_id_token_claim accepts matching issuer.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_matching_issuer() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com',  // Matches derived issuer.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_id_token_claim rejects mismatched issuer.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_wrong_issuer() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://evil.com',  // Wrong issuer.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-iss', $result->get_error_code() );
	}

	/**
	 * Test validate_id_token_claim with Auth0 style issuer.
	 * Note: Auth0 includes trailing slash in issuer, validation should handle both with/without.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_auth0_format() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://dev-emypzqmunz78not4.us.auth0.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'auth0|123456',
			'iss' => 'https://dev-emypzqmunz78not4.us.auth0.com/',  // Auth0 includes trailing slash in actual tokens.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_id_token_claim rejects expired token.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_rejects_expired() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com',
			'aud' => 'test_client',
			'exp' => time() - 3600,  // Expired 1 hour ago.
			'iat' => time() - 7200,
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertWPError( $result );
		$this->assertEquals( 'token-expired', $result->get_error_code() );
	}

	/**
	 * Test validate_id_token_claim rejects wrong audience.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_rejects_wrong_audience() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com',
			'aud' => 'wrong_client',  // Wrong audience.
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertWPError( $result );
		$this->assertEquals( 'invalid-aud', $result->get_error_code() );
	}

	/**
	 * Test validate_id_token_claim accepts audience as array.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_audience_array() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://example.com/authorize',
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://example.com',
			'aud' => array( 'test_client', 'other_client' ),  // Audience as array.
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * Test validate_id_token_claim uses configured issuer over derived issuer.
	 *
	 * @group ClientTests
	 * @group IssuerValidation
	 */
	public function test_validate_id_token_claim_with_explicit_issuer() {
		$client = $this->create_client(
			array(
				'endpoint_login' => 'https://login.example.com/authorize',
				'issuer'         => 'https://issuer.example.com', // Explicit issuer differs from login endpoint.
				'client_id'      => 'test_client',
			)
		);

		$id_token_claim = array(
			'sub' => 'user123',
			'iss' => 'https://issuer.example.com',  // Matches configured issuer, not derived from endpoint_login.
			'aud' => 'test_client',
			'exp' => time() + 3600,
			'iat' => time(),
		);

		$result = $client->validate_id_token_claim( $id_token_claim );
		$this->assertTrue( $result );
	}

	/**
	 * State round-trip: a freshly minted state validates and surfaces redirect_to.
	 *
	 * @group ClientTests
	 * @group StatelessState
	 */
	public function test_new_state_round_trip() {
		$client = $this->create_client();
		$state  = $client->new_state( 'https://example.com/wp-admin/' );

		$this->assertNotEmpty( $state );
		$this->assertSame( 1, substr_count( $state, '.' ) );
		$this->assertTrue( $client->check_state( $state ) );
		$this->assertEquals( 'https://example.com/wp-admin/', $client->get_state_redirect_url( $state ) );
	}

	/**
	 * Tampering with the payload (or signature) must invalidate the state and
	 * fire the state-not-found action so callers can log the failure.
	 *
	 * @group ClientTests
	 * @group StatelessState
	 */
	public function test_check_state_rejects_tampered_token() {
		$client = $this->create_client();
		$state  = $client->new_state( 'https://example.com/wp-admin/' );

		list( $payload_b64, $sig_b64 ) = explode( '.', $state );

		// Flip a single character in the payload while keeping the original signature.
		$tampered_payload = ( 'A' === $payload_b64[0] ? 'B' : 'A' ) . substr( $payload_b64, 1 );
		$tampered         = $tampered_payload . '.' . $sig_b64;

		$not_found_fired = false;
		$listener        = function () use ( &$not_found_fired ) {
			$not_found_fired = true;
		};
		add_action( 'openid-connect-generic-state-not-found', $listener );

		$this->assertFalse( $client->check_state( $tampered ) );
		$this->assertTrue( $not_found_fired, 'state-not-found action should fire for tampered tokens.' );

		remove_action( 'openid-connect-generic-state-not-found', $listener );

		// get_state_redirect_url must also refuse to leak the redirect target.
		$this->assertSame( '', $client->get_state_redirect_url( $tampered ) );
	}

	/**
	 * A state whose signature is valid but whose `e` claim is in the past must
	 * be rejected as expired (distinct from forgery).
	 *
	 * @group ClientTests
	 * @group StatelessState
	 */
	public function test_check_state_rejects_expired_token() {
		// state_time_limit = -10 makes new_state() emit an already-expired token.
		$client = $this->create_client( array( 'state_time_limit' => -10 ) );
		$state  = $client->new_state( 'https://example.com/wp-admin/' );

		$expired_fired = false;
		$listener      = function () use ( &$expired_fired ) {
			$expired_fired = true;
		};
		add_action( 'openid-connect-generic-state-expired', $listener );

		$this->assertFalse( $client->check_state( $state ) );
		$this->assertTrue( $expired_fired, 'state-expired action should fire for an expired but well-signed token.' );

		remove_action( 'openid-connect-generic-state-expired', $listener );

		// Expired tokens must not surface the redirect either.
		$this->assertSame( '', $client->get_state_redirect_url( $state ) );
	}

	/**
	 * Malformed input (empty string, missing separator, garbage) must fail
	 * cleanly without throwing.
	 *
	 * @dataProvider malformed_state_provider
	 * @group ClientTests
	 * @group StatelessState
	 *
	 * @param string $bad_state The malformed state value.
	 */
	public function test_check_state_rejects_malformed( $bad_state ) {
		$client = $this->create_client();
		$this->assertFalse( $client->check_state( $bad_state ) );
		$this->assertSame( '', $client->get_state_redirect_url( $bad_state ) );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function malformed_state_provider() {
		return array(
			'empty'           => array( '' ),
			'no_separator'    => array( 'abcdefg' ),
			'too_many_parts'  => array( 'a.b.c' ),
			'bad_b64_payload' => array( '!!!!.bm9wZQ' ),
			'bad_b64_sig'     => array( 'eyJ2IjoxfQ.!!!!' ),
		);
	}

	/**
	 * Helper to create client instance for testing.
	 *
	 * @param array $settings Optional settings to override defaults.
	 *
	 * @return OpenID_Connect_Generic_Client
	 */
	private function create_client( $settings = array() ) {
		$default_settings = array(
			'client_id'          => 'test_client',
			'client_secret'      => 'test_secret',
			'scope'              => 'openid email profile',
			'endpoint_login'     => 'https://example.com/authorize',
			'endpoint_userinfo'  => '',
			'endpoint_token'     => 'https://example.com/token',
			'redirect_uri'       => 'https://example.com/callback',
			'acr_values'         => '',
			'endpoint_jwks'      => '',
			'issuer'             => '',
			'jwks_cache_ttl'     => 3600,
			'state_time_limit'   => 180,
			'allow_internal_idp' => false,
		);

		$merged = array_merge( $default_settings, $settings );

		$logger = $this->createMock( OpenID_Connect_Generic_Option_Logger::class );

		return new OpenID_Connect_Generic_Client(
			$merged['client_id'],
			$merged['client_secret'],
			$merged['scope'],
			$merged['endpoint_login'],
			$merged['endpoint_userinfo'],
			$merged['endpoint_token'],
			$merged['redirect_uri'],
			$merged['acr_values'],
			$merged['endpoint_jwks'],
			$merged['issuer'],
			$merged['jwks_cache_ttl'],
			$merged['state_time_limit'],
			$merged['allow_internal_idp'],
			$logger
		);
	}

}
