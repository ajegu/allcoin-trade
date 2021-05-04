<?php


namespace AllCoinTrade\Notification\Handler;


use AllCoinTrade\Exception\NotificationPublishException;
use AllCoinTrade\Notification\Event\NotificationBuyEvent;
use AllCoinTrade\Notification\NotificationAdapter;

class NotificationBuyHandler
{
    public function __construct(
        private string $topic,
        private NotificationAdapter $notificationAdapter
    ) {}

    /**
     * @param NotificationBuyEvent $event
     * @throws NotificationPublishException
     */
    public function publishBuyEvent(NotificationBuyEvent $event): void
    {
        $this->notificationAdapter->publish($event, $this->topic);
    }
}
