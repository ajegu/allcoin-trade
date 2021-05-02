<?php


namespace Test\Process;


use AllCoinCore\Builder\OrderBuilder;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\Model\Asset;
use AllCoinCore\Model\AssetPair;
use AllCoinCore\Model\EventOrder;
use AllCoinCore\Model\Order;
use AllCoinTrade\Process\BinanceOrderSellProcess;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BinanceOrderSellProcessTest extends TestCase
{
    private BinanceOrderSellProcess $binanceOrderSellProcess;

    private AssetPairRepositoryInterface $assetPairRepository;
    private OrderRepositoryInterface $orderRepository;
    private OrderBuilder $orderBuilder;

    public function setUp(): void
    {
        $this->assetPairRepository = $this->createMock(AssetPairRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->orderBuilder = $this->createMock(OrderBuilder::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->binanceOrderSellProcess = new BinanceOrderSellProcess(
            $this->assetPairRepository,
            $this->orderRepository,
            $this->orderBuilder,
            $logger,
        );
    }

    /**
     * @throws ItemSaveException
     */
    public function testHandleShouldBeOK(): void
    {
        $asset = $this->createMock(Asset::class);
        $assetId = 'foo';
        $asset->expects($this->once())->method('getId')->willReturn($assetId);

        $lastOrder = $this->createMock(Order::class);
        $quantity = 2.;
        $lastOrder->expects($this->once())->method('getQuantity')->willReturn($quantity);

        $assetPair = $this->createMock(AssetPair::class);
        $assetPairId = 'bar';
        $assetPair->expects($this->once())->method('getId')->willReturn($assetPairId);
        $assetPair->expects($this->once())->method('getLastOrder')->willReturn($lastOrder);

        $dto = $this->createMock(EventOrder::class);
        $dto->expects($this->once())->method('getAsset')->willReturn($asset);
        $dto->expects($this->once())->method('getAssetPair')->willReturn($assetPair);
        $price = 5.;
        $dto->expects($this->once())->method('getPrice')->willReturn($price);
        $name = 'baz';
        $dto->expects($this->once())->method('getName')->willReturn($name);

        $amount = $quantity * $price;

        $order = $this->createMock(Order::class);
        $this->orderBuilder->expects($this->once())
            ->method('build')
            ->with(
                $quantity,
                $amount,
                Order::SELL,
                $name
            )
            ->willReturn($order);

        $this->orderRepository->expects($this->once())
            ->method('save')
            ->with($order);

        $assetPair->expects($this->once())
            ->method('setLastOrder')
            ->with($order);

        $this->assetPairRepository->expects($this->once())
            ->method('save')
            ->with($assetPair);

        $this->binanceOrderSellProcess->handle($dto);
    }
}
