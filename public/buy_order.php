<?php
/** @var Application $app */

use AllCoinTrade\Lambda\BuyOrderLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

return $app->make(BuyOrderLambda::class);
