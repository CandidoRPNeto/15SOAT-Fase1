<?php

namespace App\Services;

use App\Contracts\PaymentServiceInterface;
use App\Models\ServiceOrder;
use Illuminate\Support\Str;

class StubPaymentService implements PaymentServiceInterface
{
    public function processPayment(ServiceOrder $order): array
    {
        // Stub: simulates external payment gateway approval
        return [
            'success' => true,
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            'message' => "Pagamento de R$ {$order->total_amount} processado com sucesso.",
        ];
    }
}
