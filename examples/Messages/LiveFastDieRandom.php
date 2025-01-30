<?php

declare(strict_types=1);

namespace Bratikov\MQ\Examples\Messages;

use Bratikov\MQ\Runnable;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class LiveFastDieRandom implements Runnable
{
	private string $uuid;

	public function __construct()
	{
		$this->uuid = Uuid::uuid4()->toString();
	}

	public function getUniqueIdentity(): string
	{
		return $this->uuid;
	}

	public function run(?LoggerInterface $logger = null): void
	{
		$rand = rand(1, 20);
		for ($i = 0; $i < $rand; ++$i) {
			$logger?->debug('Birthday '.$i);
			if (3 == $i) {
				$logger?->info('Im alive!');
			}
			if (7 == $i) {
				$logger?->warning('I will die soon!');
			}
			if (10 == $i) {
				$logger?->alert('I will die now, really!');
				// @phpstan-ignore-next-line
				omg();
			}
			sleep(1);
		}
	}
}