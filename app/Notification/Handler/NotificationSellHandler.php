<?php


namespace AllCoinTrade\Notification\Handler;


use AllCoinTrade\Exception\NotificationPublishException;
use AllCoinTrade\Notification\Event\NotificationBuyEvent;
use AllCoinTrade\Notification\Event\NotificationSellEvent;
use AllCoinTrade\Notification\NotificationAdapter;

class NotificationSellHandler
{
    public function __construct(
        private string $topic,
        private NotificationAdapter $notificationAdapter
    ) {}

    /**
     * @param NotificationSellEvent $event
     * @throws NotificationPublishException
     */
    public function publishSellEvent(NotificationSellEvent $event): void
    {
        $this->notificationAdapter->publish($event, $this->topic);
    }
}
