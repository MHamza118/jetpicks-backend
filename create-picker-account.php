<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

// Load values from .env if the file exists.
if (is_file(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->safeLoad();
}

function env_value(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function platform_connect_account_id(): string
{
    $accountId = env_value('STRIPE_PLATFORM_CONNECT_ACCOUNT_ID');

    if (empty($accountId)) {
        throw new RuntimeException('STRIPE_PLATFORM_CONNECT_ACCOUNT_ID is missing.');
    }

    return $accountId;
}

function base_url(): string
{
    $configured = env_value('APP_URL');
    if (!empty($configured)) {
        return rtrim($configured, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return rtrim($scheme . '://' . $host, '/');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function log_stripe_error(string $message, array $context = []): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;

    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $logFile = __DIR__ . '/storage/logs/stripe-connect.log';
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    error_log($line);
}

function pdo(): PDO
{
    $host = env_value('DB_HOST', '127.0.0.1');
    $port = env_value('DB_PORT', '3306');
    $database = env_value('DB_DATABASE');
    $username = env_value('DB_USERNAME');
    $password = env_value('DB_PASSWORD', '');
    $charset = env_value('DB_CHARSET', 'utf8mb4');

    if (!$database || !$username) {
        throw new RuntimeException('Database environment variables are missing.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function is_uuid(string $value): bool
{
    return (bool) preg_match('/^[0-9a-fA-F-]{36}$/', $value);
}

function user_has_picker_role(array $user): bool
{
    $roles = json_decode((string) ($user['roles'] ?? '[]'), true);

    if (!is_array($roles)) {
        return false;
    }

    foreach ($roles as $role) {
        if (strtoupper((string) $role) === 'PICKER') {
            return true;
        }
    }

    return false;
}

function resolve_stripe_country(?string $country): string
{
    $map = [
        'United Kingdom' => 'GB',
        'United States' => 'US',
        'Poland' => 'PL',
        'Italy' => 'IT',
        'France' => 'FR',
        'Germany' => 'DE',
        'Spain' => 'ES',
        'Romania' => 'RO',
        'Hungary' => 'HU',
    ];

    return $map[$country ?? ''] ?? 'GB';
}

function find_picker(PDO $pdo, string $pickerId): array
{
    $stmt = $pdo->prepare('SELECT id, full_name, email, country, roles, stripe_connect_account_id, stripe_connect_status FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $pickerId]);
    $picker = $stmt->fetch();

    if (!$picker) {
        json_response(['message' => 'Picker not found.'], 404);
    }

    if (!user_has_picker_role($picker)) {
        json_response(['message' => 'This user is not marked as a picker.'], 400);
    }

    return $picker;
}

function resolve_picker_id(): string
{
    // Picker is expected to be logged in, so session should contain picker_id.
    session_start();

    $pickerId = (string) ($_SESSION['picker_id'] ?? $_POST['picker_id'] ?? $_GET['picker_id'] ?? '');

    // Keep session in sync if picker_id comes from request.
    if ($pickerId !== '') {
        $_SESSION['picker_id'] = $pickerId;
    }

    return $pickerId;
}

try {
    $stripeSecretKey = env_value('STRIPE_SECRET_KEY');
    if (!$stripeSecretKey) {
        throw new RuntimeException('STRIPE_SECRET_KEY is missing.');
    }

    Stripe::setApiKey($stripeSecretKey);

    $pickerId = resolve_picker_id();
    if ($pickerId === '' || !is_uuid($pickerId)) {
        json_response(['message' => 'A valid picker_id is required. Make sure the picker is logged in and session contains picker_id.'], 422);
    }

    $redirect = filter_var($_GET['redirect'] ?? $_POST['redirect'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $pdo = pdo();
    $picker = find_picker($pdo, $pickerId);

    // Create the Express account only once.
    if (empty($picker['stripe_connect_account_id'])) {
        $account = Account::create([
            'type' => 'express',
            'country' => resolve_stripe_country($picker['country'] ?? null),
            'email' => $picker['email'],
            'capabilities' => [
                'transfers' => ['requested' => true],
                // In GB, Stripe requires card_payments capability with transfers.
                'card_payments' => ['requested' => true],
            ],
            'metadata' => [
                'picker_id' => $pickerId,
                'role' => 'PICKER',
                // Helpful trace field so you can verify which platform created the account.
                'platform_connect_account_id' => platform_connect_account_id(),
            ],
        ]);

        $update = $pdo->prepare('UPDATE users SET stripe_connect_account_id = :account_id, stripe_connect_status = :status, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'account_id' => $account->id,
            'status' => 'pending',
            'id' => $pickerId,
        ]);

        $picker['stripe_connect_account_id'] = $account->id;
        $picker['stripe_connect_status'] = 'pending';
    }

    // Create the hosted Stripe onboarding link that the frontend can open.
    $accountLink = AccountLink::create([
        'account' => $picker['stripe_connect_account_id'],
        'refresh_url' => base_url() . '/create-picker-account.php?picker_id=' . urlencode($pickerId) . '&redirect=1',
        'return_url' => base_url() . '/onboarding-complete.php?picker_id=' . urlencode($pickerId),
        'type' => 'account_onboarding',
    ]);

    if ($redirect) {
        header('Location: ' . $accountLink->url, true, 302);
        exit;
    }

    json_response([
        'message' => 'Stripe onboarding link created successfully.',
        'picker_id' => $pickerId,
        'platform_connect_account_id' => platform_connect_account_id(),
        'stripe_account_id' => $picker['stripe_connect_account_id'],
        'onboarding_url' => $accountLink->url,
        'expires_at' => $accountLink->expires_at,
    ]);
} catch (ApiErrorException $e) {
    log_stripe_error('Stripe API error while creating picker account', [
        'message' => $e->getMessage(),
        'stripe_code' => method_exists($e, 'getStripeCode') ? $e->getStripeCode() : null,
        'http_status' => method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : null,
    ]);

    json_response([
        'message' => 'Stripe could not create the connected account.',
        'error' => $e->getMessage(),
    ], 500);
} catch (PDOException $e) {
    log_stripe_error('Database error while creating picker account', ['message' => $e->getMessage()]);

    json_response([
        'message' => 'Database error while creating the picker account.',
    ], 500);
} catch (Throwable $e) {
    log_stripe_error('Unexpected error while creating picker account', ['message' => $e->getMessage()]);

    json_response([
        'message' => 'Unexpected error while creating the picker account.',
    ], 500);
}
