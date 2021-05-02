<?php


namespace Test\Process;


use AllCoinCore\Builder\EventOrderBuilder;
use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Exception\NotificationHandlerException;
use AllCoinCore\Model\Asset;
use AllCoinCore\Model\AssetPair;
use AllCoinCore\Model\AssetPairPrice;
use AllCoinCore\Model\EventEnum;
use AllCoinCore\Model\EventOrder;
use AllCoinCore\Model\Order;
use AllCoinCore\Notification\Handler\OrderAnalyzerNotificationHandler;
use AllCoinTrade\Process\BinanceOrderAnalyzerProcess;
use AllCoinCore\Repository\AssetPairPriceRepositoryInterface;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\AssetRepositoryInterface;
use AllCoinCore\Service\DateTimeService;
use DateTime;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BinanceOrderAnalyzerProcessTest extends TestCase
{
    private BinanceOrderAnalyzerProcess $binanceOrderAnalyzerProcess;

    private AssetRepositoryInterface $assetRepository;
    private AssetPairRepositoryInterface $assetPairRepository;
    private AssetPairPriceRepositoryInterface $assetPairPriceRepository;
    private DateTimeService $dateTimeService;
    private OrderAnalyzerNotificationHandler $orderAnalyzerNotificationHandler;
    private EventOrderBuilder $eventOrderBuilder;

    public function setUp(): void
    {
        $this->assetRepository = $this->createMock(AssetRepositoryInterface::class);
        $this->assetPairRepository = $this->createMock(AssetPairRepositoryInterface::class);
        $this->assetPairPriceRepository = $this->createMock(AssetPairPriceRepositoryInterface::class);
        $this->dateTimeService = $this->createMock(DateTimeService::class);
        $this->orderAnalyzerNotificationHandler = $this->createMock(OrderAnalyzerNotificationHandler::class);
        $this->eventOrderBuilder = $this->createMock(EventOrderBuilder::class);

        $this->binanceOrderAnalyzerProcess = new BinanceOrderAnalyzerProcess(
            $this->assetRepository,
            $this->assetPairRepository,
            $this->assetPairPriceRepository,
            $this->createMock(LoggerInterface::class),
            $this->dateTimeService,
            $this->orderAnalyzerNotificationHandler,
            $this->eventOrderBuilder,
            3,
            2
        );
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithNoBuyOrderShouldStop(): void
    {
        $lastOrder = $this->createMock(Order::class);
        $lastOrder->expects($this->once())->method('getDirection')->willReturn(Order::SELL);
        $assetPair = $this->createMock(AssetPair::class);
        $assetPair->expects($this->once())->method('getLastOrder')->willReturn($lastOrder);

        $this->assetPairRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$assetPair]);

        $this->dateTimeService->expects($this->never())->method('now');
        $this->assetPairPriceRepository->expects($this->never())->method('findAllByDateRange');
        $this->assetRepository->expects($this->never())->method('findOneByAssetPairId');
        $this->eventOrderBuilder->expects($this->never())->method('build');
        $this->orderAnalyzerNotificationHandler->expects($this->never())->method('dispatch');

        $this->binanceOrderAnalyzerProcess->handle();
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithNoPriceHistoryShouldStop(): void
    {
        $lastOrder = $this->createMock(Order::class);
        $lastOrder->expects($this->once())->method('getDirection')->willReturn(Order::BUY);
        $createdAt = new DateTime();
        $lastOrder->expects($this->once())->method('getCreatedAt')->willReturn($createdAt);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPair->expects($this->once())->method('getLastOrder')->willReturn($lastOrder);
        $assetPairId = 'foo';
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);

        $this->assetPairRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$assetPair]);

        $now = new DateTime();
        $this->dateTimeService->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with($assetPairId, $createdAt, $now)
            ->willReturn([]);


        $this->assetRepository->expects($this->never())->method('findOneByAssetPairId');
        $this->eventOrderBuilder->expects($this->never())->method('build');
        $this->orderAnalyzerNotificationHandler->expects($this->never())->method('dispatch');

        $this->binanceOrderAnalyzerProcess->handle();
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithStopLossShouldSendEvent(): void
    {
        $lastOrder = $this->createMock(Order::class);
        $lastOrder->expects($this->once())->method('getDirection')->willReturn(Order::BUY);
        $createdAt = new DateTime();
        $lastOrder->expects($this->once())->method('getCreatedAt')->willReturn($createdAt);
        $amount = 10.;
        $lastOrder->expects($this->once())->method('getAmount')->willReturn($amount);
        $quantity = 5.;
        $lastOrder->expects($this->once())->method('getQuantity')->willReturn($quantity);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPair->expects($this->once())->method('getLastOrder')->willReturn($lastOrder);
        $assetPairId = 'foo';
        $assetPair->expects($this->exactly(2))->method('getId')->willReturn($assetPairId);

        $this->assetPairRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$assetPair]);

        $now = new DateTime();
        $this->dateTimeService->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $lastPrice = $this->createMock(AssetPairPrice::class);
        $bidPrice = 1.;
        $lastPrice->expects($this->once())->method('getBidPrice')->willReturn($bidPrice);

        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with($assetPairId, $createdAt, $now)
            ->willReturn([$lastPrice]);

        $asset = $this->createMock(Asset::class);
        $this->assetRepository->expects($this->once())
            ->method('findOneByAssetPairId')
            ->with($assetPairId)
            ->willReturn($asset);

        $event = $this->createMock(EventOrder::class);
        $this->eventOrderBuilder->expects($this->once())
            ->method('build')
            ->with(
                EventEnum::STOP_LOSS,
                $asset,
                $assetPair,
                $lastPrice
            )
            ->willReturn($event);


        $this->orderAnalyzerNotificationHandler->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->binanceOrderAnalyzerProcess->handle();
    }

    /**
     * @throws ItemReadException
     * @throws NotificationHandlerException
     */
    public function testHandleWithBreakEventShouldSendEvent(): void
    {
        $lastOrder = $this->createMock(Order::class);
        $lastOrder->expects($this->once())->method('getDirection')->willReturn(Order::BUY);
        $createdAt = new DateTime();
        $lastOrder->expects($this->once())->method('getCreatedAt')->willReturn($createdAt);
        $amount = 10.;
        $lastOrder->expects($this->once())->method('getAmount')->willReturn($amount);
        $quantity = 5.;
        $lastOrder->expects($this->once())->method('getQuantity')->willReturn($quantity);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPair->expects($this->once())->method('getLastOrder')->willReturn($lastOrder);
        $assetPairId = 'foo';
        $assetPair->expects($this->exactly(2))->method('getId')->willReturn($assetPairId);

        $this->assetPairRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$assetPair]);

        $now = new DateTime();
        $this->dateTimeService->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $topPrice = $this->createMock(AssetPairPrice::class);
        $bidPrice = 3.;
        $topPrice->expects($this->exactly(3))->method('getBidPrice')->willReturn($bidPrice);

        $lastPrice = $this->createMock(AssetPairPrice::class);
        $bidPrice = 2.1;
        $lastPrice->expects($this->exactly(3))->method('getBidPrice')->willReturn($bidPrice);

        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with($assetPairId, $createdAt, $now)
            ->willReturn([$topPrice, $lastPrice]);

        $asset = $this->createMock(Asset::class);
        $this->assetRepository->expects($this->once())
            ->method('findOneByAssetPairId')
            ->with($assetPairId)
            ->willReturn($asset);

        $event = $this->createMock(EventOrder::class);
        $this->eventOrderBuilder->expects($this->once())
            ->method('build')
            ->with(
                EventEnum::BREAK_EVENT,
                $asset,
                $assetPair,
                $lastPrice
            )
            ->willReturn($event);


        $this->orderAnalyzerNotificationHandler->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->binanceOrderAnalyzerProcess->handle();
    }
}
