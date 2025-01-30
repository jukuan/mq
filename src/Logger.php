<?php

declare(strict_types=1);

namespace Bratikov\MQ;

use Bratikov\MQ\Logger\Generic;
use Bratikov\MQ\Logger\Stdout;

class Logger
{
	private static Generic $i;

	protected function __construct()
	{
	}

	protected function __clone()
	{
	}

	public function __wakeup()
	{
		throw new \Exception('Cannot unserialize a singleton.');
	}

	private static function getDefault(): Generic
	{
		return new Stdout();
	}

	public static function setLogger(Generic $logger): void
	{
		self::$i = $logger;
	}

	public static function i(): Generic
	{
		if (!isset(self::$i)) {
			self::$i = self::getDefault();
		}

		return self::$i;
	}
}
