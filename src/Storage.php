<?php

declare(strict_types=1);

namespace Bratikov\MQ;

use Swoole\Lock;

class Storage
{
	/**
	 * @var array<string, array<string>>
	 */
	private array $data = [];
	private Lock $lock;

	public function __construct()
	{
		$this->lock = new Lock(SWOOLE_RWLOCK);
	}

	public function append(string $identity, string $value): void
	{
		$this->lock->lock();
		$this->data[$identity][] = $value;
		$this->lock->unlock();
	}

	/**
	 * @return string[]
	 */
	public function get(string $identity): array
	{
		$this->lock->lock();
		$data = $this->data[$identity] ?? [];
		$this->lock->unlock();

		return $data;
	}

	/**
	 * @todo !!MUSTHAVE!! Realize flush policies, at least by size (hello while(true))
	 * Storage can be flushed by time interval or by size, or by both.
	 * At now each channel flushes their storages by triggering general method,
	 * which flushes queues and also storages.
	 *
	 * @see \Bratikov\MQ\Channel::flush()
	 */
	public function flush(): void
	{
		$this->lock->lock();
		$this->data = [];
		$this->lock->unlock();
	}
}
