<?php


namespace AllCoinTrade\ServiceProvider;


use AllCoinTrade\Repository\OrderRepository;
use AllCoinTrade\Repository\OrderRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
    }
}
