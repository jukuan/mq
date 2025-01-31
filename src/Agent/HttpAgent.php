<?php

declare(strict_types=1);

namespace Bratikov\MQ\Agent;

use Bratikov\MQ\Channel;
use Bratikov\MQ\Factory;
use Bratikov\MQ\IAgent;
use Bratikov\MQ\Logger;
use Swoole\Coroutine\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

use function Co\go;

class HttpAgent implements IAgent
{
	private Factory $factory;
	private Server $server;

	public function __construct(readonly string $host, readonly int $port)
	{
	}

	public function setFactory(Factory $factory): void
	{
		$this->factory = $factory;
	}

	public function start(): void
	{
		$this->server = new Server($this->host, $this->port);
		$this->handle();
		go(function () {
			Logger::i()->info("Server ready to handle requests on http://{$this->host}:{$this->port}");
			$this->server->start();
		});
	}

	private function handle(): void
	{
		$this->server->handle('/', function (Request $request, Response $response) {
			switch ($request->getMethod()) {
				case 'POST':
					$this->post($request, $response);
					break;
				case 'GET':
					$this->get($request, $response);
					break;
				case 'PATCH':
					$this->patch($request, $response);
					break;
				default:
					$response->status(501);
					$response->end();
					break;
			}
		});
	}

	public function shutdown(): void
	{
		$this->server->shutdown();
		Logger::i()->info('Agent stopped');
	}

	private function get(Request $request, Response $response): void
	{
		$identity = substr($request->server['request_uri'], 1);
		if ('' === $identity) {
			$this->getStats($response);

			return;
		}

		$this->getLog($identity, $response);
	}

	private function getStats(Response $response): void
	{
		$stats = [];
		$channels = $this->factory->getChannels();
		foreach ($channels as $channel) {
			$stats[] = [
				'name' => $channel->getName(),
				'queue' => $channel->getQueueCount(),
				'pool' => $channel->getPoolCount(),
				'success' => $channel->getSuccessCount(),
				'failed' => $channel->getFailedCount(),
				'total' => $channel->getSuccessCount() + $channel->getFailedCount(),
				'status' => $channel->getStatus(),
			];
		}
		$response->header('Content-Type', 'application/json');
		// @phpstan-ignore-next-line
		$response->end(json_encode($stats, JSON_PRETTY_PRINT));
	}

	private function getLog(string $identity, Response $response): void
	{
		$log = $this->factory->getTaskLog($identity);
		$response->header('Content-Type', 'application/json');
		// @phpstan-ignore-next-line
		$response->end(json_encode($log, JSON_PRETTY_PRINT));
	}

	private function post(Request $request, Response $response): void
	{
		$channelName = substr($request->server['request_uri'], 1);
		if (!Channel::isValidChannelName($channelName)) {
			$response->status(400);
			$response->end();

			return;
		}

		$channel = $this->factory->getChannel($channelName);
		if (null === $channel) {
			$response->status(404);
			$response->end();

			return;
		}

		$message = $request->rawContent();
		if (empty($message)) {
			$response->status(404);
			$response->end();

			return;
		}

		$channel->append($message);
		$response->header('Connection', 'keep-alive');
		$response->end();
	}

	private function patch(Request $request, Response $response): void
	{
		if (preg_match('/\/(resume|pause|flush|shutdown)\/([a-zA-Z_]+)?/', $request->server['request_uri'], $matches)) {
			$action = $matches[1];
			$channel = $matches[2] ?? null;
			if (null === $channel) {
				$this->factory->{$action}();
				$response->end('ack');

				return;
			}

			$channel = $this->factory->getChannel($channel);
			if (null === $channel) {
				$response->status(404);
				$response->end();

				return;
			}

			$channel->{$action}();
			$response->end('ack');

			return;
		}

		$response->status(404);
		$response->end();
	}
}
