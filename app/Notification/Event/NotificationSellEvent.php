<?php


namespace AllCoinTrade\Notification\Event;


use AllCoinTrade\Model\Order;
use DateTime;

class NotificationSellEvent implements NotificationEvent
{
    private Order $order;
    private float $bidPrice;

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * @return float
     */
    public function getBidPrice(): float
    {
        return $this->bidPrice;
    }

    /**
     * @param float $bidPrice
     */
    public function setBidPrice(float $bidPrice): void
    {
        $this->bidPrice = $bidPrice;
    }



}
