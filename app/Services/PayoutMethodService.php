<?php

namespace App\Services;

use App\Models\PayoutMethod;
use Illuminate\Database\Eloquent\Collection;

class PayoutMethodService
{
    public function create(string $userId, array $data): PayoutMethod
    {
        $data['user_id'] = $userId;

        if ($data['is_default'] ?? false) {
            PayoutMethod::where('user_id', $userId)->update(['is_default' => false]);
        } else {
            $hasDefault = PayoutMethod::where('user_id', $userId)
                ->where('is_default', true)
                ->exists();
            if (!$hasDefault) {
                $data['is_default'] = true;
            }
        }

        return PayoutMethod::create($data);
    }

    public function update(string $id, string $userId, array $data): PayoutMethod
    {
        $method = $this->find($id, $userId);

        if (isset($data['is_default']) && $data['is_default']) {
            PayoutMethod::where('user_id', $userId)->update(['is_default' => false]);
        }

        $method->update($data);
        return $method->fresh();
    }

    public function delete(string $id, string $userId): void
    {
        $method = $this->find($id, $userId);
        $wasDefault = $method->is_default;
        $method->delete();

        if ($wasDefault) {
            $next = PayoutMethod::where('user_id', $userId)->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }
    }

    public function setDefault(string $id, string $userId): PayoutMethod
    {
        $method = $this->find($id, $userId);

        PayoutMethod::where('user_id', $userId)->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return $method->fresh();
    }

    public function find(string $id, string $userId): PayoutMethod
    {
        $method = PayoutMethod::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$method) {
            abort(404, 'Payout method not found');
        }

        return $method;
    }

    public function getUserMethods(string $userId): Collection
    {
        return PayoutMethod::where('user_id', $userId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function format(PayoutMethod $method): array
    {
        return [
            'id' => $method->id,
            'method_type' => $method->method_type,
            'is_default' => $method->is_default,
            'bank_name' => $method->bank_name,
            'account_number' => $this->maskAccount($method->account_number),
            'paypal_email' => $method->paypal_email,
            'wallet_type' => $method->wallet_type,
            'wallet_mobile_number' => $this->maskPhone($method->wallet_mobile_number),
        ];
    }

    private function maskAccount(?string $account): ?string
    {
        if (!$account || strlen($account) < 4) {
            return null;
        }
        return '****' . substr($account, -4);
    }

    private function maskPhone(?string $phone): ?string
    {
        if (!$phone || strlen($phone) < 4) {
            return null;
        }
        return '****' . substr($phone, -4);
    }
}
