<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use PayamBridge\Laravel\Notifications\PayamBridgeChannel;
use PayamBridge\Laravel\Notifications\TextMessage;

final class SmokeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return [PayamBridgeChannel::class];
    }

    public function toPayamBridge(object $notifiable): TextMessage
    {
        return new TextMessage('سلام از پروژه واقعی لاراول');
    }
}
