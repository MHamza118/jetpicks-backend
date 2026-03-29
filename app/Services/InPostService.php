<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * InPost API Service
 *
 * Handles locker discovery, shipment creation, and tracking.
 * Uses OAuth 2.1 Client Credentials flow.
 *
 * ── Setup ────────────────────────────────────────────────────────────────────
 * 1. Register at merchant.inpost-group.com
 * 2. Contact InPost Integration Team for client_id + client_secret
 * 3. Request scopes: api:points:read, api:shipments:read,
 *                    api:shipments:write, api:tracking:read
 * 4. Add to .env:
 *    INPOST_CLIENT_ID=your_client_id
 *    INPOST_CLIENT_SECRET=your_client_secret
 *    INPOST_ENV=stage   (or "production")
 *    INPOST_LOCKER_FEE=3.49
 *    INPOST_HOME_FEE=7.49
 *
 * Stage base URL:      https://stage-api.inpost-group.com
 * Production base URL: https://api.inpost-group.com
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InPostService
{
    // ── InPost countries enabled for JetPicks ─────────────────────────────────
    const SUPPORTED_COUNTRIES = [
        'GB', 'PL', 'IT', 'RO', 'HU', 'BG', 'FR', 'ES', 'PT', 'CZ', 'SK',
    ];

    // ── Country name → ISO code mapping ──────────────────────────────────────
    const COUNTRY_CODES = [
        'United Kingdom'   => 'GB',
        'Poland'           => 'PL',
        'Italy'            => 'IT',
        'Romania'          => 'RO',
        'Hungary'          => 'HU',
        'Bulgaria'         => 'BG',
        'France'           => 'FR',
        'Spain'            => 'ES',
        'Portugal'         => 'PT',
        'Czech Republic'   => 'CZ',
        'Slovakia'         => 'SK',
    ];

    private string $baseUrl;
    private string $authUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private bool $isConfigured;

    public function __construct()
    {
        $env = env('INPOST_ENV', 'stage');

        if ($env === 'production') {
            $this->baseUrl = 'https://api.inpost-group.com';
            $this->authUrl = 'https://api.inpost-group.com/oauth2/token';
        } else {
            $this->baseUrl = 'https://stage-api.inpost-group.com';
            $this->authUrl = 'https://stage-api.inpost-group.com/oauth2/token';
        }

        $this->clientId     = env('INPOST_CLIENT_ID');
        $this->clientSecret = env('INPOST_CLIENT_SECRET');
        $this->isConfigured = !empty($this->clientId) && !empty($this->clientSecret);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    /**
     * Get OAuth 2.1 access token (cached for token lifetime).
     */
    private function getAccessToken(): string
    {
        if (!$this->isConfigured) {
            throw new \Exception('InPost API is not configured. Add INPOST_CLIENT_ID and INPOST_CLIENT_SECRET to your .env file.');
        }

        return Cache::remember('inpost_access_token', 550, function () {
            $response = Http::asForm()->post($this->authUrl, [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => 'openid api:points:read api:shipments:read api:shipments:write api:tracking:read',
            ]);

            if (!$response->successful()) {
                Log::error('InPost auth failed', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \Exception('InPost authentication failed. Check your credentials.');
            }

            return $response->json('access_token');
        });
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    // ── Locker Discovery ──────────────────────────────────────────────────────

    /**
     * Find InPost lockers near a city.
     *
     * @param  string $city         City name
     * @param  string $countryCode  ISO 3166-1 alpha-2 (e.g. "GB", "PL")
     * @param  float  $lat          Latitude (optional — used if city lookup fails)
     * @param  float  $lng          Longitude (optional)
     * @param  int    $limit        Max number of lockers to return
     * @return array  List of locker objects with id, name, address, distance
     */
    public function findLockers(
        string $city,
        string $countryCode,
        float $lat = 0,
        float $lng = 0,
        int $limit = 10
    ): array {
        if (!$this->isConfigured) {
            Log::warning('InPost not configured — returning empty locker list');
            return [];
        }

        if (!in_array(strtoupper($countryCode), self::SUPPORTED_COUNTRIES)) {
            return [];
        }

        try {
            // InPost Location API — search by city or coordinates
            $params = [
                'country_code' => strtoupper($countryCode),
                'limit'        => $limit,
                'type'         => 'parcel_locker',
            ];

            if ($lat && $lng) {
                $params['lat']      = $lat;
                $params['lng']      = $lng;
                $params['max_distance'] = 10000; // 10km radius
            } else {
                $params['city'] = $city;
            }

            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/v1/points", $params);

            if (!$response->successful()) {
                Log::error('InPost locker search failed', [
                    'city'    => $city,
                    'country' => $countryCode,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
                return [];
            }

            $points = $response->json('items') ?? $response->json() ?? [];

            return array_map(fn($point) => [
                'id'       => $point['name'] ?? $point['id'] ?? '',
                'name'     => $point['name'] ?? 'InPost Locker',
                'address'  => $this->formatAddress($point['address'] ?? []),
                'city'     => $point['address']['city'] ?? $city,
                'distance' => $point['distance'] ?? null,
                'open_24h' => ($point['location_247'] ?? false) === true,
                'lat'      => $point['location']['latitude'] ?? null,
                'lng'      => $point['location']['longitude'] ?? null,
            ], array_slice($points, 0, $limit));

        } catch (\Exception $e) {
            Log::error('InPost findLockers exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Resolve country name to ISO code.
     */
    public function resolveCountryCode(string $countryName): ?string
    {
        return self::COUNTRY_CODES[$countryName]
            ?? (strlen($countryName) === 2 ? strtoupper($countryName) : null);
    }

    /**
     * Check if InPost is available for a given country.
     */
    public function isAvailableIn(string $countryNameOrCode): bool
    {
        $code = $this->resolveCountryCode($countryNameOrCode) ?? strtoupper($countryNameOrCode);
        return in_array($code, self::SUPPORTED_COUNTRIES);
    }

    // ── Shipment Creation ─────────────────────────────────────────────────────

    /**
     * Create an InPost shipment when Jetbuyer selects a locker.
     * Returns the shipment with drop-off QR code (for Jetbuyer)
     * and collection code (for Jetpicker).
     *
     * @param  string $lockerId         The InPost locker point ID
     * @param  string $recipientEmail   Jetpicker's email (for collection code)
     * @param  string $recipientPhone   Jetpicker's phone
     * @param  string $recipientName    Jetpicker's name
     * @param  string $senderName       Jetbuyer's name
     * @param  string $orderId          JetPicks order ID (stored as reference)
     * @return array  Shipment details including tracking_number, qr_code_url
     */
    public function createShipment(
        string $lockerId,
        string $recipientEmail,
        string $recipientPhone,
        string $recipientName,
        string $senderName,
        string $orderId
    ): array {
        if (!$this->isConfigured) {
            throw new \Exception('InPost API is not configured. Add credentials to .env to enable locker delivery.');
        }

        $payload = [
            'receiver' => [
                'email'      => $recipientEmail,
                'phone'      => $recipientPhone,
                'first_name' => explode(' ', $recipientName)[0] ?? $recipientName,
                'last_name'  => implode(' ', array_slice(explode(' ', $recipientName), 1)) ?: '',
            ],
            'sender' => [
                'name'  => $senderName,
                'email' => env('INPOST_SENDER_EMAIL', env('MAIL_FROM_ADDRESS', 'noreply@jetpicks.app')),
            ],
            'parcels' => [
                [
                    // Default parcel size — medium locker
                    'template'   => 'medium',
                    'is_non_standard' => false,
                ],
            ],
            'custom_attributes' => [
                'reference'   => "jetpicks-{$orderId}",
                'target_point' => $lockerId,
            ],
            'service' => 'inpost_locker_standard',
        ];

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/v1/shipments", $payload);

        if (!$response->successful()) {
            Log::error('InPost shipment creation failed', [
                'locker_id' => $lockerId,
                'order_id'  => $orderId,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \Exception('Failed to create InPost shipment: ' . ($response->json('message') ?? $response->body()));
        }

        $shipment = $response->json();

        return [
            'shipment_id'      => $shipment['id'] ?? null,
            'tracking_number'  => $shipment['tracking_number'] ?? null,
            'status'           => $shipment['status'] ?? null,
            // QR code for Jetbuyer to scan at drop-off locker
            'qr_code_url'      => $shipment['qr_code']['url'] ?? null,
            // Collection code for Jetpicker (also sent by InPost via SMS/email)
            'collection_code'  => $shipment['end_of_week_collection'] ?? null,
            'target_locker_id' => $lockerId,
        ];
    }

    // ── Tracking ──────────────────────────────────────────────────────────────

    /**
     * Get tracking status for a shipment.
     */
    public function getTracking(string $trackingNumber): ?array
    {
        if (!$this->isConfigured) return null;

        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/v1/tracking/{$trackingNumber}");

            if (!$response->successful()) return null;

            $data = $response->json();
            return [
                'tracking_number' => $trackingNumber,
                'status'          => $data['status'] ?? 'unknown',
                'events'          => $data['tracking_details'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('InPost tracking failed', ['tracking' => $trackingNumber, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Fee Helpers ───────────────────────────────────────────────────────────

    /**
     * InPost locker collect fee (includes 50p admin markup).
     */
    public static function lockerFee(): float
    {
        return (float) env('INPOST_LOCKER_FEE', 3.49);
    }

    /**
     * InPost home delivery fee (locker + courier, includes 50p markup).
     */
    public static function homeFee(): float
    {
        return (float) env('INPOST_HOME_FEE', 7.49);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function formatAddress(array $address): string
    {
        return trim(implode(', ', array_filter([
            $address['line1'] ?? $address['street'] ?? null,
            $address['line2'] ?? null,
            $address['city'] ?? null,
            $address['post_code'] ?? $address['postcode'] ?? null,
        ])));
    }
}
