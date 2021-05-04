<?php


namespace AllCoinTrade\ServiceProvider;


use AllCoinCore\Exception\ServiceProviderException;
use AllCoinTrade\Notification\Handler\NotificationSellHandler;
use Illuminate\Support\ServiceProvider;

class SellAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * @throws ServiceProviderException
     */
    public function register(): void
    {
        $this->registerNotificationSellHandler();
    }

    /**
     * @throws ServiceProviderException
     */
    private function registerNotificationSellHandler(): void
    {
        $env = 'AWS_SNS_TOPIC_SELL_ANALYZER_ARN';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(NotificationSellHandler::class)
            ->needs('$topic')
            ->give(getenv($env));
    }
}
