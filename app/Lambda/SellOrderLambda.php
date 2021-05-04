<?php


namespace AllCoinTrade\Lambda;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\Helper\DateTimeHelper;
use AllCoinCore\Helper\SerializerHelper;
use AllCoinCore\Lambda\LambdaInterface;
use AllCoinTrade\Model\Order;
use AllCoinTrade\Notification\Event\NotificationBuyEvent;
use AllCoinTrade\Notification\Event\NotificationSellEvent;
use AllCoinTrade\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class SellOrderLambda implements LambdaInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private OrderRepositoryInterface $orderRepository,
        private SerializerHelper $serializerHelper,
        private DateTimeHelper $dateTimeHelper
    ) {}

    /**
     * @param array $event
     * @return array|null
     * @throws ItemReadException
     * @throws ItemSaveException
     */
    public function __invoke(array $event): array|null
    {
        $records = $event['Records'] ?? [];
        $message = '';
        foreach ($records as $record) {
            $sns = $record['Sns'] ?? [];
            $message = $sns['Message'] ?? '';
            break;
        }

        if (!$message) {
            $this->logger->error('No message found from event.', [
                'event' => $event
            ]);
            return null;
        }

        /** @var NotificationSellEvent $event */
        $event = $this->serializerHelper->deserialize(json_decode($message, true), NotificationSellEvent::class);
        $buyOrder = $event->getOrder();

        $order = new Order();
        $order->setPair($buyOrder->getPair());
        $order->setCreatedAt($this->dateTimeHelper->now());
        $order->setSide(Order::SIDE_SELL);
        $order->setUnitPrice($event->getBidPrice());
        $order->setTotal(round($event->getBidPrice() * $buyOrder->getAmount(), 8));
        $order->setAmount($buyOrder->getAmount());

        $this->orderRepository->save($order);

        $this->logger->debug("{$buyOrder->getPair()} order is created.");

        return null;
    }

}
