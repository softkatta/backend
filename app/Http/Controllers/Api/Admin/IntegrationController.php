<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Integration;
use App\Services\IntegrationCredentialService;
use App\Services\SmtpMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

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
        $integrations = Integration::orderBy('name')->get()->map(fn (Integration $integration): array => $this->formatIntegration($integration));

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

        $integration = new Integration([
            'name' => $data['name'],
            'provider' => $data['provider'],
            'is_active' => $data['is_active'] ?? false,
        ]);

        if (isset($data['credentials'])) {
            $integration->credentials = $this->mergeCredentials(
                $integration,
                (array) $data['credentials'],
            );
        }

        $integration->save();

        return $this->success($this->formatIntegration($integration), 'Integration created.', 201);
    }

    public function update(Request $request, Integration $integration, SmtpMailService $smtpMail): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        if (isset($data['credentials'])) {
            $incoming = (array) $data['credentials'];
            $passwordChanging = $this->isSecretChanging($integration, $incoming);

            $data['credentials'] = $this->mergeCredentials(
                $integration,
                $incoming,
            );

            if ($integration->provider === 'email_smtp' && $passwordChanging) {
                $smtp = $smtpMail->resolveConfig(null, $data['credentials']);

                if (! $smtp) {
                    return $this->error('SMTP is incomplete. Enter host, username, password, and from email.', 422);
                }

                try {
                    $smtpMail->verifyConnection($smtp);
                } catch (Throwable $e) {
                    return $this->error(
                        'SMTP login failed. Password was not saved. '.$smtpMail->formatFailureMessage($e),
                        422,
                    );
                }
            }
        }

        $integration->update($data);

        return $this->success($this->formatIntegration($integration->fresh()), 'Integration updated.');
    }

    public function destroy(Integration $integration): JsonResponse
    {
        $this->permanentlyDelete($integration);

        return $this->success(null, 'Integration deleted.');
    }

    public function sendTestEmail(Request $request, Integration $integration, SmtpMailService $smtpMail): JsonResponse
    {
        if ($integration->provider !== 'email_smtp') {
            return $this->error('Test email is only available for the SMTP integration.', 422);
        }

        $integration = $integration->fresh();

        $data = $request->validate([
            'to' => ['required', 'email'],
        ]);

        $to = $data['to'];

        if (! $smtpMail->resolveConfig($integration)) {
            return $this->error('SMTP is not fully configured. Enter host, username, password, and from email, then click Save. Or configure MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD in backend/.env.', 422);
        }

        try {
            $smtpMail->sendTest($integration, $to);
        } catch (Throwable $e) {
            return $this->error($smtpMail->formatFailureMessage($e), 422);
        }

        return $this->success(null, "Test email sent to {$to}.");
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeCredentials(Integration $integration, array $incoming): array
    {
        try {
            $existing = (array) $integration->credentials;
        } catch (Throwable) {
            $existing = [];
        }

        $secretFields = self::SECRET_FIELDS[$integration->provider] ?? ['api_secret', 'secret', 'password', 'access_token'];
        $merged = array_merge($existing, $incoming);

        foreach ($merged as $key => $value) {
            if ($value === null || $value === '••••••••') {
                unset($merged[$key]);
            }
        }

        foreach ($secretFields as $secretKey) {
            $incomingValue = $incoming[$secretKey] ?? null;

            if (
                isset($existing[$secretKey])
                && $existing[$secretKey] !== ''
                && ($incomingValue === null || $incomingValue === '' || $incomingValue === '••••••••')
            ) {
                $merged[$secretKey] = $existing[$secretKey];
            }
        }

        foreach ($incoming as $key => $value) {
            if ($value !== '') {
                continue;
            }

            if (in_array($key, $secretFields, true)) {
                continue;
            }

            $merged[$key] = '';
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
        if (isset($merged['key_id']) || isset($merged['api_key'])) {
            $merged['key_id'] = (string) ($merged['key_id'] ?? $merged['api_key'] ?? '');
        }

        if (isset($merged['api_secret']) || isset($merged['secret'])) {
            $merged['api_secret'] = (string) ($merged['api_secret'] ?? $merged['secret'] ?? '');
        }

        unset($merged['api_key'], $merged['secret']);

        return $this->removeEmptyValues($merged);
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

        return $this->removeEmptyValues($merged);
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

        return $this->removeEmptyValues($merged);
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

        return $this->removeEmptyValues($merged);
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function mergeStripe(array $merged): array
    {
        if (isset($merged['publishable_key']) || isset($merged['key_id'])) {
            $merged['publishable_key'] = (string) ($merged['publishable_key'] ?? $merged['key_id'] ?? '');
        }

        if (isset($merged['secret_key']) || isset($merged['api_secret'])) {
            $merged['secret_key'] = (string) ($merged['secret_key'] ?? $merged['api_secret'] ?? '');
        }

        unset($merged['key_id'], $merged['api_secret']);

        return $this->removeEmptyValues($merged);
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function isSecretChanging(Integration $integration, array $incoming): bool
    {
        $secretFields = self::SECRET_FIELDS[$integration->provider] ?? [];

        foreach ($secretFields as $field) {
            $value = $incoming[$field] ?? null;

            if (is_string($value) && $value !== '' && $value !== '••••••••') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function removeEmptyValues(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                unset($values[$key]);
            }
        }

        return $values;
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

    /**
     * @return array<string, mixed>
     */
    private function formatIntegration(Integration $integration): array
    {
        $data = $integration->getAttributes();
        unset($data['credentials']);

        try {
            $credentials = (array) $integration->credentials;
        } catch (Throwable) {
            $credentials = [];
        }

        $data['credentials'] = $this->maskCredentials($integration->provider, $credentials);
        $data['is_configured'] = app(IntegrationCredentialService::class)->isIntegrationReady($integration);

        return $data;
    }
}
