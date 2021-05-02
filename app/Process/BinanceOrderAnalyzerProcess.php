<?php


namespace AllCoinTrade\Process;


use AllCoinCore\Builder\EventOrderBuilder;
use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Dto\RequestDtoInterface;
use AllCoinCore\Dto\ResponseDtoInterface;
use AllCoinCore\Exception\NotificationHandlerException;
use AllCoinCore\Model\AssetPair;
use AllCoinCore\Model\AssetPairPrice;
use AllCoinCore\Model\EventEnum;
use AllCoinCore\Model\Order;
use AllCoinCore\Notification\Handler\OrderAnalyzerNotificationHandler;
use AllCoinCore\Process\ProcessInterface;
use AllCoinCore\Repository\AssetPairPriceRepositoryInterface;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\AssetRepositoryInterface;
use AllCoinCore\Service\DateTimeService;
use Psr\Log\LoggerInterface;

class BinanceOrderAnalyzerProcess implements ProcessInterface
{
    public function __construct(
        private AssetRepositoryInterface $assetRepository,
        private AssetPairRepositoryInterface $assetPairRepository,
        private AssetPairPriceRepositoryInterface $assetPairPriceRepository,
        private LoggerInterface $logger,
        private DateTimeService $dateTimeService,
        private OrderAnalyzerNotificationHandler $orderAnalyzerNotificationHandler,
        private EventOrderBuilder $eventOrderBuilder,
        private int $stopLossPercent,
        private int $breakEventPercent
    )
    {
    }

    /**
     * @param RequestDtoInterface|null $dto
     * @param array $params
     * @return ResponseDtoInterface|null
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function handle(RequestDtoInterface $dto = null, array $params = []): ?ResponseDtoInterface
    {
        $assetPairs = $this->assetPairRepository->findAll();

        foreach ($assetPairs as $assetPair) {

            $lastOrder = $assetPair->getLastOrder();

            if ($lastOrder === null || $lastOrder->getDirection() === Order::SELL) {
                $this->logger->debug('Not a buy order.');
                continue;
            }

            $prices = $this->assetPairPriceRepository->findAllByDateRange(
                $assetPair->getId(),
                $lastOrder->getCreatedAt(),
                $this->dateTimeService->now()
            );

            $lastPrice = $prices[count($prices) - 1] ?? null;
            if (!$lastPrice) {
                $this->logger->debug('No prices found.');
                continue;
            }

            $orderUnitPrice = $lastOrder->getAmount() / $lastOrder->getQuantity();

            $stopLoss = $orderUnitPrice - ($orderUnitPrice * ($this->stopLossPercent / 100));

            $lastBidPrice = $lastPrice->getBidPrice();
            if ($lastBidPrice <= $stopLoss) {
                $this->logger->debug('Stop loss reach.');
                $this->createEvent($assetPair, $lastPrice, EventEnum::STOP_LOSS);
                continue;
            }

            $latestTopPrice = $lastPrice;
            foreach ($prices as $price) {
                if ($latestTopPrice->getBidPrice() < $price->getBidPrice()) {
                    $latestTopPrice = $price;
                }
            }

            // if the latest price is under the latest top price - 10% => break event
            $topBidPrice = $latestTopPrice->getBidPrice();
            if ($lastBidPrice <= $topBidPrice - ($topBidPrice * ($this->breakEventPercent / 100)) && $lastBidPrice > $orderUnitPrice) {
                $this->logger->debug('Break event reach.');
                $this->createEvent($assetPair, $lastPrice, EventEnum::BREAK_EVENT);
            }
        }

        $this->logger->debug('Nothing to do.');

        return null;
    }

    /**
     * @param AssetPair $assetPair
     * @param AssetPairPrice $price
     * @param string $eventName
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    private function createEvent(AssetPair $assetPair, AssetPairPrice $price, string $eventName): void
    {
        $asset = $this->assetRepository->findOneByAssetPairId($assetPair->getId());
        $event = $this->eventOrderBuilder->build(
            $eventName,
            $asset,
            $assetPair,
            $price
        );
        $this->orderAnalyzerNotificationHandler->dispatch($event);
    }

}
