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
            'api_base_url' => $overrides['api_base_url'] ?? config('softkatta.central_api_url'),
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
        $baseUrl = rtrim($integration->api_base_url, '/');
        $slug = $integration->slug;
        $publicKey = $integration->public_api_key;
        $sampleDomain = 'erp.example.com';
        $sampleLicense = 'SK-STUDY-XXXXX-XXXXX';
        $timestamp = time();
        $path = '/central/license/check';
        $method = 'POST';
        $body = json_encode([
            'license_key' => $sampleLicense,
            'domain' => $sampleDomain,
        ], JSON_THROW_ON_ERROR);
        $bodyHash = hash('sha256', $body);
        $signaturePayload = implode("\n", [(string) $timestamp, strtoupper($method), $path, $bodyHash]);
        $sampleSignature = hash_hmac('sha256', $signaturePayload, 'YOUR_SECRET_API_KEY');

        $curl = <<<CURL
curl -X POST "{$baseUrl}/license/check" \\
  -H "Authorization: Bearer {$publicKey}" \\
  -H "Content-Type: application/json" \\
  -H "X-Product-Slug: {$slug}" \\
  -H "X-License-Key: {$sampleLicense}" \\
  -H "X-Domain: {$sampleDomain}" \\
  -H "X-Product-Version: {$integration->version}" \\
  -H "X-Request-Time: {$timestamp}" \\
  -H "X-Signature: {$sampleSignature}" \\
  -d '{$body}'
CURL;

        $laravel = <<<PHP
use Illuminate\Support\Facades\Http;

\$timestamp = time();
\$path = '/central/license/check';
\$body = ['license_key' => '{$sampleLicense}', 'domain' => '{$sampleDomain}'];
\$bodyJson = json_encode(\$body);
\$signaturePayload = implode("\\n", [
    \$timestamp,
    'POST',
    \$path,
    hash('sha256', \$bodyJson),
]);
\$signature = hash_hmac('sha256', \$signaturePayload, config('services.softkatta.secret_api_key'));

\$response = Http::withHeaders([
    'Authorization' => 'Bearer {$publicKey}',
    'X-Product-Slug' => '{$slug}',
    'X-License-Key' => '{$sampleLicense}',
    'X-Domain' => '{$sampleDomain}',
    'X-Product-Version' => '{$integration->version}',
    'X-Request-Time' => (string) \$timestamp,
    'X-Signature' => \$signature,
])->post('{$baseUrl}/license/check', \$body);
PHP;

        $axios = <<<JS
import axios from 'axios';
import crypto from 'crypto';

const timestamp = Math.floor(Date.now() / 1000);
const path = '/central/license/check';
const body = { license_key: '{$sampleLicense}', domain: '{$sampleDomain}' };
const bodyJson = JSON.stringify(body);
const signaturePayload = [timestamp, 'POST', path, crypto.createHash('sha256').update(bodyJson).digest('hex')].join('\\n');
const signature = crypto.createHmac('sha256', 'YOUR_SECRET_API_KEY').update(signaturePayload).digest('hex');

const response = await axios.post('{$baseUrl}/license/check', body, {
  headers: {
    Authorization: 'Bearer {$publicKey}',
    'X-Product-Slug': '{$slug}',
    'X-License-Key': '{$sampleLicense}',
    'X-Domain': '{$sampleDomain}',
    'X-Product-Version': '{$integration->version}',
    'X-Request-Time': String(timestamp),
    'X-Signature': signature,
  },
});
JS;

        $fetchApi = <<<JS
const timestamp = Math.floor(Date.now() / 1000);
const path = '/central/license/check';
const body = { license_key: '{$sampleLicense}', domain: '{$sampleDomain}' };
const bodyJson = JSON.stringify(body);
const encoder = new TextEncoder();
const bodyHash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', encoder.encode(bodyJson))))
  .map((b) => b.toString(16).padStart(2, '0')).join('');
const signaturePayload = [timestamp, 'POST', path, bodyHash].join('\\n');
const key = await crypto.subtle.importKey('raw', encoder.encode('YOUR_SECRET_API_KEY'), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
const signatureBuffer = await crypto.subtle.sign('HMAC', key, encoder.encode(signaturePayload));
const signature = Array.from(new Uint8Array(signatureBuffer)).map((b) => b.toString(16).padStart(2, '0')).join('');

const response = await fetch('{$baseUrl}/license/check', {
  method: 'POST',
  headers: {
    Authorization: 'Bearer {$publicKey}',
    'Content-Type': 'application/json',
    'X-Product-Slug': '{$slug}',
    'X-License-Key': '{$sampleLicense}',
    'X-Domain': '{$sampleDomain}',
    'X-Product-Version': '{$integration->version}',
    'X-Request-Time': String(timestamp),
    'X-Signature': signature,
  },
  body: bodyJson,
});
JS;

        return [
            'product' => [
                'name' => $integration->name,
                'slug' => $integration->slug,
                'version' => $integration->version,
            ],
            'api_base_url' => $baseUrl,
            'public_api_key' => $integration->public_api_key,
            'authentication' => [
                'method' => 'Bearer Token + HMAC SHA256 Signature',
                'signature_payload' => '{timestamp}\\n{METHOD}\\n{path}\\n{sha256(body)}',
                'request_window_seconds' => 300,
            ],
            'required_headers' => [
                'Authorization',
                'X-Product-Slug',
                'X-License-Key',
                'X-Domain',
                'X-Product-Version',
                'X-Request-Time',
                'X-Signature',
            ],
            'endpoints' => [
                'POST /license/check',
                'GET /subscription',
                'GET /modules',
                'GET /limits',
                'GET /addons',
                'POST /heartbeat',
                'POST /license/activate',
                'POST /license/deactivate',
            ],
            'error_codes' => [
                'INVALID_API_KEY',
                'INVALID_LICENSE',
                'DOMAIN_NOT_AUTHORIZED',
                'PRODUCT_NOT_FOUND',
                'PRODUCT_INACTIVE',
                'VERSION_NOT_SUPPORTED',
                'SUBSCRIPTION_EXPIRED',
                'LICENSE_SUSPENDED',
                'INVALID_SIGNATURE',
                'REQUEST_EXPIRED',
                'RATE_LIMIT_EXCEEDED',
            ],
            'examples' => [
                'curl' => $curl,
                'laravel' => $laravel,
                'axios' => $axios,
                'fetch' => $fetchApi,
            ],
            'troubleshooting' => [
                'Ensure server clock is synced (REQUEST_EXPIRED otherwise).',
                'Use the exact request path including /central prefix when signing.',
                'Register the domain via POST /license/activate before calling /license/check.',
                'Never expose the secret API key in frontend code.',
            ],
        ];
    }
}
