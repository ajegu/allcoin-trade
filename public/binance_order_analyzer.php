<?php
/** @var Application $app */

use AllCoinTrade\Lambda\BinanceOrderAnalyzerLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

$app->register(AllCoinTrade\ServiceProvider\BinanceOrderAnalyzerServiceProvider::class);

return $app->make(BinanceOrderAnalyzerLambda::class);
