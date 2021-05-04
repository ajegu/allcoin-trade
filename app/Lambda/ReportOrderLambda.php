<?php


namespace AllCoinTrade\Lambda;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Exception\LambdaInvokeException;
use AllCoinCore\Helper\DateTimeHelper;
use AllCoinCore\Lambda\Event\LambdaPriceSearchEvent;
use AllCoinCore\Lambda\Handler\LambdaPriceHandler;
use AllCoinCore\Lambda\LambdaInterface;
use AllCoinCore\Model\Price;
use AllCoinTrade\Model\Order;
use AllCoinTrade\Repository\OrderRepositoryInterface;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;

class ReportOrderLambda implements LambdaInterface
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private LoggerInterface $logger,
        private LambdaPriceHandler $lambdaPriceHandler,
        private DateTimeHelper $dateTimeHelper
    ) {}

    /**
     * @param array $event
     * @return array|null
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

        $globalReport = [];
        $globalBuy = 0;
        $globalSell = 0;
        $globalInProgress = 0;
        $currentInvest = 0;

        foreach ($groupedOrders as $pair => $orders) {
            $lastOrder = null;
            $orderBuy = 0;
            $orderSell = 0;
            $orderInProgress = 0;

            /** @var Order $order */
            foreach ($orders as $order) {
                if ($order->getSide() === Order::SIDE_BUY) {
                    $globalBuy += $order->getTotal();
                    $orderBuy += $order->getTotal();
                    $currentInvest += $order->getTotal();
                } else {
                    $globalSell += $order->getTotal();
                    $orderSell += $order->getTotal();
                    $currentInvest -= $order->getTotal();
                }
                $lastOrder = $order;
            }

            $progressReport = [];
            if ($lastOrder->getSide() === Order::SIDE_BUY) {
                $event = new LambdaPriceSearchEvent();
                $event->setPair($pair);
                $event->setStartAt($lastOrder->getCreatedAt());
                $event->setEndAt($this->dateTimeHelper->now());
                $prices = $this->lambdaPriceHandler->invokePriceSearch($event);

                /** @var Price $lastPrice */
                $lastPrice = $prices[count($prices) - 1];

                $orderInProgress = round($lastPrice->getBidPrice() * $lastOrder->getAmount(), 8);
                $globalInProgress += $orderInProgress;

                $progressReport = [
                    'amount' => $lastOrder->getAmount(),
                    'order' => $lastOrder->getUnitPrice(),
                    'current' => $lastPrice?->getBidPrice()
                ];
            }

            $report = $this->generateReport($orderSell, $orderInProgress, $orderBuy);
            $report = array_merge($report,$progressReport);

            $globalReport[$pair] = $report;
            $this->logger->info("Report for $pair", $report);
        }

        $report = $this->generateReport($globalSell, $globalInProgress, $globalBuy);
        $report = array_merge($report, [
            'invest' => $currentInvest
        ]);

        $this->logger->info('Global Report', [
            'Total' => $report
        ]);


        return $globalReport;
    }

    /**
     * @param float $sell
     * @param float $progress
     * @param float $buy
     * @return array
     */
    #[ArrayShape(['pnl' => "float", 'rate' => "string", 'buy' => "float", 'sell' => "float", 'progress' => "float"])]
    private function generateReport(float $sell, float $progress, float $buy): array
    {
        return [
            'pnl' => round($sell + $progress - $buy, 8),
            'rate' => round(100 - ($buy * 100 / ($sell + $progress)), 2) . '%',
            'buy' => $buy,
            'sell' => $sell,
            'progress' => $progress
        ];
    }
}
