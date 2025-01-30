<?php

declare(strict_types=1);

namespace Bratikov\MQ\Logger;

use Bratikov\MQ\Storage;

abstract class Generic implements \Psr\Log\LoggerInterface
{
	protected const EMERGENCY = 'emr';
	protected const ALERT = 'alt';
	protected const CRITICAL = 'crt';
	protected const ERROR = 'err';
	protected const WARNING = 'wrn';
	protected const NOTICE = 'ntc';
	protected const INFO = 'inf';
	protected const DEBUG = 'dbg';

	protected ?string $identity = null;
	protected ?Storage $channelStorage = null;

	public function emergency(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::EMERGENCY, $message, $context);
	}

	public function alert(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::ALERT, $message, $context);
	}

	public function critical(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::CRITICAL, $message, $context);
	}

	public function error(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::ERROR, $message, $context);
	}

	public function warning(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::WARNING, $message, $context);
	}

	public function notice(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::NOTICE, $message, $context);
	}

	public function info(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::INFO, $message, $context);
	}

	public function debug(string|\Stringable $message, array $context = []): void
	{
		$this->log(self::DEBUG, $message, $context);
	}

	public function setIdentity(string $identity): void
	{
		$this->identity = $identity;
	}

	public function setChannelStorage(Storage $storage): void
	{
		$this->channelStorage = $storage;
	}

	protected function appendToChannelStorage(string $msg): void
	{
		if (null === $this->identity) {
			return;
		}

		$this->channelStorage?->append($this->identity, $msg);
	}

	abstract public function log($level, string|\Stringable $message, array $context = []): void;
}
