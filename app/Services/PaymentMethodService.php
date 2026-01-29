<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Pagination\Paginator;

class PaymentMethodService
{
    public function create(User $user, array $data): PaymentMethod
    {
        if ($data['is_default'] ?? false) {
            PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        }

        return PaymentMethod::create([
            'user_id' => $user->id,
            'method_type' => $data['method_type'],
            'is_default' => $data['is_default'] ?? false,
            'card_holder_name' => $data['card_holder_name'] ?? null,
            'card_last_four' => $data['card_last_four'] ?? null,
            'card_brand' => $data['card_brand'] ?? null,
            'expiry_month' => $data['expiry_month'] ?? null,
            'expiry_year' => $data['expiry_year'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'paypal_email' => $data['paypal_email'] ?? null,
            'payment_token' => $data['payment_token'],
        ]);
    }

    public function getUserMethods(User $user, int $limit = 20, int $page = 1): array
    {
        $query = PaymentMethod::where('user_id', $user->id);
        $total = $query->count();
        
        $methods = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'data' => $methods,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($page * $limit) < $total,
            ],
        ];
    }

    public function update(PaymentMethod $method, array $data): PaymentMethod
    {
        if (($data['is_default'] ?? false) && !$method->is_default) {
            PaymentMethod::where('user_id', $method->user_id)->update(['is_default' => false]);
        }

        $method->update([
            'card_holder_name' => $data['card_holder_name'] ?? $method->card_holder_name,
            'billing_address' => $data['billing_address'] ?? $method->billing_address,
            'paypal_email' => $data['paypal_email'] ?? $method->paypal_email,
            'is_default' => $data['is_default'] ?? $method->is_default,
        ]);

        return $method;
    }

    public function setDefault(PaymentMethod $method): PaymentMethod
    {
        PaymentMethod::where('user_id', $method->user_id)->update(['is_default' => false]);
        $method->update(['is_default' => true]);
        return $method;
    }

    public function delete(PaymentMethod $method): bool
    {
        $wasDefault = $method->is_default;
        $method->delete();

        if ($wasDefault) {
            $next = PaymentMethod::where('user_id', $method->user_id)->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return true;
    }
}
