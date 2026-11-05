<?php

declare(strict_types=1);

namespace PayamBridge\Laravel\Notifications;

use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use PayamBridge\Contracts\Capability;
use PayamBridge\Contracts\SendsText;
use PayamBridge\Data\ChatRef;
use PayamBridge\Data\MessageReceipt;
use PayamBridge\Laravel\ConnectionManager;

final readonly class PayamBridgeChannel
{
    public function __construct(private ConnectionManager $manager) {}

    public function send(object $notifiable, Notification $notification): ?MessageReceipt
    {
        if (! method_exists($notifiable, 'routeNotificationFor') || ! method_exists($notification, 'toPayamBridge')) {
            throw new InvalidArgumentException('Notification requires toPayamBridge and a routable notifiable.');
        }
        $route = $notifiable->routeNotificationFor('payambridge', $notification);
        if ($route === null) {
            return null;
        }
        if (! $route instanceof NotificationRoute) {
            throw new InvalidArgumentException('PayamBridge notification route must be a NotificationRoute.');
        }
        $message = $notification->toPayamBridge($notifiable);
        if (! $message instanceof TextMessage) {
            throw new InvalidArgumentException('toPayamBridge must return a TextMessage.');
        }
        $connection = $this->manager->connection($route->connection);
        $connection->capabilities()->require(Capability::SendsText);
        if (! $connection instanceof SendsText) {
            throw new InvalidArgumentException('Driver does not implement SendsText.');
        }

        return $connection->sendText(new ChatRef($connection->id(), $route->chatId), $message->text);
    }
}
