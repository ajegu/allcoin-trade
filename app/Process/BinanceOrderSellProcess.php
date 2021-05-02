<?php


namespace AllCoinTrade\Process;


use AllCoinCore\Builder\OrderBuilder;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\Dto\RequestDtoInterface;
use AllCoinCore\Dto\ResponseDtoInterface;
use AllCoinCore\Model\EventOrder;
use AllCoinCore\Model\Order;
use AllCoinCore\Process\ProcessInterface;
use AllCoinCore\Repository\AssetPairRepositoryInterface;
use AllCoinCore\Repository\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class BinanceOrderSellProcess implements ProcessInterface
{
    public function __construct(
        private AssetPairRepositoryInterface $assetPairRepository,
        private OrderRepositoryInterface $orderRepository,
        private OrderBuilder $orderBuilder,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * @param RequestDtoInterface|EventOrder|null $dto
     * @param array $params
     * @return ResponseDtoInterface|null
     * @throws ItemSaveException
     */
    public function handle(RequestDtoInterface|EventOrder $dto = null, array $params = []): ?ResponseDtoInterface
    {
        $assetPair = $dto->getAssetPair();

        $quantity = $assetPair->getLastOrder()->getQuantity();
        $unitPrice = $dto->getPrice();
        $amount = round($quantity * $unitPrice, 5);

        $order = $this->orderBuilder->build(
            $quantity,
            $amount,
            Order::SELL,
            $dto->getName()
        );

        $this->orderRepository->save($order, $assetPair->getId());

        $assetPair->setLastOrder($order);
        $this->assetPairRepository->save($assetPair, $dto->getAsset()->getId());

        $this->logger->debug('Order sell created!');

        return null;
    }

}
