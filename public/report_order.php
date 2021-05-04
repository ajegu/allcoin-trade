<?php
/** @var Application $app */

use AllCoinTrade\Lambda\ReportOrderLambda;
use Laravel\Lumen\Application;

$app = require __DIR__ . '/../bootstrap/app.php';

return $app->make(ReportOrderLambda::class);
