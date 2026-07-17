<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceProfileService
{
    /** @var array<string, string|null> */
    private ?array $cache = null;

    public function company(): array
    {
        $defaults = config('invoice.company');
        $s = $this->settings();

        $name = trim((string) ($s['company_name'] ?? $defaults['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) config('app.name', 'SoftKatta Solutions'));
        }
        if ($name === '' || $name === 'Laravel') {
            $name = 'SoftKatta Solutions';
        }

        $logoPath = $s['company_logo'] ?? null;

        return [
            'name' => $name,
            'tagline' => $s['company_tagline'] ?? $defaults['tagline'],
            'address' => $s['company_address'] ?? $defaults['address'],
            'email' => $s['billing_email'] ?? $s['support_email'] ?? $defaults['email'],
            'website' => $s['company_website'] ?? $defaults['website'],
            'phone' => $s['company_phone'] ?? $defaults['phone'],
            'account_no' => $s['invoice_account_no'] ?? $defaults['account_no'],
            'account_name' => $s['invoice_account_name'] ?? $defaults['account_name'],
            'ifsc_code' => $s['invoice_ifsc_code'] ?? ($defaults['ifsc_code'] ?? ''),
            'branch' => $s['invoice_branch'] ?? $defaults['branch'],
            'gst_number' => $s['gst_number'] ?? $defaults['gst_number'],
            'upi_vpa' => $s['upi_vpa'] ?? ($defaults['upi_vpa'] ?? ''),
            'signatory' => $s['invoice_signatory'] ?? $defaults['signatory'],
            'signature' => $s['invoice_signature'] ?? null,
            'signature_url' => $this->storageUrl($s['invoice_signature'] ?? null),
            'initials' => $s['company_initials'] ?? $this->initialsFromName($name, $defaults['initials']),
            'logo' => $logoPath,
            'logo_url' => $this->storageUrl($logoPath),
            'favicon' => $s['favicon'] ?? null,
            'favicon_url' => $this->storageUrl($s['favicon'] ?? null),
        ];
    }

    public function displayName(): string
    {
        $name = trim((string) ($this->company()['name'] ?? ''));

        if ($name !== '' && $name !== 'Laravel') {
            return $name;
        }

        return 'SoftKatta Solutions';
    }

    public function clearCache(): void
    {
        $this->cache = null;
    }

    /** @return array<string, mixed> */
    public function branding(): array
    {
        $company = $this->company();

        return [
            'company_name' => $company['name'],
            'company_tagline' => $company['tagline'],
            'company_address' => $company['address'],
            'company_phone' => $company['phone'],
            'company_website' => $company['website'],
            'company_logo' => $company['logo'],
            'company_logo_url' => $company['logo_url'],
            'favicon' => $company['favicon'],
            'favicon_url' => $company['favicon_url'],
            'gst_number' => $company['gst_number'],
            'gst_enabled' => $this->hasGstNumber(),
            'gst_rate' => $this->gstRate(),
            'default_currency' => $this->currency(),
            'support_email' => $this->settings()['support_email'] ?? config('invoice.company.email'),
            'company_description' => trim((string) ($this->settings()['company_description'] ?? '')),
            'brand_short_name' => trim((string) ($this->settings()['brand_short_name'] ?? '')),
        ];
    }

    public function terms(): string
    {
        return $this->settings()['invoice_terms'] ?? config('invoice.terms');
    }

    public function currency(): string
    {
        return $this->settings()['default_currency'] ?? 'INR';
    }

    public function gstRate(): float
    {
        if (! $this->hasGstNumber()) {
            return 0;
        }

        $raw = $this->settings()['gst_rate'] ?? null;
        if ($raw === null || $raw === '') {
            return (float) config('invoice.gst_rate', 18);
        }

        $rate = (float) $raw;

        return max(0, min(100, $rate));
    }

    public function hasGstNumber(): bool
    {
        $gstNumber = trim((string) ($this->company()['gst_number'] ?? ''));

        return $gstNumber !== '';
    }

    public function invoicePrefix(): string
    {
        $prefix = trim((string) ($this->settings()['invoice_prefix'] ?? config('invoice.invoice_prefix', 'SK-INV')));

        return $prefix !== '' ? $prefix : 'SK-INV';
    }

    public function invoiceNumberStart(): int
    {
        return max(1, (int) ($this->settings()['invoice_number_start'] ?? config('invoice.invoice_number_start', 1)));
    }

    public function previewNextInvoiceNumber(): string
    {
        $prefix = $this->invoicePrefix();
        $next = $this->peekNextInvoiceSequence();

        return $this->formatInvoiceNumber($prefix, $next);
    }

    public function allocateInvoiceNumber(): string
    {
        return DB::transaction(function (): string {
            $prefix = $this->invoicePrefix();
            $start = $this->invoiceNumberStart();

            $row = Setting::query()->lockForUpdate()->firstOrCreate(
                ['key' => 'invoice_number_next'],
                ['value' => (string) $this->bootstrapNextSequence($prefix, $start), 'group' => 'invoice']
            );

            $current = max($start, (int) $row->value);
            $row->update(['value' => (string) ($current + 1)]);
            $this->cache = null;

            return $this->formatInvoiceNumber($prefix, $current);
        });
    }

    public function syncInvoiceNumberStart(int $start): void
    {
        $start = max(1, $start);
        $row = Setting::query()->firstOrCreate(
            ['key' => 'invoice_number_next'],
            ['value' => (string) $start, 'group' => 'invoice']
        );

        if ((int) $row->value < $start) {
            $row->update(['value' => (string) $start]);
        }
    }

    private function peekNextInvoiceSequence(): int
    {
        $start = $this->invoiceNumberStart();
        $stored = Setting::query()->where('key', 'invoice_number_next')->value('value');

        if ($stored !== null && $stored !== '') {
            return max($start, (int) $stored);
        }

        return $this->bootstrapNextSequence($this->invoicePrefix(), $start);
    }

    private function bootstrapNextSequence(string $prefix, int $start): int
    {
        $escaped = preg_quote($prefix, '/');
        $max = Invoice::withoutGlobalScopes()
            ->where('invoice_number', 'like', $prefix.'-%')
            ->pluck('invoice_number')
            ->map(function (string $number) use ($escaped): int {
                if (preg_match('/^'.$escaped.'-(\d+)$/', $number, $matches)) {
                    return (int) $matches[1];
                }

                return 0;
            })
            ->max();

        return max($start, (int) $max + 1);
    }

    private function formatInvoiceNumber(string $prefix, int $sequence): string
    {
        return $prefix.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function logoAbsolutePath(?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $local = public_path('storage/'.ltrim($path, '/'));

        return file_exists($local) ? $local : null;
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function initialsFromName(string $name, string $fallback): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= strtoupper(substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : $fallback;
    }

    /** @return array<string, string|null> */
    private function settings(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $keys = [
            'company_name',
            'company_tagline',
            'company_address',
            'company_phone',
            'company_website',
            'company_logo',
            'favicon',
            'company_initials',
            'billing_email',
            'support_email',
            'gst_number',
            'gst_rate',
            'invoice_account_no',
            'invoice_account_name',
            'invoice_ifsc_code',
            'invoice_branch',
            'invoice_terms',
            'upi_vpa',
            'invoice_signatory',
            'invoice_signature',
            'default_currency',
            'invoice_prefix',
            'invoice_number_start',
            'invoice_number_next',
        ];

        $this->cache = Setting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->all();

        return $this->cache;
    }
}
