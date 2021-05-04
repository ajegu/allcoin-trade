<?php


namespace AllCoinTrade\ServiceProvider;


use AllCoinTrade\Lambda\BuyAnalyzerLambda;
use AllCoinCore\Exception\ServiceProviderException;
use AllCoinTrade\Notification\Handler\NotificationBuyHandler;
use Illuminate\Support\ServiceProvider;

class BuyAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * @throws ServiceProviderException
     */
    public function register(): void
    {
        $this->registerBuyAnalyzerLambda();
        $this->registerNotificationBuyHandler();
    }

    /**
     * @throws ServiceProviderException
     */
    private function registerBuyAnalyzerLambda(): void
    {
//        $env = 'BUY_ANALYZER_TIME_ANALYTICS';
//        if (!getenv($env)) {
//            throw new ServiceProviderException(
//                "You must defined the environment variable {$env}"
//            );
//        }
//
//        $this->app->when(BuyAnalyzerLambda::class)
//            ->needs('$timeAnalytics')
//            ->give(getenv($env));
    }

    /**
     * @throws ServiceProviderException
     */
    private function registerNotificationBuyHandler(): void
    {
        $env = 'AWS_SNS_TOPIC_BUY_ANALYZER_ARN';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(NotificationBuyHandler::class)
            ->needs('$topic')
            ->give(getenv($env));
    }
}
