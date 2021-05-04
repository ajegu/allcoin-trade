<?php


namespace AllCoinTrade\Repository;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\Database\DynamoDb\ItemManager;
use AllCoinCore\Repository\Repository;
use AllCoinTrade\Model\Order;

class OrderRepository extends Repository implements OrderRepositoryInterface
{
    const PARTITION_KEY = 'order';

    /**
     * @param Order $order
     * @throws ItemSaveException
     */
    public function save(Order $order): void
    {
        $data = $this->serializer->normalize($order);

        $data[ItemManager::LSI_1] = $order->getPair();

        $this->itemManager->save(
            $data,
            self::PARTITION_KEY,
            $order->getPair() . '_' . $order->getCreatedAt()->getTimestamp()
        );
    }

    /**
     * @param string $pair
     * @return Order[]
     * @throws ItemReadException
     */
    public function findAllByPair(string $pair): array
    {
        $items = $this->itemManager->fetchAllOnLSI(
            self::PARTITION_KEY,
            ItemManager::LSI_1,
            $pair
        );

        return array_map(function (array $item) {
            return $this->serializer->deserialize($item, Order::class);
        }, $items);
    }

    /**
     * @return Order[]
     * @throws ItemReadException
     */
    public function findAll(): array
    {
        $items = $this->itemManager->fetchAll(
            self::PARTITION_KEY
        );

        return array_map(function (array $item) {
            return $this->serializer->deserialize($item, Order::class);
        }, $items);
    }

}
