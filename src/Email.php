<?php

namespace Withinboredom\MailingList;

class Email
{
	public function __construct(public int $id, public string $address, public int $time)
	{
		$this->address = str_replace('+dot+', '.', $this->address);
		$this->address = str_replace('+at+', '@', $this->address);
		$this->address = str_replace('%40', '@', $this->address);

		if (getenv('UNSAFE_EMAILS')) {
			return;
		}

		$this->address = self::doHide($this->address);
	}

	private static array $knownEmails = [];

	private static function doHide(string $address): string
	{
		if (empty(self::$knownEmails)) {
			self::$knownEmails = json_decode($_COOKIE['knownEmails'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
		}

		if (isset(self::$knownEmails[$address])) {
			return $address;
		}

		$hash = md5($address . (getenv('SALT') ?? ''), true);

		// format the hash as a uuid
		$hash[6] = chr(ord($hash[6]) & 0x0F | 0x40);
		$hash[8] = chr(ord($hash[8]) & 0x3F | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($hash), 4));
	}
}
