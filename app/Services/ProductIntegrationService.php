<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductIntegration;
use Illuminate\Support\Str;

class ProductIntegrationService
{
    public function createForProduct(Product $product, array $overrides = []): ProductIntegration
    {
        if ($product->productIntegration) {
            return $product->productIntegration;
        }

        $credentials = $this->generateCredentials();

        return ProductIntegration::create([
            'product_id' => $product->id,
            'name' => $overrides['name'] ?? $product->name,
            'slug' => $overrides['slug'] ?? $product->installerSlug(),
            'version' => $overrides['version'] ?? $product->currentVersion(),
            'api_base_url' => $overrides['api_base_url'] ?? config('softkatta.company_api_url'),
            'public_api_key' => $credentials['public_api_key'],
            'secret_api_key' => $credentials['secret_api_key'],
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'webhook_secret' => $credentials['webhook_secret'],
            'supported_versions' => $overrides['supported_versions'] ?? [$product->currentVersion()],
            'status' => $overrides['status'] ?? 'active',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function generateCredentials(): array
    {
        return [
            'public_api_key' => 'sk_pub_'.Str::lower(Str::random(32)),
            'secret_api_key' => 'sk_sec_'.Str::lower(Str::random(48)),
            'client_id' => 'sk_cli_'.Str::lower(Str::random(24)),
            'client_secret' => 'sk_cls_'.Str::lower(Str::random(48)),
            'webhook_secret' => 'whsec_'.Str::lower(Str::random(32)),
        ];
    }

    public function regenerateKeys(ProductIntegration $integration): ProductIntegration
    {
        $credentials = $this->generateCredentials();

        $integration->update([
            'public_api_key' => $credentials['public_api_key'],
            'secret_api_key' => $credentials['secret_api_key'],
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'webhook_secret' => $credentials['webhook_secret'],
        ]);

        return $integration->fresh(['product']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(ProductIntegration $integration, bool $revealSecrets = false): array
    {
        $integration->loadMissing('product');
        $data = $integration->toArray();
        $data['product_id'] = $integration->product_id;
        $data['product_name'] = $integration->product?->name;

        if ($revealSecrets) {
            $data['secret_api_key'] = $integration->secret_api_key;
            $data['client_secret'] = $integration->client_secret;
            $data['webhook_secret'] = $integration->webhook_secret;
        } else {
            $data['secret_api_key'] = $this->maskSecret($integration->secret_api_key);
            $data['client_secret'] = $this->maskSecret($integration->client_secret);
            $data['webhook_secret'] = $this->maskSecret($integration->webhook_secret);
        }

        return $data;
    }

    public function maskSecret(?string $secret): string
    {
        if ($secret === null || $secret === '') {
            return '••••••••';
        }

        $visible = substr($secret, -4);

        return '••••••••'.$visible;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildIntegrationGuide(ProductIntegration $integration): array
    {
        $integration->loadMissing('product');
        $companyBase = rtrim((string) config('softkatta.company_api_url'), '/');
        $baseUrl = $companyBase !== '' ? $companyBase : rtrim((string) $integration->api_base_url, '/');
        if (! str_ends_with($baseUrl, '/company') && ! str_contains($baseUrl, '/api/v1/company')) {
            $baseUrl = rtrim((string) config('softkatta.company_api_url', 'https://api.softkatta.in/api/v1/company'), '/');
        }

        $slug = $integration->slug;
        $publicKey = $integration->public_api_key;
        $version = $integration->version ?: '1.0.0';
        $sampleDomain = 'erp.example.com';
        $sampleLicense = 'SK-STUDY-XXXXX-XXXXX';
        $sampleInstallationId = '00000000-0000-0000-0000-000000000001';
        $sampleFingerprint = 'fp_example_server_fingerprint';
        $timestamp = time();
        $nonce = 'example-nonce-'.substr(md5((string) $timestamp), 0, 16);
        $path = '/api/v1/company/activate';
        $method = 'POST';
        $body = json_encode([
            'license_key' => $sampleLicense,
            'installation_id' => $sampleInstallationId,
        ], JSON_THROW_ON_ERROR);
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            (string) $timestamp,
            $nonce,
            $slug,
            $sampleDomain,
            $version,
            $sampleInstallationId,
            $sampleFingerprint,
            $bodyHash,
        ]);
        $sampleSignature = hash_hmac('sha256', $canonical, 'YOUR_SECRET_API_KEY');

        $curl = <<<CURL
curl -X POST "{$baseUrl}/activate" \\
  -H "Authorization: Bearer {$publicKey}" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -H "X-Product-Slug: {$slug}" \\
  -H "X-Domain: {$sampleDomain}" \\
  -H "X-Product-Version: {$version}" \\
  -H "X-Installation-Id: {$sampleInstallationId}" \\
  -H "X-Server-Fingerprint: {$sampleFingerprint}" \\
  -H "X-Timestamp: {$timestamp}" \\
  -H "X-Nonce: {$nonce}" \\
  -H "X-Signature: {$sampleSignature}" \\
  -d '{$body}'
CURL;

        return [
            'product' => [
                'name' => $integration->name,
                'slug' => $integration->slug,
                'version' => $integration->version,
            ],
            'api_base_url' => $baseUrl,
            'public_api_key' => $integration->public_api_key,
            'authentication' => [
                'method' => 'Bearer Token + HMAC SHA256 Signature (Company API)',
                'signature_payload' => "METHOD\\nPATH\\nTIMESTAMP\\nNONCE\\nPRODUCT_SLUG\\nDOMAIN\\nPRODUCT_VERSION\\nINSTALLATION_ID\\nSERVER_FINGERPRINT\\nSHA256(raw_body)",
                'request_window_seconds' => (int) config('softkatta.company_timestamp_skew', 300),
                'preferred_client' => 'softkatta/licensing (Study Point install wizard)',
            ],
            'required_headers' => [
                'Authorization',
                'X-Product-Slug',
                'X-Domain',
                'X-Product-Version',
                'X-Installation-Id',
                'X-Server-Fingerprint',
                'X-Timestamp',
                'X-Nonce',
                'X-Signature',
                'X-Install-Token (after activate)',
            ],
            'endpoints' => [
                'POST /activate',
                'POST /verify',
                'POST /refresh-token',
                'GET /modules',
                'GET /limits',
                'GET /addons',
                'POST /heartbeat',
            ],
            'env' => [
                'SOFTKATTA_COMPANY_API_URL' => $baseUrl,
                'SOFTKATTA_PUBLIC_API_KEY' => $publicKey,
                'SOFTKATTA_API_SECRET' => 'YOUR_SECRET_API_KEY',
                'SOFTKATTA_PRODUCT_SLUG' => $slug,
                'SOFTKATTA_PRODUCT_VERSION' => $version,
            ],
            'error_codes' => [
                'INVALID_API_KEY',
                'INVALID_LICENSE',
                'DOMAIN_NOT_AUTHORIZED',
                'UNSUPPORTED_VERSION',
                'EXPIRED_SUBSCRIPTION',
                'SUSPENDED_LICENSE',
                'INVALID_INSTALL_TOKEN',
                'INVALID_SIGNATURE',
                'INSTALLATION_LIMIT',
                'REVOKED_LICENSE',
            ],
            'examples' => [
                'curl' => $curl,
            ],
            'troubleshooting' => [
                'Use Company API base URL ending with /api/v1/company (not /central).',
                'Sign the canonical 10-line Company API payload; legacy Central HMAC will fail.',
                'Product slug must match SoftKatta Admin → Product Integrations (e.g. study-point-management-software).',
                'Never expose the secret API key in frontend code.',
            ],
        ];
    }
}
