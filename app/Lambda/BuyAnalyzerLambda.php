<?php


namespace AllCoinTrade\Lambda;




use AllCoinCore\Exception\LambdaInvokeException;
use AllCoinCore\Helper\DateTimeHelper;
use AllCoinCore\Lambda\Event\LambdaPriceSearchEvent;
use AllCoinCore\Lambda\Handler\LambdaAssetHandler;
use AllCoinCore\Lambda\Handler\LambdaPriceHandler;
use AllCoinCore\Lambda\LambdaInterface;
use AllCoinCore\Model\Price;
use AllCoinTrade\Exception\NotificationPublishException;
use AllCoinTrade\Model\Order;
use AllCoinTrade\Notification\Event\NotificationBuyEvent;
use AllCoinTrade\Notification\Handler\NotificationBuyHandler;
use AllCoinTrade\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class BuyAnalyzerLambda implements LambdaInterface
{
    const TIME_LIMIT = 'PT30M';
    const RISE_LIMIT = 33;

    public function __construct(
        private DateTimeHelper $dateTimeHelper,
        private LambdaAssetHandler $lambdaAssetHandler,
        private LambdaPriceHandler $lambdaPriceHandler,
        private LoggerInterface $logger,
        private NotificationBuyHandler $notificationBuyHandler,
        private OrderRepositoryInterface $orderRepository,
    )
    {
    }


    /**
     * @param array $event
     * @return array|null
     * @throws LambdaInvokeException
     * @throws NotificationPublishException
     */
    public function __invoke(array $event): array|null
    {
        $now = $this->dateTimeHelper->now();
        $currentPeriod = [
            'startAt' => $this->dateTimeHelper->sub($now, self::TIME_LIMIT),
            'endAt' => $now,
        ];

        $previousPeriod = [
            'startAt' => $this->dateTimeHelper->sub($currentPeriod['startAt'], self::TIME_LIMIT),
            'endAt' => $currentPeriod['startAt']
        ];

        $assets = $this->lambdaAssetHandler->invokeAssetList();
        foreach ($assets as $asset) {

            $pair = $asset->getPair();

            $currentPrices = $this->getPrices($pair, $currentPeriod);

            if (count($currentPrices) === 0) {
                $this->logger->warning("No prices found for $pair current period.");
                continue;
            }

            $previousPrices = $this->getPrices($pair, $previousPeriod);

            if (count($previousPrices) === 0) {
                $this->logger->warning("No prices found for $pair previous period");
                continue;
            }

            $oldCurrentAskPrice = $currentPrices[0]->getAskPrice();
            $lastCurrentPrice = $currentPrices[count($currentPrices) - 1];
            $lastCurrentAskPrice = $lastCurrentPrice->getAskPrice();

            $rate = round(100 - ($oldCurrentAskPrice * 100 / $lastCurrentAskPrice), 2);
            $trace = [
                'last' => $lastCurrentAskPrice,
                'old' => $oldCurrentAskPrice,
                'rate' => "$rate %"
            ];
            if ($lastCurrentAskPrice <= $oldCurrentAskPrice) {
                $this->logger->debug("KO: The current period is a down for $pair, looking for a up.", $trace);
                continue;
            }

            $this->logger->debug("OK: The last price is lower than old price for $pair", $trace);

            $oldPreviousPrice = $previousPrices[0];
            $oldPreviousAskPrice = $oldPreviousPrice->getAskPrice();
            $lastPreviousAskPrice = $previousPrices[count($previousPrices) - 1]->getAskPrice();

            $rate = round(100 - ($oldPreviousAskPrice * 100 / $lastPreviousAskPrice), 2);
            $trace = [
                'last' => $lastPreviousAskPrice,
                'old' => $oldPreviousAskPrice,
                'rate' => "$rate %"
            ];
            if ($oldPreviousAskPrice <= $lastPreviousAskPrice) {
                $this->logger->debug("KO: The previous period is a up for $pair, looking for a down.", $trace);
                continue;
            }

            $this->logger->debug("OK: The previous period is a down for $pair.", $trace);

//            $this->logger->alert("The period is OK for buying $pair.", [
//                'current' => [
//                    'last' => $lastCurrentAskPrice,
//                    'old' => $oldCurrentAskPrice,
//                ],
//                'previous' => [
//                    'last' => $lastPreviousAskPrice,
//                    'old' => $oldPreviousAskPrice
//                ]
//            ]);


            $downPercent = round(100 - ($lastPreviousAskPrice * 100 / $oldPreviousAskPrice), 2);
            $upPercent = round(100 - ($oldCurrentAskPrice * 100 / $lastCurrentAskPrice), 2);
            $trace = [
                'down' => $downPercent,
                'up' => $upPercent,
            ];
            if ($upPercent > $downPercent) {
                $this->logger->alert("Too late for buying $pair.", $trace);
                continue;
            }

            $this->logger->alert("Good time for buying $pair.", $trace);

            $rate = round(100 - ($upPercent * 100 / $downPercent), 2);
            $trace = [
                'down' => $downPercent,
                'up' => $upPercent,
                'rate' => $rate . '%',
                'limit' => self::RISE_LIMIT
            ];
            if ($rate < self::RISE_LIMIT) {
                $this->logger->alert("KO: The rate is too low for buying $pair.", $trace);
                continue;
            }
            $this->logger->alert("OK: The rate is OK for buying $pair.", $trace);

//            if ($upPercent < self::RISE_LIMIT) {
//                $this->logger->alert("The up ($upPercent %) isn't good for buying $pair.", [
//                    'rateLimit' => self::RISE_LIMIT,
//                    'timeLimit' => self::TIME_LIMIT
//                ]);
//                continue;
//            }

            $orders = $this->orderRepository->findAllByPair($pair);

            if (count($orders) > 0) {
                $lastOrder = $orders[count($orders) - 1];

                if ($lastOrder->getSide() === Order::SIDE_BUY) {
                    $this->logger->info("$pair is already bought.");
                    continue;
                }
            }

            $event = new NotificationBuyEvent();
            $event->setPair($pair);
            $event->setAskPrice($lastCurrentPrice->getAskPrice());
            $event->setBidPrice($lastCurrentPrice->getBidPrice());

            $this->notificationBuyHandler->publishBuyEvent($event);
            $this->logger->debug("Send buy event for $pair.", [
                'last' => $lastCurrentAskPrice,
                'old' => $oldCurrentAskPrice,
            ]);

        }

        return null;
    }

    /**
     * @param string $pair
     * @param array $period
     * @return Price[]
     * @throws LambdaInvokeException
     */
    private function getPrices(string $pair, array $period): array
    {
        $event = new LambdaPriceSearchEvent();
        $event->setPair($pair);
        $event->setStartAt($period['startAt']);
        $event->setEndAt($period['endAt']);

        return $this->lambdaPriceHandler->invokePriceSearch($event);
    }
}
