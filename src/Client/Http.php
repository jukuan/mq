<?php

declare(strict_types=1);

namespace Bratikov\MQ\Client;

use Bratikov\MQ\IClient;
use Swoole\Atomic;
use Swoole\Coroutine\Http\Client as HttpClient;

use function Co\run;

class Http implements IClient
{
	public const CONCURENCY = 100;

	public function __construct(readonly string $host, readonly int $port, readonly int $concurrency = self::CONCURENCY)
	{
	}

	public function send(string $channel, array $messages): int
	{
		$sentCount = 0;
		if (PHP_SAPI === 'cli') {
			// Send asuncronously with concurency in cli mode
			$sentAtomic = new Atomic();
			run(function () use ($channel, &$messages, $sentAtomic) {
				$pageSize = 1;
				$offset = 0;
				if (count($messages) > $this->concurrency) {
					$pageSize = ceil(count($messages) / $this->concurrency);
				}
				for ($i = 0; $i < $this->concurrency; ++$i) {
					$offset = $i * $pageSize;
					go(function () use (&$messages, $offset, $pageSize, $channel, $sentAtomic) {
						$client = new HttpClient($this->host, $this->port);
						$client->set(['keep_alive' => true]);
						defer(function () use ($client) {
							$client->close();
						});
						for ($j = $offset; $j < $offset + $pageSize; ++$j) {
							if (!isset($messages[$j])) {
								break;
							}
							$client->post('/'.$channel, $messages[$j]);
							if (200 === $client->getStatusCode()) {
								$sentAtomic->add();
							}
						}
					});
				}
			});
			$sentCount = $sentAtomic->get();
		} else {
			// Send synchronously in fpm or any other mode
			foreach ($messages as $message) {
				$opts = [
					'http' => [
						'method' => 'POST',
						'header' => "Content-Type: text/plain\r\nContent-Length: ".strlen($message)."\r\n",
						'content' => $message,
					],
				];
				$ctx = stream_context_create($opts);
				$response = file_get_contents('http://'.$this->host.':'.$this->port.'/'.$channel, false, $ctx);
				if (false !== $response) {
					$statusLine = $http_response_header[0];
					if (preg_match('#^HTTP/\d+\.\d+ (\d+)#', $statusLine, $matches)) {
						$httpCode = (int) $matches[1];
						if (200 === $httpCode) {
							++$sentCount;
						}
					}
				}
			}
		}

		return $sentCount;
	}
}
