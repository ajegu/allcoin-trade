<?php


namespace AllCoinTrade\ServiceProvider;


use AllCoinCore\Notification\Handler\PriceAnalyzerNotificationHandler;
use AllCoinTrade\Process\BinancePriceAnalyzerProcess;
use AllCoinCore\Exception\ServiceProviderException;
use Illuminate\Support\ServiceProvider;

class BinancePriceAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * @throws ServiceProviderException
     */
    public function register(): void
    {
        $env = 'AWS_SNS_TOPIC_PRICE_ANALYZER_ARN';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(PriceAnalyzerNotificationHandler::class)
            ->needs('$topicArn')
            ->give(getenv($env));

        $env = 'BINANCE_PRICE_ANALYZER_TIME_ANALYTICS';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(BinancePriceAnalyzerProcess::class)
            ->needs('$timeAnalytics')
            ->give(getenv($env));

        $env = 'BINANCE_PRICE_ANALYZER_ALERT_PERCENT_PRICE_UP';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(BinancePriceAnalyzerProcess::class)
            ->needs('$alertPercentPriceUp')
            ->give(getenv($env));
    }
}
