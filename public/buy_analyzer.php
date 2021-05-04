<?php
/** @var Application $app */

use AllCoinTrade\ServiceProvider\BuyAnalyzerServiceProvider;
use AllCoinTrade\Lambda\BuyAnalyzerLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

$app->register(BuyAnalyzerServiceProvider::class);

return $app->make(BuyAnalyzerLambda::class);
