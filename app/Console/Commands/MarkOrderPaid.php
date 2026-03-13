<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

class MarkOrderPaid extends Command
{
    protected $signature = 'order:mark-paid {orderId}';
    protected $description = 'Manually mark an order as paid';

    public function handle()
    {
        $orderId = $this->argument('orderId');
        
        $order = Order::find($orderId);
        
        if (!$order) {
            $this->error("Order {$orderId} not found");
            return 1;
        }
        
        $order->update([
            'payment_status' => 'PAID',
            'payment_completed_at' => now(),
        ]);
        
        $this->info("Order {$orderId} marked as PAID");
        $this->info("Status: {$order->status}");
        $this->info("Payment Status: {$order->payment_status}");
        
        return 0;
    }
}
