<?php

declare(strict_types=1);

namespace Bratikov\MQ;

use Swoole\Lock;

/**
 * @extends \SplQueue<string>
 */
class Queue extends \SplQueue
{
	private Lock $mutex;

	public function __construct()
	{
		$this->mutex = new Lock(SWOOLE_MUTEX);
	}

	public function enqueue(mixed $msg): void
	{
		$this->mutex->lock();
		parent::enqueue($msg);
		$this->mutex->unlock();
	}

	public function dequeue(): string
	{
		$this->mutex->lock();
		/** @var string $value */
		$value = parent::dequeue();
		$this->mutex->unlock();

		return $value;
	}
}
