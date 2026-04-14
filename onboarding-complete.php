<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

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

function html_response(string $html, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
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

function resolve_picker_id(): string
{
    session_start();

    $pickerId = (string) ($_GET['picker_id'] ?? $_POST['picker_id'] ?? ($_SESSION['picker_id'] ?? ''));

    if ($pickerId !== '') {
        $_SESSION['picker_id'] = $pickerId;
    }

    return $pickerId;
}

function render_message_page(string $title, string $message, string $status, ?string $actionUrl = null, ?string $actionLabel = null): void
{
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $messageEsc = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $statusEsc = htmlspecialchars(strtoupper($status), ENT_QUOTES, 'UTF-8');
    $actionHtml = '';

    if ($actionUrl && $actionLabel) {
        $actionHtml = '<p><a class="button" href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }

    html_response(<<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$titleEsc}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f8fc; color: #1f2937; margin: 0; padding: 40px; }
        .card { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 999px; background: #e0f2fe; color: #075985; font-weight: 700; letter-spacing: .04em; margin-bottom: 16px; }
        .button { display: inline-block; margin-top: 12px; padding: 12px 18px; border-radius: 10px; background: #111827; color: #fff; text-decoration: none; font-weight: 600; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">{$statusEsc}</div>
        <h1>{$titleEsc}</h1>
        <p>{$messageEsc}</p>
        {$actionHtml}
        <p class="muted">You can close this page after reviewing the status.</p>
    </div>
</body>
</html>
HTML);
}

try {
    $stripeSecretKey = env_value('STRIPE_SECRET_KEY');
    if (!$stripeSecretKey) {
        throw new RuntimeException('STRIPE_SECRET_KEY is missing.');
    }

    Stripe::setApiKey($stripeSecretKey);

    $pickerId = resolve_picker_id();
    if ($pickerId === '' || !is_uuid($pickerId)) {
        render_message_page('Onboarding status', 'Please provide a valid picker_id to continue.', 'error');
    }

    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT id, full_name, email, country, roles, stripe_connect_account_id, stripe_connect_status FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $pickerId]);
    $picker = $stmt->fetch();

    if (!$picker) {
        render_message_page('Onboarding status', 'Picker not found.', 'error');
    }

    if (!user_has_picker_role($picker)) {
        render_message_page('Onboarding status', 'This user is not marked as a picker.', 'error');
    }

    if (empty($picker['stripe_connect_account_id'])) {
        $retryUrl = base_url() . '/create-picker-account.php?picker_id=' . urlencode($pickerId) . '&redirect=1';
        render_message_page(
            'Stripe onboarding not started',
            'No Stripe connected account exists yet for this picker. Start onboarding first, then return to this page.',
            'pending',
            $retryUrl,
            'Start onboarding'
        );
    }

    // Ask Stripe for the latest account state after the picker returns from onboarding.
    $account = \Stripe\Account::retrieve($picker['stripe_connect_account_id']);

    // Account is considered fully active only when both charges and payouts are enabled.
    $chargesEnabled = (bool) ($account->charges_enabled ?? false);
    $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);
    $detailsSubmitted = (bool) ($account->details_submitted ?? false);
    $disabledReason = $account->requirements->disabled_reason ?? null;

    if ($chargesEnabled && $payoutsEnabled) {
        $status = 'verified';
        $message = 'Your Stripe Express account is fully active. You can now receive transfers.';
    } elseif ($disabledReason) {
        $status = 'restricted';
        $message = 'Your account needs attention before it can receive transfers. Stripe reported: ' . $disabledReason . '.';
    } elseif ($detailsSubmitted) {
        $status = 'pending';
        $message = 'Your onboarding details were submitted, but Stripe has not fully activated the account yet.';
    } else {
        $status = 'pending';
        $message = 'Onboarding has not been fully completed yet. Please continue with Stripe to finish setup.';
    }

    $update = $pdo->prepare('UPDATE users SET stripe_connect_status = :status, updated_at = NOW() WHERE id = :id');
    $update->execute([
        'status' => $status,
        'id' => $pickerId,
    ]);

    $actionUrl = ($chargesEnabled && $payoutsEnabled)
        ? null
        : base_url() . '/create-picker-account.php?picker_id=' . urlencode($pickerId) . '&redirect=1';

    $actionLabel = ($chargesEnabled && $payoutsEnabled) ? null : 'Open onboarding again';

    render_message_page(
        'Stripe onboarding status',
        $message . '\n\nPlatform account: ' . platform_connect_account_id(),
        $status,
        $actionUrl,
        $actionLabel
    );
} catch (ApiErrorException $e) {
    log_stripe_error('Stripe API error while checking onboarding status', [
        'message' => $e->getMessage(),
        'stripe_code' => method_exists($e, 'getStripeCode') ? $e->getStripeCode() : null,
        'http_status' => method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : null,
    ]);

    render_message_page('Stripe onboarding status', 'Stripe could not retrieve the connected account status right now.', 'error');
} catch (PDOException $e) {
    log_stripe_error('Database error while checking onboarding status', ['message' => $e->getMessage()]);

    render_message_page('Stripe onboarding status', 'A database error occurred while updating the picker account status.', 'error');
} catch (Throwable $e) {
    log_stripe_error('Unexpected error while checking onboarding status', ['message' => $e->getMessage()]);

    render_message_page('Stripe onboarding status', 'An unexpected error occurred while checking onboarding status.', 'error');
}
