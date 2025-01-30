<?php

declare(strict_types=1);

namespace Bratikov\MQ;

interface IClient
{
	/**
	 * @param string[] $messages
	 */
	public function send(string $channel, array $messages): int;
}
