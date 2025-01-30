<?php

declare(strict_types=1);

namespace Bratikov\MQ;

interface IAgent
{
	public function setFactory(Factory $factory): void;

	public function start(): void;

	public function shutdown(): void;
}
