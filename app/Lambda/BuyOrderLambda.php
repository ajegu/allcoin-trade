<?php


namespace AllCoinTrade\Lambda;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\Helper\DateTimeHelper;
use AllCoinCore\Helper\SerializerHelper;
use AllCoinCore\Lambda\LambdaInterface;
use AllCoinTrade\Model\Order;
use AllCoinTrade\Notification\Event\NotificationBuyEvent;
use AllCoinTrade\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class BuyOrderLambda implements LambdaInterface
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

        /** @var NotificationBuyEvent $event */
        $event = $this->serializerHelper->deserialize(json_decode($message, true), NotificationBuyEvent::class);

        $pair = $event->getPair();
        $orders = $this->orderRepository->findAllByPair($pair);

        if (count($orders) > 0) {
            $lastOrder = $orders[count($orders) - 1];

            if ($lastOrder->getSide() === Order::SIDE_BUY) {
                $this->logger->info("$pair is already bought.");
                return null;
            }
        }

        $order = new Order();
        $order->setPair($pair);
        $order->setCreatedAt($this->dateTimeHelper->now());
        $order->setSide(Order::SIDE_BUY);
        $order->setUnitPrice($event->getAskPrice());
        $order->setTotal(10);
        $order->setAmount(round(10 / $event->getAskPrice(), 8));

        $this->orderRepository->save($order);

        $this->logger->debug("$pair order is created.");

        return null;
    }

}
