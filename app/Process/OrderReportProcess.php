<?php


namespace AllCoinTrade\Process;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Dto\RequestDtoInterface;
use AllCoinCore\Dto\ResponseDtoInterface;
use AllCoinCore\Model\Order;
use AllCoinCore\Process\ProcessInterface;
use AllCoinCore\Repository\AssetPairPriceRepositoryInterface;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\AssetRepositoryInterface;
use AllCoinCore\Repository\OrderRepositoryInterface;
use AllCoinCore\Service\DateTimeService;
use DateInterval;
use Psr\Log\LoggerInterface;

class OrderReportProcess implements ProcessInterface
{
    public function __construct(
        private AssetRepositoryInterface $assetRepository,
        private AssetPairRepositoryInterface $assetPairRepository,
        private OrderRepositoryInterface $orderRepository,
        private AssetPairPriceRepositoryInterface $assetPairPriceRepository,
        private DateTimeService $dateTimeService,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * @param RequestDtoInterface|null $dto
     * @param array $params
     * @return ResponseDtoInterface|null
     * @throws ItemReadException
     */
    public function handle(RequestDtoInterface $dto = null, array $params = []): ?ResponseDtoInterface
    {
        $ordersGrouped = $this->orderRepository->findAllGroupByAssetPairId();

        $globalBuy = 0;
        $globalSell = 0;
        $globalInProgress = 0;

        foreach ($ordersGrouped as $assetPairId => $orders) {

            $assetPair = $this->assetPairRepository->findOneById($assetPairId);
            $asset = $this->assetRepository->findOneByAssetPairId($assetPairId);

            $symbol = $asset->getName() . $assetPair->getName();

            uasort($orders, function (Order $a, Order $b) {
                return $a->getCreatedAt() > $b->getCreatedAt() ? 1 : -1;
            });

            $lastOrder = null;
            $orderBuy = 0;
            $orderSell = 0;
            $orderInProgress = 0;

            foreach ($orders as $order) {
                if ($order->getDirection() === Order::BUY) {
                    $globalBuy += $order->getAmount();
                    $orderBuy += $order->getAmount();
                } else {
                    $globalSell += $order->getAmount();
                    $orderSell += $order->getAmount();
                }
                $lastOrder = $order;
            }

            $lastBuyPrice = 0;
            if ($lastOrder->getDirection() === Order::BUY) {
                $prices = $this->assetPairPriceRepository->findAllByDateRange(
                    $assetPairId,
                    $lastOrder->getCreatedAt()->sub(new DateInterval('PT1M')),
                    $this->dateTimeService->now()
                );

                $lastPrice = $prices[count($prices) - 1];

                $lastBuyPrice = round($lastOrder->getAmount() / $lastOrder->getQuantity(), 5);
                $orderInProgress = round($lastPrice->getBidPrice() * $lastOrder->getQuantity(), 5);
                $globalInProgress += $orderInProgress;
            }

            $this->logger->info('Asset report ' . $symbol, [
                'balance' => $orderSell + $orderInProgress - $orderBuy,
                'buyPrice' => $lastBuyPrice,
                'assetPairId' => $assetPairId
            ]);
        }

        $this->logger->info('Global report', [
            'balance' => $globalSell + $globalInProgress - $globalBuy
        ]);

        return null;
    }

}
