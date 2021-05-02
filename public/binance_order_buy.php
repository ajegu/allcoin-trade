<?php
/** @var Application $app */

use AllCoinTrade\Lambda\BinanceOrderBuyLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

return $app->make(BinanceOrderBuyLambda::class);
