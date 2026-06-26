<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class IntegrationCredentialService
{
    /**
     * @return array{key_id: string, key_secret: string}|null
     */
    public function razorpay(): ?array
    {
        $integration = $this->active('razorpay');

        if (! $integration) {
            return null;
        }

        $creds = (array) $integration->credentials;
        $keyId = $creds['key_id'] ?? $creds['api_key'] ?? null;
        $keySecret = $creds['api_secret'] ?? $creds['secret'] ?? null;

        if (! $keyId || ! $keySecret) {
            return null;
        }

        return [
            'key_id' => (string) $keyId,
            'key_secret' => (string) $keySecret,
        ];
    }

    public function isRazorpayConfigured(): bool
    {
        return $this->razorpay() !== null;
    }

    /**
     * @return array{host: string, port: int, username: string, password: string, encryption: ?string, from_address: string, from_name: string}|null
     */
    public function emailSmtp(): ?array
    {
        $integration = $this->active('email_smtp');

        if (! $integration) {
            return null;
        }

        $creds = (array) $integration->credentials;
        $host = $creds['host'] ?? null;
        $username = $creds['username'] ?? null;
        $password = $creds['password'] ?? null;
        $fromAddress = $creds['from_address'] ?? null;

        if (! $host || ! $username || ! $password || ! $fromAddress) {
            return null;
        }

        return [
            'host' => (string) $host,
            'port' => (int) ($creds['port'] ?? 587),
            'username' => (string) $username,
            'password' => (string) $password,
            'encryption' => isset($creds['encryption']) && $creds['encryption'] !== '' && $creds['encryption'] !== 'none'
                ? (string) $creds['encryption']
                : null,
            'from_address' => (string) $fromAddress,
            'from_name' => (string) ($creds['from_name'] ?? config('app.name')),
        ];
    }

    public function isEmailConfigured(): bool
    {
        return $this->emailSmtp() !== null;
    }

    /**
     * @return array{phone_number_id: string, access_token: string, api_version: string}|null
     */
    public function whatsapp(): ?array
    {
        $integration = $this->active('whatsapp');

        if (! $integration) {
            return null;
        }

        $creds = (array) $integration->credentials;
        $phoneNumberId = $creds['phone_number_id'] ?? null;
        $accessToken = $creds['access_token'] ?? null;

        if (! $phoneNumberId || ! $accessToken) {
            return null;
        }

        return [
            'phone_number_id' => (string) $phoneNumberId,
            'access_token' => (string) $accessToken,
            'api_version' => (string) ($creds['api_version'] ?? 'v21.0'),
        ];
    }

    public function isWhatsappConfigured(): bool
    {
        return $this->whatsapp() !== null;
    }

    /**
     * @return array{app_id: string, key: string, secret: string, cluster: string, host: ?string, port: ?int, scheme: string}|null
     */
    public function pusher(): ?array
    {
        $integration = $this->active('pusher');

        if (! $integration) {
            return null;
        }

        $creds = (array) $integration->credentials;
        $appId = $creds['app_id'] ?? null;
        $key = $creds['key'] ?? null;
        $secret = $creds['secret'] ?? null;
        $cluster = $creds['cluster'] ?? null;

        if (! $appId || ! $key || ! $secret || ! $cluster) {
            return null;
        }

        return [
            'app_id' => (string) $appId,
            'key' => (string) $key,
            'secret' => (string) $secret,
            'cluster' => (string) $cluster,
            'host' => isset($creds['host']) && $creds['host'] !== '' ? (string) $creds['host'] : null,
            'port' => isset($creds['port']) && $creds['port'] !== '' ? (int) $creds['port'] : null,
            'scheme' => (string) ($creds['scheme'] ?? 'https'),
        ];
    }

    /**
     * @return array{key: string, cluster: string, host: ?string, port: ?int, scheme: string}|null
     */
    public function pusherPublicConfig(): ?array
    {
        $config = $this->pusher();

        if (! $config) {
            return null;
        }

        return [
            'key' => $config['key'],
            'cluster' => $config['cluster'],
            'host' => $config['host'],
            'port' => $config['port'],
            'scheme' => $config['scheme'],
        ];
    }

    public function isPusherConfigured(): bool
    {
        return $this->pusher() !== null;
    }

    public function applyBroadcastingConfig(): void
    {
        $pusher = $this->pusher();

        if (! $pusher) {
            return;
        }

        Config::set('broadcasting.default', 'pusher');
        Config::set('broadcasting.connections.pusher.key', $pusher['key']);
        Config::set('broadcasting.connections.pusher.secret', $pusher['secret']);
        Config::set('broadcasting.connections.pusher.app_id', $pusher['app_id']);
        Config::set('broadcasting.connections.pusher.options.cluster', $pusher['cluster']);

        if ($pusher['host']) {
            Config::set('broadcasting.connections.pusher.options.host', $pusher['host']);
        }

        if ($pusher['port']) {
            Config::set('broadcasting.connections.pusher.options.port', $pusher['port']);
        }

        Config::set('broadcasting.connections.pusher.options.scheme', $pusher['scheme']);
        Config::set('broadcasting.connections.pusher.options.useTLS', $pusher['scheme'] === 'https');
    }

    private function active(string $provider): ?Integration
    {
        if (! Schema::hasTable('integrations')) {
            return null;
        }

        return Integration::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
    }
}
