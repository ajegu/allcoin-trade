<?php


namespace AllCoinTrade\Notification;


use AllCoinTrade\Exception\NotificationPublishException;
use AllCoinCore\Helper\SerializerHelper;
use AllCoinTrade\Notification\Event\NotificationEvent;
use Aws\Sns\Exception\SnsException;
use Aws\Sns\SnsClient;
use Psr\Log\LoggerInterface;

class NotificationAdapter
{
    public function __construct(
        private SnsClient $snsClient,
        private SerializerHelper $serializerHelper,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * @param NotificationEvent $event
     * @param string $topicArn
     * @throws NotificationPublishException
     */
    public function publish(NotificationEvent $event, string $topicArn): void
    {
        $args = [
            'Message' => $this->serializerHelper->serialize($event),
            'TopicArn' => $topicArn
        ];

        try {
            $this->snsClient->publish($args);
        } catch (SnsException $exception) {
            $message = 'The event cannot be sent!';
            $this->logger->error($message, [
                'topic' => $topicArn,
                'message' => $exception->getMessage()
            ]);
            throw new NotificationPublishException($message);
        }
    }
}
