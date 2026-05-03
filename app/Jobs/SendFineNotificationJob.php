<?php

namespace App\Jobs;

use App\Contracts\MessagingServiceInterface;
use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendFineNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MessagingServiceInterface $messaging): void
    {
        ServiceOrder::with(['client', 'vehicle'])
            ->where('status', ServiceOrderStatus::FINALIZED)
            ->whereNotNull('finalized_at')
            ->whereNull('paid_at')
            ->where('finalized_at', '<=', now()->subHours(24))
            ->get()
            ->each(fn (ServiceOrder $order) => $messaging->notifyPickupOverdue($order));
    }
}
