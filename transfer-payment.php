<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Transfer;

// Your Stripe platform Connect account id.
const PLATFORM_CONNECT_ACCOUNT_ID = 'acct_1TKDwB64GEDaW3TO';

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

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) ($stmt->fetchColumn() ?: 0) > 0;
}

function json_decode_array(?string $json): array
{
    if (!$json) {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
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

try {
    $stripeSecretKey = env_value('STRIPE_SECRET_KEY');
    if (!$stripeSecretKey) {
        throw new RuntimeException('STRIPE_SECRET_KEY is missing.');
    }

    Stripe::setApiKey($stripeSecretKey);

    $orderId = (string) ($_POST['order_id'] ?? '');

    // Allow a JSON request body as a fallback, but POST is the primary input.
    if ($orderId === '') {
        $rawBody = file_get_contents('php://input');
        if ($rawBody !== false && $rawBody !== '') {
            $payload = json_decode($rawBody, true);
            if (is_array($payload)) {
                $orderId = (string) ($payload['order_id'] ?? '');
            }
        }
    }

    if ($orderId === '' || !is_uuid($orderId)) {
        json_response(['message' => 'A valid order_id is required.'], 422);
    }

    $pdo = pdo();

    // Load the order and the assigned picker in one query.
    $stmt = $pdo->prepare(
        'SELECT
            o.id,
            o.orderer_id,
            o.assigned_picker_id,
            o.reward_amount,
            o.accepted_counter_offer_amount,
            o.currency,
            o.status,
            o.payment_status,
            o.stripe_payment_intent_id,
            o.delivered_at,
            o.delivery_confirmed_at,
            o.auto_confirmed,
            u.email AS picker_email,
            u.full_name AS picker_name,
            u.roles AS picker_roles,
            u.stripe_connect_account_id,
            u.stripe_connect_status
         FROM orders o
         INNER JOIN users u ON u.id = o.assigned_picker_id
         WHERE o.id = :order_id
         LIMIT 1'
    );
    $stmt->execute(['order_id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        json_response(['message' => 'Order not found.'], 404);
    }

    if (!user_has_picker_role([
        'roles' => $order['picker_roles'] ?? '[]',
    ])) {
        json_response(['message' => 'The assigned user is not marked as a picker.'], 400);
    }

    // The app records picker confirmation at delivered_at and buyer confirmation at delivery_confirmed_at.
    if ($order['status'] !== 'COMPLETED' || empty($order['delivered_at']) || empty($order['delivery_confirmed_at']) || !empty($order['auto_confirmed'])) {
        json_response([
            'message' => 'Delivery must be confirmed by both buyer and picker before payout can be sent.',
            'order_status' => $order['status'],
            'delivered_at' => $order['delivered_at'],
            'delivery_confirmed_at' => $order['delivery_confirmed_at'],
            'auto_confirmed' => (bool) $order['auto_confirmed'],
        ], 400);
    }

    if ($order['payment_status'] !== 'PAID') {
        json_response(['message' => 'The order payment is not marked as PAID yet.'], 400);
    }

    if (empty($order['stripe_connect_account_id'])) {
        json_response(['message' => 'The picker does not have a Stripe connected account.'], 400);
    }

    if (($order['stripe_connect_status'] ?? null) !== 'verified') {
        json_response(['message' => 'The picker Stripe account is not verified yet.'], 400);
    }

    // Re-check the live Stripe account before sending funds.
    $connectedAccount = \Stripe\Account::retrieve($order['stripe_connect_account_id']);
    $chargesEnabled = !empty($connectedAccount->charges_enabled);
    $payoutsEnabled = !empty($connectedAccount->payouts_enabled);

    if (!$chargesEnabled || !$payoutsEnabled) {
        json_response([
            'message' => 'The picker Stripe account is not fully active yet (charges and payouts must both be enabled).',
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
        ], 400);
    }

    // Use the accepted counter-offer if present; otherwise use the base reward.
    $grossAmount = (float) ($order['accepted_counter_offer_amount'] ?? 0);
    if ($grossAmount <= 0) {
        $grossAmount = (float) ($order['reward_amount'] ?? 0);
    }

    $grossAmountPence = (int) round($grossAmount * 100);
    if ($grossAmountPence <= 0) {
        json_response(['message' => 'Order amount is invalid.'], 400);
    }

    $platformFeePercent = (float) env_value('PLATFORM_FEE_PERCENT', '10');
    $platformFeeFixedPence = (int) env_value('PLATFORM_FEE_FIXED_PENCE', '0');

    $platformFeePence = (int) round(($grossAmountPence * $platformFeePercent / 100) + $platformFeeFixedPence);
    $pickerAmountPence = max($grossAmountPence - $platformFeePence, 0);

    if ($pickerAmountPence <= 0) {
        json_response(['message' => 'Calculated payout amount is zero. Check the fee configuration.'], 400);
    }

    // Try to avoid sending the same payout twice by looking for a saved transfer ID.
    $paymentStmt = $pdo->prepare('SELECT id, metadata FROM payments WHERE order_id = :order_id ORDER BY created_at DESC LIMIT 1');
    $paymentStmt->execute(['order_id' => $orderId]);
    $paymentRow = $paymentStmt->fetch();

    if ($paymentRow) {
        $paymentMetadata = json_decode_array($paymentRow['metadata'] ?? null);
        if (!empty($paymentMetadata['stripe_transfer_id'])) {
            json_response([
                'message' => 'This order has already been paid out to the picker.',
                'stripe_transfer_id' => $paymentMetadata['stripe_transfer_id'],
            ], 200);
        }
    }

    // Create the Stripe Transfer to the picker's connected account.
    $transfer = Transfer::create(
        [
            'amount' => $pickerAmountPence,
            'currency' => strtolower((string) ($order['currency'] ?: 'gbp')),
            'destination' => $order['stripe_connect_account_id'],
            'metadata' => [
                'order_id' => $orderId,
                'picker_id' => $order['assigned_picker_id'],
                'gross_amount_pence' => (string) $grossAmountPence,
                'platform_fee_pence' => (string) $platformFeePence,
                'platform_connect_account_id' => PLATFORM_CONNECT_ACCOUNT_ID,
            ],
        ],
        [
            'idempotency_key' => 'order-payout-' . $orderId,
        ]
    );

    // Save the transfer ID wherever the current schema allows it.
    if ($paymentRow) {
        $paymentMetadata = json_decode_array($paymentRow['metadata'] ?? null);
        $paymentMetadata['stripe_transfer_id'] = $transfer->id;
        $paymentMetadata['payout_status'] = 'TRANSFERRED';
        $paymentMetadata['gross_amount_pence'] = $grossAmountPence;
        $paymentMetadata['platform_fee_pence'] = $platformFeePence;
        $paymentMetadata['picker_payout_amount_pence'] = $pickerAmountPence;

        $updatePayment = $pdo->prepare('UPDATE payments SET metadata = :metadata, updated_at = NOW() WHERE id = :id');
        $updatePayment->execute([
            'metadata' => json_encode($paymentMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'id' => $paymentRow['id'],
        ]);
    }

    if (column_exists($pdo, 'orders', 'payout_status')) {
        $orderUpdateSql = 'UPDATE orders SET payout_status = :payout_status';
        $params = ['payout_status' => 'TRANSFERRED'];

        if (column_exists($pdo, 'orders', 'stripe_transfer_id')) {
            $orderUpdateSql .= ', stripe_transfer_id = :stripe_transfer_id';
            $params['stripe_transfer_id'] = $transfer->id;
        }

        $orderUpdateSql .= ' WHERE id = :id';
        $params['id'] = $orderId;

        $updateOrder = $pdo->prepare($orderUpdateSql);
        $updateOrder->execute($params);
    }

    json_response([
        'message' => 'Transfer created successfully.',
        'platform_connect_account_id' => PLATFORM_CONNECT_ACCOUNT_ID,
        'order_id' => $orderId,
        'stripe_transfer_id' => $transfer->id,
        'destination_account' => $order['stripe_connect_account_id'],
        'gross_amount_pence' => $grossAmountPence,
        'platform_fee_pence' => $platformFeePence,
        'picker_amount_pence' => $pickerAmountPence,
        'currency' => $transfer->currency,
        'status' => $transfer->status,
    ]);
} catch (ApiErrorException $e) {
    log_stripe_error('Stripe API error while creating transfer', [
        'message' => $e->getMessage(),
        'stripe_code' => method_exists($e, 'getStripeCode') ? $e->getStripeCode() : null,
        'http_status' => method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : null,
    ]);

    json_response([
        'message' => 'Stripe transfer failed.',
        'error' => $e->getMessage(),
    ], 500);
} catch (PDOException $e) {
    log_stripe_error('Database error while creating transfer', ['message' => $e->getMessage()]);

    json_response([
        'message' => 'Database error while creating the transfer.',
    ], 500);
} catch (Throwable $e) {
    log_stripe_error('Unexpected error while creating transfer', ['message' => $e->getMessage()]);

    json_response([
        'message' => 'Unexpected error while creating the transfer.',
    ], 500);
}
