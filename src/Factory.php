<?php

declare(strict_types=1);

namespace Bratikov\MQ;

use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Process;

use function Co\run;

class Factory
{
	/**
	 * Starts the factory in daemon mode.
	 */
	public const MODE_DAEMONIZE = 1;

	/**
	 * Enables swap recovery mode.
	 */
	public const MODE_SWAP_RECOVER = 2;

	/**
	 * Appends log instead of truncating it.
	 */
	public const MODE_LOG_APPEND = 4;

	/**
	 * @var Channel[]
	 */
	private array $channels = [];
	private IAgent $agent;
	private Atomic $killers;

	private const STD_LOGNAME = 'mq.log';

	public function __construct(readonly int $mode = 0)
	{
		$this->killers = new Atomic(0);
	}

	public function handle(): void
	{
		if (PHP_SAPI !== 'cli') {
			throw new \RuntimeException('This method should be run from CLI');
		}

		if (empty($this->channels)) {
			throw new \RuntimeException('No channels for listen to');
		}

		if (empty($this->agent)) {
			throw new \RuntimeException('No receiver set');
		}

		if ($this->mode & self::MODE_DAEMONIZE) {
			$this->daemonize();
		}

		run(function () {
			foreach ($this->channels as $channel) {
				$channel->listen();
			}

			$this->agent->start();
			$this->handleSignals();
		});
	}

	public function addChannel(string $name, int $size = 64): self
	{
		$channel = new Channel($name, $size, (bool) ($this->mode & self::MODE_SWAP_RECOVER));
		$this->channels[$channel->getName()] = $channel;

		return $this;
	}

	public function getChannel(string $name): ?Channel
	{
		return $this->channels[$name] ?? null;
	}

	/**
	 * @return Channel[]
	 */
	public function getChannels(): array
	{
		return $this->channels;
	}

	public function setAgent(IAgent $agent): void
	{
		$agent->setFactory($this);
		$this->agent = $agent;
	}

	/**
	 * @return string[]
	 */
	public function getTaskLog(string $identity): array
	{
		foreach ($this->channels as $channel) {
			$log = $channel->getLog($identity);
			if (count($log) > 0) {
				return $log;
			}
		}

		return [];
	}

	public function resume(): void
	{
		foreach ($this->channels as $channel) {
			$channel->resume();
		}
	}

	public function pause(): void
	{
		foreach ($this->channels as $channel) {
			$channel->pause();
		}
	}

	public function flush(): void
	{
		foreach ($this->channels as $channel) {
			$channel->flush();
		}
	}

	private function daemonize(): void
	{
		$logPath = sys_get_temp_dir().'/mq/'.self::STD_LOGNAME;
		if (!is_dir(dirname($logPath))) {
			mkdir(dirname($logPath), 0777, true);
		}
		if (is_file($logPath) && !($this->mode & self::MODE_LOG_APPEND)) {
			unlink($logPath);
		}
		$log = fopen($logPath, 'a');
		echo 'Daemon mode, PID: '.(getmypid() + 2).', standard IO goes to '.$logPath.PHP_EOL;
		Process::daemon(true, true, [null, $log, $log]);
	}

	private function handleSignals(): void
	{
		Process::signal(SIGINT, function () {
			$this->shutdown();
		});

		Process::signal(SIGTERM, function () {
			$this->shutdown();
		});
	}

	public function shutdown(): void
	{
		if ($this->killers->get() > 0) {
			Logger::i()->alert('Force shutdown received, bye!');
			Process::kill((int) getmypid(), SIGKILL);
		}

		$this->agent->shutdown();
		foreach ($this->channels as $channel) {
			$channel->shutdown();
		}
		$this->killers->add(1);

		if (count(Coroutine::list()) > 1) {
			Logger::i()->warning('Waiting for '.(count(Coroutine::list()) - 1).' unprocessed tasks in pools, do not terminate!');
			// @phpstan-ignore-next-line
			while (count(Coroutine::list()) > 1) {
				Coroutine::sleep(1);
			}
		}

		Logger::i()->info('Bye!');
	}
}
