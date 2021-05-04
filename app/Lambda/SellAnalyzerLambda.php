<?php


namespace AllCoinTrade\Lambda;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Exception\LambdaInvokeException;
use AllCoinCore\Helper\DateTimeHelper;
use AllCoinCore\Lambda\Event\LambdaPriceSearchEvent;
use AllCoinCore\Lambda\Handler\LambdaPriceHandler;
use AllCoinCore\Lambda\LambdaInterface;
use AllCoinCore\Model\Price;
use AllCoinTrade\Exception\NotificationPublishException;
use AllCoinTrade\Model\Order;
use AllCoinTrade\Notification\Event\NotificationSellEvent;
use AllCoinTrade\Notification\Handler\NotificationSellHandler;
use AllCoinTrade\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class SellAnalyzerLambda implements LambdaInterface
{
    const STOP_LOSS_PERCENT = 5;
    const BREAK_EVENT_PERCENT = 3;

    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private LambdaPriceHandler $lambdaPriceHandler,
        private DateTimeHelper $dateTimeHelper,
        private LoggerInterface $logger,
        private NotificationSellHandler $notificationSellHandler
    ) {}

    /**
     * @param array $event
     * @return array|null
     * @throws NotificationPublishException
     * @throws ItemReadException
     * @throws LambdaInvokeException
     */
    public function __invoke(array $event): array|null
    {
        $orders = $this->orderRepository->findAll();

        $groupedOrders = [];
        foreach ($orders as $order) {
            $groupedOrders[$order->getPair()][] = $order;
        }

        foreach ($groupedOrders as $pair => $orders) {

            /** @var Order $lastOrder */
            $lastOrder = $orders[count($orders) - 1];

            if ($lastOrder->getSide() === Order::SIDE_SELL) {
                $this->logger->debug("No buy order for $pair.");
            }

            $event = new LambdaPriceSearchEvent();
            $event->setPair($pair);
            $event->setStartAt($lastOrder->getCreatedAt());
            $event->setEndAt($this->dateTimeHelper->now());
            $prices = $this->lambdaPriceHandler->invokePriceSearch($event);

            if (count($prices) === 0) {
                $this->logger->error("No prices found for $pair.");
                continue;
            }

            /** @var Price $lastPrice */
            $lastPrice = $prices[count($prices) - 1];

            $stopLoss = $lastOrder->getUnitPrice() - ($lastOrder->getUnitPrice() * (self::STOP_LOSS_PERCENT / 100));

            if ($lastPrice->getBidPrice() <= $stopLoss) {
                $this->logger->debug("Stop loss reach for $pair.");
                $this->sendEvent($lastOrder, $lastPrice->getBidPrice());
                continue;
            }

            $latestTopPrice = $lastPrice;
            foreach ($prices as $price) {
                if ($latestTopPrice->getBidPrice() < $price->getBidPrice()) {
                    $latestTopPrice = $price;
                }
            }

            // if the latest price is under the latest top price - 10% => break event
            $breakEventPrice = $latestTopPrice->getBidPrice() - ($latestTopPrice->getBidPrice() * (self::BREAK_EVENT_PERCENT / 100));
            if ($lastPrice->getBidPrice() <= $breakEventPrice && $lastPrice->getBidPrice() > $lastOrder->getUnitPrice()) {
                $this->logger->debug("Break event reach for $pair.");
                $this->sendEvent($lastOrder, $lastPrice->getBidPrice());
                return null;
            }
            $this->logger->debug("Nothing to do for $pair.");
        }

        return null;
    }

    /**
     * @param Order $order
     * @param float $bidPrice
     * @throws NotificationPublishException
     */
    private function sendEvent(Order $order, float $bidPrice): void
    {
        $event = new NotificationSellEvent();
        $event->setOrder($order);
        $event->setBidPrice($bidPrice);

        $this->notificationSellHandler->publishSellEvent($event);
    }

}
