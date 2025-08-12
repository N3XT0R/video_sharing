<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\DatabaseTestCase;

/**
 * Feature tests for Dropbox OAuth connect/callback flow.
 *
 * Assumptions:
 * - Routes are named `dropbox.connect` and `dropbox.callback`.
 * - Config model stores refresh token in table `configs` with columns `key`, `value`.
 *   If your table name differs, adjust the `assertDatabaseHas` calls accordingly.
 *
 * Notes:
 * - The controller should:
 *   - Use the `scope` query param (no trailing space) in `connect()`.
 *   - Store a random `state` in session on `connect()` and validate it in `callback()`
 *     using a timing-safe compare (`hash_equals`) and `pull()` to invalidate it.
 */
class DropboxControllerTest extends DatabaseTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal service configuration for tests.
        config()->set('services.dropbox.client_id', 'app_key_123');
        config()->set('services.dropbox.client_secret', 'app_secret_456');

        // Disallow any real HTTP calls globally for this test class.
        Http::preventStrayRequests();
    }

    public function testConnectRedirectsToDropboxWithStateAndExpectedParams(): void
    {
        // Act: hit the connect endpoint
        $resp = $this->get(route('dropbox.connect'));

        // Assert: we are redirected to Dropbox OAuth authorize endpoint
        $resp->assertRedirect();
        $url = $resp->headers->get('Location');
        $this->assertStringStartsWith('https://www.dropbox.com/oauth2/authorize?', $url);

        // Parse query string for assertions
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        // Assert: required params are present and correct
        $this->assertSame('app_key_123', $q['client_id'] ?? null);
        $this->assertSame('code', $q['response_type'] ?? null);
        $this->assertSame('offline', $q['token_access_type'] ?? null);
        // Expect scopes exactly as in controller
        $this->assertSame('files.content.write files.content.read', $q['scope'] ?? null);

        // Assert: state was written to session and echoed back into the URL
        $this->assertTrue(session()->has('dropbox_oauth_state'));
        $this->assertSame(session('dropbox_oauth_state'), $q['state'] ?? null);
    }

    public function testConnectFailsWith412IfClientIdIsMissing(): void
    {
        // Arrange: simulate missing client_id config
        config()->set('services.dropbox.client_id', '');

        // Act & Assert
        $this->get(route('dropbox.connect'))
            ->assertStatus(412)
            ->assertSee('Fehlende Konfiguration'); // message from controller
    }

    public function testCallbackRequiresCodeParam(): void
    {
        // Act & Assert
        $this->get(route('dropbox.callback'))
            ->assertStatus(400)
            ->assertSee('Kein Code erhalten');
    }

    public function testCallbackRejectsWhenStateIsMissingOrInvalid(): void
    {
        // Arrange: simulate a previously stored state (as if /connect was called)
        session(['dropbox_oauth_state' => 'EXPECTED_STATE']);

        // Fake token endpoint to avoid real HTTP (won’t be reached if state is invalid)
        Http::fake([config('services.dropbox.token_url') => Http::response([], 200)]);

        // Act & Assert: wrong state must be rejected
        $this->get(route('dropbox.callback', ['code' => 'CODE123', 'state' => 'WRONG']))
            ->assertStatus(400)
            ->assertSee('Ungültiger state');
    }

    public function testCallbackExchangesCodePersistsRefreshTokenAndClearsCachedAccessToken(): void
    {
        // Arrange: existing cached access token should be cleared if refresh token is saved
        Cache::put('dropbox.access_token', 'OLD_TOKEN', 300);

        // Fake successful token exchange
        Http::fake([
            'https://api.dropboxapi.com/oauth2/token' => Http::response([
                'access_token' => 'ACCESS_TOKEN_XYZ',
                'token_type' => 'bearer',
                'expires_in' => 14400,
                'refresh_token' => 'REFRESH_ABC',
            ], 200),
        ]);

        // Arrange: valid state (controller should read & invalidate it via pull())
        session(['dropbox_oauth_state' => 'STATE_OK']);

        // Act
        $resp = $this->get(route('dropbox.callback', [
            'code' => 'CODE123',
            'state' => 'STATE_OK',
        ]));

        // Assert: JSON response contains useful data
        $resp->assertOk()
            ->assertJson([
                'status' => 'ok',
                'access_token_expires_in' => 14400,
            ]);

        // Assert: refresh token persisted to DB
        $this->assertDatabaseHas('configs', [
            'key' => 'dropbox_refresh_token',
            'value' => 'REFRESH_ABC',
        ]);

        // Assert: cached access token was cleared
        $this->assertTrue(Cache::missing('dropbox.access_token'));
    }

    public function testCallbackBubblesUpOauthErrorsFromDropbox(): void
    {
        // Arrange: fake an OAuth error response
        Http::fake([
            'https://api.dropboxapi.com/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        // Arrange: valid state so we reach the HTTP call
        session(['dropbox_oauth_state' => 'STATE_OK']);

        // Act & Assert: controller uses ->throw(), so a RequestException is expected
        $this->withoutExceptionHandling();
        $this->expectException(RequestException::class);

        $this->get(route('dropbox.callback', [
            'code' => 'BAD_CODE',
            'state' => 'STATE_OK',
        ]));
    }
}