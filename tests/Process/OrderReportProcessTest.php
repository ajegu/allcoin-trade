<?php


namespace Test\Process;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Model\Asset;
use AllCoinCore\Model\AssetPair;
use AllCoinCore\Model\AssetPairPrice;
use AllCoinCore\Model\Order;
use AllCoinTrade\Process\OrderReportProcess;
use AllCoinCore\Repository\AssetPairPriceRepositoryInterface;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\AssetRepositoryInterface;
use AllCoinCore\Repository\OrderRepositoryInterface;
use AllCoinCore\Service\DateTimeService;
use DateInterval;
use DateTime;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class OrderReportProcessTest extends TestCase
{
    private OrderReportProcess $orderReportProcess;

    private AssetRepositoryInterface $assetRepository;
    private AssetPairRepositoryInterface $assetPairRepository;
    private OrderRepositoryInterface $orderRepository;
    private AssetPairPriceRepositoryInterface $assetPairPriceRepository;
    private DateTimeService $dateTimeService;
    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->assetRepository = $this->createMock(AssetRepositoryInterface::class);
        $this->assetPairRepository = $this->createMock(AssetPairRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->assetPairPriceRepository = $this->createMock(AssetPairPriceRepositoryInterface::class);
        $this->dateTimeService = $this->createMock(DateTimeService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->orderReportProcess = new OrderReportProcess(
            $this->assetRepository,
            $this->assetPairRepository,
            $this->orderRepository,
            $this->assetPairPriceRepository,
            $this->dateTimeService,
            $this->logger,
        );
    }

    /**
     * @throws ItemReadException
     */
    public function testHandleShouldBeOK(): void
    {
        $orderBTCBuy = $this->createMock(Order::class);
        $orderBTCBuyDate = new DateTime('2021-05-01');
        $orderBTCBuy->expects($this->once())->method('getCreatedAt')->willReturn($orderBTCBuyDate);
        $orderBTCBuy->expects($this->once())->method('getDirection')->willReturn(Order::BUY);
        $orderBTCBuyAmount = 5.;
        $orderBTCBuy->expects($this->exactly(2))->method('getAmount')->willReturn($orderBTCBuyAmount);

        $orderBTCSell = $this->createMock(Order::class);
        $orderBTCSellDate = new DateTime('2021-06-01');
        $orderBTCSell->expects($this->once())->method('getCreatedAt')->willReturn($orderBTCSellDate);
        $orderBTCSell->expects($this->exactly(2))->method('getDirection')->willReturn(Order::SELL);
        $orderBTCSellAmount = 10.;
        $orderBTCSell->expects($this->exactly(2))->method('getAmount')->willReturn($orderBTCSellAmount);

        $orderETHBuy = $this->createMock(Order::class);
        $orderETHBuyDate = new DateTime('2021-06-01');
        $orderETHBuy->expects($this->once())->method('getCreatedAt')->willReturn($orderETHBuyDate);
        $orderETHBuy->expects($this->exactly(2))->method('getDirection')->willReturn(Order::BUY);
        $orderETHBuyAmount = 7.;
        $orderETHBuy->expects($this->exactly(3))->method('getAmount')->willReturn($orderETHBuyAmount);
        $orderETHBuyQuantity = 5.;
        $orderETHBuy->expects($this->exactly(2))->method('getQuantity')->willReturn($orderETHBuyQuantity);

        $assetPairBTCId = 'foo';
        $assetPairETHId = 'bar';
        $ordersGrouped = [
            $assetPairBTCId => [$orderBTCSell, $orderBTCBuy],
            $assetPairETHId => [$orderETHBuy]
        ];

        $this->orderRepository->expects($this->once())
            ->method('findAllGroupByAssetPairId')
            ->willReturn($ordersGrouped);

        $assetPairBTC = $this->createMock(AssetPair::class);
        $assetPairBTCName = 'USDT';
        $assetPairBTC->expects($this->once())->method('getName')->willReturn($assetPairBTCName);
        $assetPairETH = $this->createMock(AssetPair::class);
        $assetPairETHName = 'USDT';
        $assetPairETH->expects($this->once())->method('getName')->willReturn($assetPairETHName);

        $this->assetPairRepository->expects($this->exactly(2))
            ->method('findOneById')
            ->withConsecutive([$assetPairBTCId], [$assetPairETHId])
            ->willReturn($assetPairBTC, $assetPairETH);

        $assetBTC = $this->createMock(Asset::class);
        $assetBTCName = 'BTC';
        $assetBTC->expects($this->once())->method('getName')->willReturn($assetBTCName);
        $assetETH = $this->createMock(Asset::class);
        $assetETHName = 'ETH';
        $assetETH->expects($this->once())->method('getName')->willReturn($assetETHName);

        $this->assetRepository->expects($this->exactly(2))
            ->method('findOneByAssetPairId')
            ->withConsecutive([$assetPairBTCId], [$assetPairETHId])
            ->willReturn($assetBTC, $assetETH);

        $now = new DateTime();
        $this->dateTimeService->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $lastPrice = $this->createMock(AssetPairPrice::class);
        $lastPriceETHBidPrice = 1.;
        $lastPrice->expects($this->once())->method('getBidPrice')->willReturn($lastPriceETHBidPrice);

        $prices = [$lastPrice];
        $this->assetPairPriceRepository->expects($this->once())
            ->method('findAllByDateRange')
            ->with(
                $assetPairETHId,
                $orderETHBuyDate->sub(new DateInterval('PT1M')),
                $now
            )
            ->willReturn($prices);

        $symbolBTC = $assetBTCName . $assetPairBTCName;
        $symbolETH = $assetETHName . $assetPairETHName;

        $orderETHInProgress = $lastPriceETHBidPrice * $orderETHBuyQuantity;

        $this->logger->expects($this->exactly(3))
            ->method('info')
            ->withConsecutive(
                [
                    'Asset report ' . $symbolBTC,
                    [
                        'balance' => $orderBTCSellAmount - $orderBTCBuyAmount,
                        'assetPairId' => $assetPairBTCId,
                        'buyPrice' => 0
                    ]
                ], [
                'Asset report ' . $symbolETH,
                [
                    'balance' => $orderETHInProgress - $orderETHBuyAmount,
                    'assetPairId' => $assetPairETHId,
                    'buyPrice' => round($orderETHBuyAmount / $orderETHBuyQuantity, 5)
                ]
            ], [
                    'Global report', [
                        'balance' => $orderBTCSellAmount + $orderETHInProgress - ($orderBTCBuyAmount + $orderETHBuyAmount)
                    ]
                ]
            );

        $this->orderReportProcess->handle();
    }
}
