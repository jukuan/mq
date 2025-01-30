<?php

declare(strict_types=1);

namespace Bratikov\MQ;

use Psr\Log\LoggerInterface;

interface Runnable
{
	/**
	 * Each runnable class should have a unique identifier.
	 */
	public function getUniqueIdentity(): string;

	/**
	 * Each class should implement this method to run the task.
	 */
	public function run(?LoggerInterface $logger = null): void;
}
