<?php


namespace AllCoinTrade\Notification\Event;


class NotificationBuyEvent implements NotificationEvent
{
    private string $pair;
    private float $stopLoss;
    private float $askPrice;
    private float $bidPrice;

    /**
     * @return string
     */
    public function getPair(): string
    {
        return $this->pair;
    }

    /**
     * @param string $pair
     */
    public function setPair(string $pair): void
    {
        $this->pair = $pair;
    }

    /**
     * @return float
     */
    public function getStopLoss(): float
    {
        return $this->stopLoss;
    }

    /**
     * @param float $stopLoss
     */
    public function setStopLoss(float $stopLoss): void
    {
        $this->stopLoss = $stopLoss;
    }

    /**
     * @return float
     */
    public function getAskPrice(): float
    {
        return $this->askPrice;
    }

    /**
     * @param float $askPrice
     */
    public function setAskPrice(float $askPrice): void
    {
        $this->askPrice = $askPrice;
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
