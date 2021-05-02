<?php


namespace Test\Process;


use AllCoinCore\Builder\OrderBuilder;
use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\Model\Asset;
use AllCoinCore\Model\AssetPair;
use AllCoinCore\Model\EventPrice;
use AllCoinCore\Model\Order;
use AllCoinTrade\Process\BinanceOrderBuyProcess;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BinanceOrderBuyProcessTest extends TestCase
{
    private BinanceOrderBuyProcess $binanceBuyOrderProcess;

    private OrderBuilder $orderBuilder;
    private OrderRepositoryInterface $orderRepository;
    private AssetPairRepositoryInterface $assetPairRepository;
    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->orderBuilder = $this->createMock(OrderBuilder::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->assetPairRepository = $this->createMock(AssetPairRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->binanceBuyOrderProcess = new BinanceOrderBuyProcess(
            $this->orderBuilder,
            $this->orderRepository,
            $this->assetPairRepository,
            $this->logger,
        );
    }

    /**
     * @throws ItemReadException
     * @throws ItemSaveException
     */
    public function testHandleWithExistingOrderShouldStop(): void
    {
        $order = $this->createMock(Order::class);
        $order->expects($this->once())->method('getDirection')->willReturn(Order::BUY);

        $assetPairId = 'foo';
        $assetPair = $this->createMock(AssetPair::class);
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);
        $assetPair->expects($this->any())->method('getLastOrder')->willReturn($order);

        $dto = $this->createMock(EventPrice::class);
        $dto->expects($this->once())->method('getAssetPair')->willReturn($assetPair);

        $this->assetPairRepository->expects($this->once())
            ->method('findOneById')
            ->with($assetPairId)
            ->willReturn($assetPair);

        $this->logger->expects($this->never())->method('error');
        $this->orderBuilder->expects($this->never())->method('build');
        $this->orderRepository->expects($this->never())->method('save');
        $this->assetPairRepository->expects($this->never())->method('save');

        $this->binanceBuyOrderProcess->handle($dto);
    }

    /**
     * @throws ItemReadException
     * @throws ItemSaveException
     */
    public function testHandleShouldBeOK(): void
    {
        $order = $this->createMock(Order::class);
        $order->expects($this->once())->method('getDirection')->willReturn(Order::SELL);

        $assetPairId = 'foo';
        $assetPair = $this->createMock(AssetPair::class);
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);
        $assetPair->expects($this->once())->method('getLastOrder')->willReturn($order);

        $dto = $this->createMock(EventPrice::class);
        $dto->expects($this->once())->method('getAssetPair')->willReturn($assetPair);
        $price = 10.;
        $dto->expects($this->once())->method('getPrice')->willReturn($price);
        $asset = $this->createMock(Asset::class);
        $assetId = 'foo';
        $asset->expects($this->once())->method('getId')->willReturn($assetId);
        $dto->expects($this->once())->method('getAsset')->willReturn($asset);
        $name = 'foo';
        $dto->expects($this->once())->method('getName')->willReturn($name);

        $this->assetPairRepository->expects($this->once())
            ->method('findOneById')
            ->with($assetPairId)
            ->willReturn($assetPair);

        $order = $this->createMock(Order::class);
        $quantity = BinanceOrderBuyProcess::FIXED_TRANSACTION_AMOUNT / $price;
        $this->orderBuilder->expects($this->once())
            ->method('build')
            ->with(
                $quantity,
                BinanceOrderBuyProcess::FIXED_TRANSACTION_AMOUNT,
                Order::BUY,
                $name
            )
            ->willReturn($order);

        $this->orderRepository->expects($this->once())
            ->method('save')
            ->with($order, $assetPairId);

        $assetPair->expects($this->once())
            ->method('setLastOrder')
            ->with($order);

        $this->assetPairRepository->expects($this->once())
            ->method('save')
            ->with($assetPair);

        $this->logger->expects($this->never())->method('error');

        $this->binanceBuyOrderProcess->handle($dto);
    }
}
