<?php

declare(strict_types=1);

namespace Bratikov\MQ;

use Swoole\Atomic;
use Swoole\Coroutine\Channel as CoChannel;
use Swoole\Timer;

use function Co\go;

class Channel
{
	// @phpstan-ignore-next-line
	private CoChannel $pool;
	private Queue $queue;
	private Storage $storage;

	private Atomic $success;
	private Atomic $failed;
	private Atomic $running;

	private int $id;

	/**
	 * @throws \InvalidArgumentException
	 */
	public function __construct(readonly string $name, readonly int $size = 64, readonly bool $swapRecover = false)
	{
		if (!self::isValidChannelName($name)) {
			throw new \InvalidArgumentException('Invalid channel name, only letters and underscores are allowed');
		}

		$this->storage = new Storage();
		$this->queue = new Queue();
		$this->pool = new CoChannel($this->size);
		$this->success = new Atomic();
		$this->failed = new Atomic();
		$this->running = new Atomic(1);
		$this->restore();
	}

	public static function isValidChannelName(string $channelName): bool
	{
		return 1 === preg_match('/^[a-zA-Z_]+$/', $channelName);
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function append(string $msg): void
	{
		$this->queue->enqueue($msg);
	}

	public function getQueueCount(): int
	{
		return $this->queue->count();
	}

	public function getPoolCount(): int
	{
		return $this->pool->length();
	}

	public function getSuccessCount(): int
	{
		return $this->success->get();
	}

	public function getFailedCount(): int
	{
		return $this->failed->get();
	}

	public function resume(): void
	{
		$this->running->set(1);
	}

	public function pause(): void
	{
		$this->running->set(0);
	}

	public function flush(): void
	{
		$this->queue = new Queue();
		$this->storage = new Storage();
	}

	public function getStatus(): string
	{
		return $this->running->get() ? 'active' : 'paused';
	}

	/**
	 * @return string[]
	 */
	public function getLog(string $identity): array
	{
		return $this->storage->get($identity);
	}

	public function swap(): void
	{
		if ($this->swapRecover && $this->getQueueCount() > 0) {
			Logger::i()->notice('Channel '.$this->getName().' has '.$this->getQueueCount().' not completed tasks in queue, swapping');
			$fd = fopen($this->getSwapPath(), 'a');
			if (false === $fd) {
				Logger::i()->error('Channel '.$this->getName().' failed to swap queue');

				return;
			}
			while ($this->queue->count() > 0) {
				fwrite($fd, $this->queue->dequeue().PHP_EOL);
			}
			fclose($fd);
		}
	}

	public function restore(): void
	{
		if ($this->swapRecover && is_file($this->getSwapPath())) {
			Logger::i()->notice('Channel '.$this->getName().' has not completed tasks in queue, restoring');
			$rCount = 0;
			$fd = fopen($this->getSwapPath(), 'r');
			if (false === $fd) {
				Logger::i()->error('Channel '.$this->getName().' failed to restore queue');

				return;
			}
			while (($buffer = fgets($fd)) !== false) {
				$this->queue->enqueue($buffer);
				++$rCount;
			}
			fclose($fd);
			Logger::i()->notice('Channel '.$this->getName().' restored '.$rCount.' tasks');
			unlink($this->getSwapPath());
		}
	}

	private function getSwapPath(): string
	{
		return sys_get_temp_dir().'/cf_'.$this->getName().'.swp';
	}

	public function listen(): void
	{
		$timer = Timer::tick(100, function () {
			while ($this->queue->count() > 0 && !$this->pool->isFull() && $this->running->get()) {
				$msg = $this->queue->dequeue();
				$job = unserialize($msg);
				if ($job instanceof Runnable) {
					$log = clone Logger::i();
					go(function () use ($job, $log) {
						$this->pool->push($job->getUniqueIdentity());
						$log->setIdentity($job->getUniqueIdentity());
						$log->setChannelStorage($this->storage);
						try {
							$job->run($log);
							$this->success->add();
						} catch (\Throwable $e) {
							$log->error($e->getFile().':'.$e->getLine().' '.$e->getMessage());
							$this->failed->add();
						}
						$this->pool->pop();
					});
				}
			}
		});
		if (false === $timer) {
			Logger::i()->error('Channel '.$this->name.' failed to start');

			return;
		}
		$this->id = $timer;
		Logger::i()->info('Channel '.$this->name.' initialzed, fixed pool size is '.$this->pool->capacity);
	}

	public function shutdown(): void
	{
		if (Timer::exists($this->id)) {
			Timer::clear($this->id);
		}

		Logger::i()->info('Channel '.$this->name.' closed, processed '.$this->getSuccessCount() + $this->getFailedCount().' tasks');
		$this->swap();
	}
}
