<?php
/** @var Application $app */

use AllCoinTrade\Lambda\BinanceOrderSellLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

return $app->make(BinanceOrderSellLambda::class);
