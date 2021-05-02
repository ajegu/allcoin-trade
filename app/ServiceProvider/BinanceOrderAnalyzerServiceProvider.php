<?php


namespace AllCoinTrade\ServiceProvider;


use AllCoinCore\Notification\Handler\OrderAnalyzerNotificationHandler;
use AllCoinTrade\Process\BinanceOrderAnalyzerProcess;
use AllCoinCore\Exception\ServiceProviderException;
use Illuminate\Support\ServiceProvider;

class BinanceOrderAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * @throws ServiceProviderException
     */
    public function register(): void
    {
        $env = 'AWS_SNS_TOPIC_ORDER_ANALYZER_ARN';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(OrderAnalyzerNotificationHandler::class)
            ->needs('$topicArn')
            ->give(getenv($env));

        $env = 'BINANCE_ORDER_ANALYZER_STOP_LOSS_PERCENT';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(BinanceOrderAnalyzerProcess::class)
            ->needs('$stopLossPercent')
            ->give(getenv($env));

        $env = 'BINANCE_ORDER_ANALYZER_BREAK_EVENT_PERCENT';
        if (!getenv($env)) {
            throw new ServiceProviderException(
                "You must defined the environment variable {$env}"
            );
        }

        $this->app->when(BinanceOrderAnalyzerProcess::class)
            ->needs('$breakEventPercent')
            ->give(getenv($env));
    }
}
