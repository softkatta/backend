<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Integration;
use App\Services\SmtpMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends BaseApiController
{
    /** @var array<string, list<string>> */
    private const SECRET_FIELDS = [
        'razorpay' => ['api_secret', 'secret'],
        'email_smtp' => ['password'],
        'whatsapp' => ['access_token'],
        'pusher' => ['secret'],
        'stripe' => ['secret_key', 'api_secret'],
    ];

    public function index(): JsonResponse
    {
        $integrations = Integration::orderBy('name')->get()->map(function (Integration $integration): array {
            $data = $integration->toArray();
            $data['credentials'] = $this->maskCredentials(
                $integration->provider,
                (array) $integration->credentials,
            );

            return $data;
        });

        return $this->success($integrations);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $integration = Integration::create($data);

        return $this->success($integration, 'Integration created.', 201);
    }

    public function update(Request $request, Integration $integration): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['credentials'])) {
            $data['credentials'] = $this->mergeCredentials(
                $integration,
                (array) $data['credentials'],
            );
        }

        $integration->update($data);

        $fresh = $integration->fresh();
        $response = $fresh->toArray();
        $response['credentials'] = $this->maskCredentials($fresh->provider, (array) $fresh->credentials);

        return $this->success($response, 'Integration updated.');
    }

    public function destroy(Integration $integration): JsonResponse
    {
        $integration->delete();

        return $this->success(null, 'Integration deleted.');
    }

    public function sendTestEmail(Request $request, Integration $integration, SmtpMailService $smtpMail): JsonResponse
    {
        if ($integration->provider !== 'email_smtp') {
            return $this->error('Test email is only available for the SMTP integration.', 422);
        }

        $data = $request->validate([
            'to' => ['nullable', 'email'],
            'credentials' => ['nullable', 'array'],
        ]);

        $to = $data['to'] ?? $request->user()?->email;

        if (! $to) {
            return $this->error('Recipient email address is required.', 422);
        }

        try {
            $smtpMail->sendTest($integration, $to, $data['credentials'] ?? null);
        } catch (\Throwable $e) {
            return $this->error('Failed to send test email: '.$e->getMessage(), 422);
        }

        return $this->success(null, "Test email sent to {$to}.");
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeCredentials(Integration $integration, array $incoming): array
    {
        $existing = (array) $integration->credentials;
        $merged = array_merge($existing, $incoming);

        foreach ($merged as $key => $value) {
            if ($value === null || $value === '' || $value === '••••••••') {
                unset($merged[$key]);
            }
        }

        return match ($integration->provider) {
            'razorpay' => $this->mergeRazorpay($merged),
            'email_smtp' => $this->mergeEmailSmtp($merged),
            'whatsapp' => $this->mergeWhatsapp($merged),
            'pusher' => $this->mergePusher($merged),
            'stripe' => $this->mergeStripe($merged),
            default => $merged,
        };
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function mergeRazorpay(array $merged): array
    {
        $keyId = $merged['key_id'] ?? $merged['api_key'] ?? null;
        $keySecret = $merged['api_secret'] ?? $merged['secret'] ?? null;

        if ($keyId && $keySecret) {
            $merged['key_id'] = (string) $keyId;
            $merged['api_secret'] = (string) $keySecret;
        }

        unset($merged['api_key'], $merged['secret']);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function mergeEmailSmtp(array $merged): array
    {
        if (isset($merged['port'])) {
            $merged['port'] = (string) $merged['port'];
        }

        if (($merged['encryption'] ?? '') === 'none') {
            $merged['encryption'] = '';
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function mergeWhatsapp(array $merged): array
    {
        if (! isset($merged['api_version']) || $merged['api_version'] === '') {
            $merged['api_version'] = 'v21.0';
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function mergePusher(array $merged): array
    {
        if (! isset($merged['scheme']) || $merged['scheme'] === '') {
            $merged['scheme'] = 'https';
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function mergeStripe(array $merged): array
    {
        $publishable = $merged['publishable_key'] ?? $merged['key_id'] ?? null;
        $secret = $merged['secret_key'] ?? $merged['api_secret'] ?? null;

        if ($publishable) {
            $merged['publishable_key'] = (string) $publishable;
        }

        if ($secret) {
            $merged['secret_key'] = (string) $secret;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function maskCredentials(string $provider, array $credentials): array
    {
        $masked = [];
        $secretFields = self::SECRET_FIELDS[$provider] ?? ['api_secret', 'secret', 'password', 'access_token'];

        foreach ($credentials as $key => $value) {
            $stringValue = (string) $value;

            if ($stringValue === '') {
                continue;
            }

            if (in_array($key, $secretFields, true)) {
                $masked[$key] = '••••••••';
            } else {
                $masked[$key] = $stringValue;
            }
        }

        return $masked;
    }
}
