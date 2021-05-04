<?php


namespace AllCoinTrade\Repository;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinTrade\Model\Order;

interface OrderRepositoryInterface
{
    /**
     * @param Order $order
     * @throws ItemSaveException
     */
    public function save(Order $order): void;

    /**
     * @param string $pair
     * @return Order[]
     * @throws ItemReadException
     */
    public function findAllByPair(string $pair): array;

    /**
     * @return Order[]
     * @throws ItemReadException
     */
    public function findAll(): array;
}
