<?php
/** @var Application $app */

use AllCoinTrade\Lambda\BinancePriceAnalyzerLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

$app->register(AllCoinTrade\ServiceProvider\BinancePriceAnalyzerServiceProvider::class);

return $app->make(BinancePriceAnalyzerLambda::class);
