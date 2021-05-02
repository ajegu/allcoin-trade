<?php


namespace Test\Process;


use AllCoinCore\Builder\EventPriceBuilder;
use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Exception\NotificationHandlerException;
use AllCoinCore\Model\Asset;
use AllCoinCore\Model\AssetPair;
use AllCoinCore\Model\AssetPairPrice;
use AllCoinCore\Model\EventEnum;
use AllCoinCore\Model\EventPrice;
use AllCoinCore\Notification\Handler\PriceAnalyzerNotificationHandler;
use AllCoinTrade\Process\BinancePriceAnalyzerProcess;
use AllCoinCore\Repository\AssetPairPriceRepositoryInterface;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\AssetRepositoryInterface;
use AllCoinCore\Service\DateTimeService;
use DateTime;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BinancePriceAnalyzerProcessTest extends TestCase
{
    private BinancePriceAnalyzerProcess $assetPairPriceAnalyzerProcess;

    private AssetRepositoryInterface $assetRepository;
    private AssetPairRepositoryInterface $assetPairRepository;
    private AssetPairPriceRepositoryInterface $assetPairPriceRepository;
    private LoggerInterface $logger;
    private DateTimeService $dateTimeService;
    private PriceAnalyzerNotificationHandler $eventHandler;
    private EventPriceBuilder $eventPriceBuilder;
    private int $timeAnalytics = 5;

    public function setUp(): void
    {
        $this->assetRepository = $this->createMock(AssetRepositoryInterface::class);
        $this->assetPairRepository = $this->createMock(AssetPairRepositoryInterface::class);
        $this->assetPairPriceRepository = $this->createMock(AssetPairPriceRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dateTimeService = $this->createMock(DateTimeService::class);
        $this->eventHandler = $this->createMock(PriceAnalyzerNotificationHandler::class);
        $this->eventPriceBuilder = $this->createMock(EventPriceBuilder::class);

        $this->assetPairPriceAnalyzerProcess = new BinancePriceAnalyzerProcess(
            $this->assetRepository,
            $this->assetPairRepository,
            $this->assetPairPriceRepository,
            $this->logger,
            $this->dateTimeService,
            $this->eventHandler,
            $this->eventPriceBuilder,
            $this->timeAnalytics,
            5
        );
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithPriceUpShouldBeOK(): void
    {
        $end = DateTime::createFromFormat('Y-m-d', '2020-04-17');
        $this->dateTimeService->expects($this->once())->method('now')->willReturn($end);
        $start = DateTime::createFromFormat('Y-m-d', '2020-04-16');
        $this->dateTimeService->expects($this->once())
            ->method('sub')
            ->with($end, 'PT' . $this->timeAnalytics . 'M')
            ->willReturn($start);

        $asset = $this->createMock(Asset::class);
        $assetId = 'foo';
        $asset->expects($this->once())->method('getId')->willReturn($assetId);

        $this->assetRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$asset]);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPairId = 'foo';
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);

        $this->assetPairRepository->expects($this->once())
            ->method('findAllByAssetId')
            ->with($assetId)
            ->willReturn([$assetPair]);

        $oldPrice = $this->createMock(AssetPairPrice::class);
        $oldPriceAsk = 1.2;
        $oldPrice->expects($this->any())->method('getAskPrice')->willReturn($oldPriceAsk);

        $newPrice = $this->createMock(AssetPairPrice::class);
        $newPriceAsk = 2.1;
        $newPrice->expects($this->any())->method('getAskPrice')->willReturn($newPriceAsk);

        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with($assetPairId, $start, $end)
            ->willReturn([$oldPrice, $newPrice]);

        $percent = 42.86;
        $event = $this->createMock(EventPrice::class);
        $this->eventPriceBuilder->expects($this->once())
            ->method('build')
            ->with(
                EventEnum::PRICE_UP,
                $asset,
                $assetPair,
                $newPrice,
                $end,
                $percent
            )
            ->willReturn($event);

        $this->eventHandler->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->logger->expects($this->never())->method('error');

        $this->assetPairPriceAnalyzerProcess->handle();
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithPriceDownShouldBeOK(): void
    {
        $end = DateTime::createFromFormat('Y-m-d', '2020-04-17');
        $this->dateTimeService->expects($this->once())->method('now')->willReturn($end);
        $start = DateTime::createFromFormat('Y-m-d', '2020-04-16');
        $this->dateTimeService->expects($this->once())
            ->method('sub')
            ->with($end, 'PT' . $this->timeAnalytics . 'M')
            ->willReturn($start);

        $asset = $this->createMock(Asset::class);
        $assetId = 'foo';
        $asset->expects($this->once())->method('getId')->willReturn($assetId);

        $this->assetRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$asset]);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPairId = 'foo';
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);

        $this->assetPairRepository->expects($this->once())
            ->method('findAllByAssetId')
            ->with($assetId)
            ->willReturn([$assetPair]);

        $oldPrice = $this->createMock(AssetPairPrice::class);
        $oldPriceAsk = 2.1;
        $oldPrice->expects($this->any())->method('getAskPrice')->willReturn($oldPriceAsk);

        $newPrice = $this->createMock(AssetPairPrice::class);
        $newPriceAsk = 1.2;
        $newPrice->expects($this->any())->method('getAskPrice')->willReturn($newPriceAsk);

        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with($assetPairId, $start, $end)
            ->willReturn([$oldPrice, $newPrice]);

        $percent = -75;
        $event = $this->createMock(EventPrice::class);
        $this->eventPriceBuilder->expects($this->once())
            ->method('build')
            ->with(
                EventEnum::PRICE_DOWN,
                $asset,
                $assetPair,
                $newPrice,
                $end,
                $percent
            )
            ->willReturn($event);

        $this->eventHandler->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->logger->expects($this->never())->method('error');

        $this->assetPairPriceAnalyzerProcess->handle();
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithNoPriceEvolutionShouldBeOK(): void
    {
        $end = DateTime::createFromFormat('Y-m-d', '2020-04-17');
        $this->dateTimeService->expects($this->once())->method('now')->willReturn($end);
        $start = DateTime::createFromFormat('Y-m-d', '2020-04-16');
        $this->dateTimeService->expects($this->once())
            ->method('sub')
            ->with($end, 'PT' . $this->timeAnalytics . 'M')
            ->willReturn($start);

        $asset = $this->createMock(Asset::class);
        $assetId = 'foo';
        $asset->expects($this->once())->method('getId')->willReturn($assetId);

        $this->assetRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$asset]);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPairId = 'foo';
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);

        $this->assetPairRepository->expects($this->once())
            ->method('findAllByAssetId')
            ->with($assetId)
            ->willReturn([$assetPair]);

        $oldPrice = $this->createMock(AssetPairPrice::class);
        $oldPriceAsk = 1.19;
        $oldPrice->expects($this->any())->method('getAskPrice')->willReturn($oldPriceAsk);

        $newPrice = $this->createMock(AssetPairPrice::class);
        $newPriceAsk = 1.2;
        $newPrice->expects($this->any())->method('getAskPrice')->willReturn($newPriceAsk);

        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with($assetPairId, $start, $end)
            ->willReturn([$oldPrice, $newPrice]);

        $this->eventPriceBuilder->expects($this->never())->method('build');

        $this->eventHandler->expects($this->never())->method('dispatch');

        $this->logger->expects($this->never())->method('error');

        $this->assetPairPriceAnalyzerProcess->handle();
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithNoPriceFoundShouldStop(): void
    {
        $end = DateTime::createFromFormat('Y-m-d', '2020-04-17');
        $this->dateTimeService->expects($this->once())->method('now')->willReturn($end);
        $start = DateTime::createFromFormat('Y-m-d', '2020-04-16');
        $this->dateTimeService->expects($this->once())
            ->method('sub')
            ->with($end, 'PT' . $this->timeAnalytics . 'M')
            ->willReturn($start);

        $asset = $this->createMock(Asset::class);
        $assetId = 'foo';
        $asset->expects($this->once())->method('getId')->willReturn($assetId);

        $this->assetRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$asset]);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPairId = 'foo';
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);

        $this->assetPairRepository->expects($this->once())
            ->method('findAllByAssetId')
            ->with($assetId)
            ->willReturn([$assetPair]);

        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with($assetPairId, $start, $end)
            ->willReturn([]);

        $this->eventPriceBuilder->expects($this->never())->method('build');

        $this->eventHandler->expects($this->never())->method('dispatch');

        $this->logger->expects($this->never())->method('error');

        $this->assetPairPriceAnalyzerProcess->handle();
    }
}
