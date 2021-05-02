<?php


namespace AllCoinTrade\Process;


use AllCoinCore\Builder\EventPriceBuilder;
use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Dto\RequestDtoInterface;
use AllCoinCore\Dto\ResponseDtoInterface;
use AllCoinCore\Exception\NotificationHandlerException;
use AllCoinCore\Model\EventEnum;
use AllCoinCore\Notification\Handler\PriceAnalyzerNotificationHandler;
use AllCoinCore\Process\ProcessInterface;
use AllCoinCore\Repository\AssetPairPriceRepositoryInterface;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\AssetRepositoryInterface;
use AllCoinCore\Service\DateTimeService;
use Psr\Log\LoggerInterface;

class BinancePriceAnalyzerProcess implements ProcessInterface
{
    const ALERT_PERCENT_PRICE_DOWN = -5;

    public function __construct(
        private AssetRepositoryInterface $assetRepository,
        private AssetPairRepositoryInterface $assetPairRepository,
        private AssetPairPriceRepositoryInterface $assetPairPriceRepository,
        private LoggerInterface $logger,
        private DateTimeService $dateTimeService,
        private PriceAnalyzerNotificationHandler $eventHandler,
        private EventPriceBuilder $eventPriceBuilder,
        private int $timeAnalytics,
        private int $alertPercentPriceUp
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
        $end = $this->dateTimeService->now();
        $start = $this->dateTimeService->sub($end, 'PT' . $this->timeAnalytics . 'M');

        $assets = $this->assetRepository->findAll();

        foreach ($assets as $asset) {
            $assetPairs = $this->assetPairRepository->findAllByAssetId($asset->getId());

            foreach ($assetPairs as $assetPair) {
                $prices = $this->assetPairPriceRepository->findAllByDateRange($assetPair->getId(), $start, $end);

                if (count($prices) === 0) {
                    $this->logger->debug('No prices found.', [
                        'assetPair' => $assetPair
                    ]);
                    continue;
                }
                $oldPrice = $prices[0];
                $newPrice = $prices[count($prices) - 1];

                $evolution = $newPrice->getAskPrice() - $oldPrice->getAskPrice();

                $percent = 0;
                if ($newPrice->getAskPrice() > 0) {
                    $percent = round($evolution / $newPrice->getAskPrice() * 100, 2);
                }

                $this->logger->debug(
                    "{$asset->getName()}{$assetPair->getName()} $percent%",
                    [
                        'old' => $oldPrice->getAskPrice(),
                        'new' => $newPrice->getAskPrice(),
                    ]
                );

                $eventName = null;
                if ($percent >= $this->alertPercentPriceUp) {
                    $eventName = EventEnum::PRICE_UP;
                } else if ($percent <= self::ALERT_PERCENT_PRICE_DOWN) {
                    $eventName = EventEnum::PRICE_DOWN;
                }

                if ($eventName) {
                    $event = $this->eventPriceBuilder->build(
                        $eventName,
                        $asset,
                        $assetPair,
                        $newPrice,
                        $end,
                        $percent
                    );

                    $this->eventHandler->dispatch($event);
                }

                $this->logger->debug(
                    ($eventName) ? 'New event sent' : 'No event sent'
                );

            }
        }

        return null;
    }
}
