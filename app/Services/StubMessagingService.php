<?php

namespace App\Services;

use App\Contracts\MessagingServiceInterface;
use App\Models\ServiceOrder;
use Illuminate\Support\Str;

class StubMessagingService implements MessagingServiceInterface
{
    public function send(ServiceOrder $order, string $message): array
    {
        // Stub: simulates external messaging gateway dispatch
        return [
            'success' => true,
            'message_id' => 'MSG-' . strtoupper(Str::random(10)),
            'message' => "Mensagem enviada ao cliente {$order->client->name}: {$message}",
        ];
    }

    public function notifyOrderCreated(ServiceOrder $order): array
    {
        $vehicle = $order->vehicle;
        $message = "Olá {$order->client->name}! Sua OS {$order->number} foi criada. "
            . "Veículo: {$vehicle->brand} {$vehicle->model} ({$vehicle->plate}). "
            . "Status: Recebida.";

        return $this->send($order, $message);
    }

    public function notifyBudgetReady(ServiceOrder $order): array
    {
        $message = "Olá {$order->client->name}! O orçamento da sua OS {$order->number} está disponível. "
            . "Valor total: R$ " . number_format((float) $order->total_amount, 2, ',', '.') . ". "
            . "Acesse o sistema para aprovar ou cancelar.";

        return $this->send($order, $message);
    }

    public function notifyPickupReady(ServiceOrder $order): array
    {
        $vehicle = $order->vehicle;
        $message = "Olá {$order->client->name}! Seu veículo {$vehicle->brand} {$vehicle->model} ({$vehicle->plate}) "
            . "está pronto! Por favor, retire em até 24 horas para evitar taxas de permanência.";

        return $this->send($order, $message);
    }

    public function notifyPickupOverdue(ServiceOrder $order): array
    {
        $vehicle = $order->vehicle;
        $message = "Olá {$order->client->name}! Seu veículo {$vehicle->brand} {$vehicle->model} ({$vehicle->plate}) "
            . "está aguardando retirada há mais de 24 horas. "
            . "Uma taxa de permanência será aplicada. Por favor, retire o veículo o quanto antes.";

        return $this->send($order, $message);
    }
}
