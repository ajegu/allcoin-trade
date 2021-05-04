<?php
/** @var Application $app */

use AllCoinTrade\Lambda\SellAnalyzerLambda;
use AllCoinTrade\ServiceProvider\SellAnalyzerServiceProvider;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

$app->register(SellAnalyzerServiceProvider::class);

return $app->make(SellAnalyzerLambda::class);
