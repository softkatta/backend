<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\LicenseStatus;
use App\Enums\SubscriptionStatus;
use App\Models\LicenseInstallation;
use App\Models\LicenseKey;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductIntegration;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CompanyApiAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyApiLicenseTest extends TestCase
{
    use RefreshDatabase;

    private ProductIntegration $integration;

    private LicenseKey $license;

    private string $secret;

    private string $publicKey;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for Company API feature tests.');
        }

        parent::setUp();

        Cache::flush();

        $user = User::factory()->create(['email' => 'client@example.com']);
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'owner_id' => $user->id,
        ]);
        $user->update(['tenant_id' => $tenant->id]);

        $product = Product::create([
            'name' => 'Study Point',
            'slug' => 'study-point',
            'is_active' => true,
            'meta' => ['current_version' => '1.0.0', 'installer_slug' => 'study-point'],
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 100,
            'billing_cycle' => BillingCycle::Yearly,
            'limits' => [
                'max_students' => 100,
                'max_branches' => 2,
                'enabled_modules' => ['students' => true, 'admissions' => true],
            ],
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $this->license = LicenseKey::create([
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'license_key' => 'SK-TEST-AAAAA-BBBBB',
            'allowed_domains' => [],
            'max_devices' => 2,
            'max_domains' => 1,
            'status' => LicenseStatus::Active,
            'is_product_active' => true,
            'activated_at' => now(),
            'expires_at' => now()->addYear(),
            'activation_count' => 0,
        ]);

        $this->publicKey = 'sk_pub_test_'.Str::random(16);
        $this->secret = 'sk_sec_test_'.Str::random(24);

        $this->integration = ProductIntegration::create([
            'product_id' => $product->id,
            'name' => 'Study Point',
            'slug' => 'study-point',
            'version' => '1.0.0',
            'api_base_url' => 'http://localhost/api/v1/company',
            'public_api_key' => $this->publicKey,
            'secret_api_key' => $this->secret,
            'client_id' => 'sk_cli_'.Str::random(12),
            'client_secret' => 'sk_cls_'.Str::random(12),
            'webhook_secret' => 'whsec_'.Str::random(12),
            'supported_versions' => ['1.0.0'],
            'status' => 'active',
        ]);
    }

    public function test_activate_verify_refresh_and_domain_mismatch(): void
    {
        $activate = $this->signedJson('POST', '/api/v1/company/activate', [
            'license_key' => $this->license->license_key,
            'installation_id' => null,
        ], [
            'domain' => 'study.local',
            'installation_id' => '',
            'fingerprint' => hash('sha256', 'server-1'),
        ]);

        $activate->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'install_token',
                    'refresh_token',
                    'installation_id',
                    'bound_domain',
                    'configuration_profile' => ['modules', 'limits', 'plan'],
                ],
            ]);

        $installToken = $activate->json('data.install_token');
        $refreshToken = $activate->json('data.refresh_token');
        $installationId = $activate->json('data.installation_id');

        $this->assertDatabaseHas('license_installations', [
            'installation_id' => $installationId,
            'domain' => 'study.local',
        ]);

        $verify = $this->signedJson('POST', '/api/v1/company/verify', [], [
            'domain' => 'study.local',
            'installation_id' => $installationId,
            'fingerprint' => hash('sha256', 'server-1'),
            'install_token' => $installToken,
        ]);

        $verify->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.bound_domain', 'study.local');

        $mismatch = $this->signedJson('POST', '/api/v1/company/verify', [], [
            'domain' => 'other.local',
            'installation_id' => $installationId,
            'fingerprint' => hash('sha256', 'server-1'),
            'install_token' => $installToken,
        ]);

        $mismatch->assertStatus(403)
            ->assertJsonPath('error_code', 'DOMAIN_NOT_AUTHORIZED');

        $refresh = $this->signedJson('POST', '/api/v1/company/refresh-token', [
            'refresh_token' => $refreshToken,
            'installation_id' => $installationId,
        ], [
            'domain' => 'study.local',
            'installation_id' => $installationId,
            'fingerprint' => hash('sha256', 'server-1'),
        ]);

        $refresh->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['install_token', 'refresh_token']]);

        $newInstallToken = $refresh->json('data.install_token');
        $this->assertNotSame($installToken, $newInstallToken);

        // Old install token should fail
        $oldToken = $this->signedJson('POST', '/api/v1/company/verify', [], [
            'domain' => 'study.local',
            'installation_id' => $installationId,
            'fingerprint' => hash('sha256', 'server-1'),
            'install_token' => $installToken,
        ]);

        $oldToken->assertStatus(401)
            ->assertJsonPath('error_code', 'INVALID_INSTALL_TOKEN');
    }

    public function test_duplicate_nonce_is_rejected(): void
    {
        $nonce = Str::random(32);
        $headers = $this->buildHeaders('POST', '/api/v1/company/activate', json_encode([
            'license_key' => $this->license->license_key,
            'installation_id' => null,
        ], JSON_UNESCAPED_SLASHES), [
            'domain' => 'study.local',
            'installation_id' => '',
            'fingerprint' => hash('sha256', 'fp'),
            'nonce' => $nonce,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/company/activate', [
                'license_key' => $this->license->license_key,
                'installation_id' => null,
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/v1/company/activate', [
                'license_key' => $this->license->license_key,
                'installation_id' => null,
            ])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'DUPLICATE_NONCE');
    }

    public function test_revoked_installation_rejects_verify(): void
    {
        $activate = $this->signedJson('POST', '/api/v1/company/activate', [
            'license_key' => $this->license->license_key,
        ], [
            'domain' => 'study.local',
            'installation_id' => '',
            'fingerprint' => hash('sha256', 'server-1'),
        ])->assertOk();

        $installationId = $activate->json('data.installation_id');
        $installToken = $activate->json('data.install_token');

        LicenseInstallation::query()
            ->where('installation_id', $installationId)
            ->update(['revoked_at' => now()]);

        $this->signedJson('POST', '/api/v1/company/verify', [], [
            'domain' => 'study.local',
            'installation_id' => $installationId,
            'fingerprint' => hash('sha256', 'server-1'),
            'install_token' => $installToken,
        ])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'INVALID_INSTALL_TOKEN');
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array{domain?: string, installation_id?: string, fingerprint?: string, install_token?: string, nonce?: string}  $meta
     */
    private function signedJson(string $method, string $path, array $body = [], array $meta = [])
    {
        $rawBody = in_array(strtoupper($method), ['GET', 'HEAD'], true)
            ? ''
            : json_encode($body, JSON_UNESCAPED_SLASHES);

        $headers = $this->buildHeaders($method, $path, $rawBody ?: '', $meta);

        $pending = $this->withHeaders($headers);

        return match (strtoupper($method)) {
            'GET' => $pending->getJson($path),
            default => $pending->postJson($path, $body),
        };
    }

    /**
     * @param  array{domain?: string, installation_id?: string, fingerprint?: string, install_token?: string, nonce?: string}  $meta
     * @return array<string, string>
     */
    private function buildHeaders(string $method, string $path, string $rawBody, array $meta = []): array
    {
        $timestamp = (string) time();
        $nonce = $meta['nonce'] ?? Str::random(32);
        $domain = $meta['domain'] ?? 'study.local';
        $installationId = $meta['installation_id'] ?? '';
        $fingerprint = $meta['fingerprint'] ?? hash('sha256', 'fp');
        $version = '1.0.0';

        $canonical = app(CompanyApiAuthService::class)->canonicalString(
            $method,
            $path,
            $timestamp,
            $nonce,
            $this->integration->slug,
            $domain,
            $version,
            $installationId,
            $fingerprint,
            $rawBody,
        );

        $headers = [
            'Authorization' => 'Bearer '.$this->publicKey,
            'X-Product-Slug' => $this->integration->slug,
            'X-Domain' => $domain,
            'X-Product-Version' => $version,
            'X-Installation-Id' => $installationId,
            'X-Server-Fingerprint' => $fingerprint,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => hash_hmac('sha256', $canonical, $this->secret),
            'Accept' => 'application/json',
        ];

        if (! empty($meta['install_token'])) {
            $headers['X-Install-Token'] = $meta['install_token'];
        }

        return $headers;
    }
}
