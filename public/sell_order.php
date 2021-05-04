<?php
/** @var Application $app */

use AllCoinTrade\Lambda\SellOrderLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

return $app->make(SellOrderLambda::class);
