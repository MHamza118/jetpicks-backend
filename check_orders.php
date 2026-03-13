<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$order1 = App\Models\Order::find('019cd1ac-1a64-72ad-ab07-ffec24daf64d');
$order2 = App\Models\Order::find('019cd637-dbce-7310-a3a4-ff2a4ca11677');

echo "=== Order 1 (019cd1ac-1a64-72ad-ab07-ffec24daf64d) ===" . PHP_EOL;
echo "Status: " . $order1->status . PHP_EOL;
echo "Payment Status: " . $order1->payment_status . PHP_EOL;
echo "Has Assigned Picker: " . ($order1->assigned_picker_id ? "Yes ({$order1->assigned_picker_id})" : "No") . PHP_EOL;

$notif1 = App\Models\Notification::where('entity_id', $order1->id)
    ->where('type', 'PAYMENT_CONFIRMED')
    ->first();
echo "Has Payment Notification: " . ($notif1 ? "Yes" : "No") . PHP_EOL;

echo PHP_EOL;

echo "=== Order 2 (019cd637-dbce-7310-a3a4-ff2a4ca11677) ===" . PHP_EOL;
echo "Status: " . $order2->status . PHP_EOL;
echo "Payment Status: " . $order2->payment_status . PHP_EOL;
echo "Has Assigned Picker: " . ($order2->assigned_picker_id ? "Yes ({$order2->assigned_picker_id})" : "No") . PHP_EOL;

$notif2 = App\Models\Notification::where('entity_id', $order2->id)
    ->where('type', 'PAYMENT_CONFIRMED')
    ->first();
echo "Has Payment Notification: " . ($notif2 ? "Yes" : "No") . PHP_EOL;
