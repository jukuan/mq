#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Bratikov\MQ\Client\HttpClient;
use Bratikov\MQ\Examples\Messages\LiveFastDieRandom;

$time = microtime(true);
$messages = [];
for ($i = 0; $i < 100000; ++$i) {
    $messages[] = serialize(new LiveFastDieRandom());
}

echo sprintf('%.3f seconds', microtime(true) - $time).PHP_EOL;
$time = microtime(true);
$transport = new HttpClient('127.0.0.1', 3333);
$sent = $transport->send('my_channel', $messages);
echo sprintf('%.3f seconds, processed - %d', microtime(true) - $time, $sent).PHP_EOL;