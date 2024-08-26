<?php

namespace Withinboredom\MailingList;

use function Withinboredom\Time\Hours;
use function Withinboredom\Time\Seconds;

class Downloader
{

	public int $startTimestamp;
	public int $endTimestamp;

	/**
	 * @param array<Email> $emails
	 */
	public function __construct(public array $emails, public string|null $previous = null)
	{
		$this->endTimestamp = array_reduce($emails, fn($carry, $email) => max($carry, $email->time), 0);
		$this->startTimestamp = array_reduce(
			$emails,
			fn($carry, $email) => min($carry, $email->time > 0 ? $email->time : PHP_INT_MAX),
			$this->endTimestamp,
		);
	}

	public static function download(string $url, bool $force = false): Downloader
	{
		$id = basename($url);
		if (is_numeric($id)) {
			// clamp to a number divisible by 20
			$new = floor($id / 20) * 20;
			$url = str_replace($id, $new, $url);
		}

		if (!$force && file_exists(DOWNLOAD_DIR . basename($url))) {
			if (!is_numeric($id)) {
				$createdAt = filectime(DOWNLOAD_DIR . basename($url));
				if ((Seconds(time() - $createdAt) > Hours(2))) {
					return self::download($url, force: true);
				}
			}

			$data = file_get_contents(DOWNLOAD_DIR . basename($url));
		} else {
			sleep(2);
			$data = self::doActualDownload($url, DOWNLOAD_DIR . basename($url));
		}
		if (empty($data)) {
			return self::download($url, force: true);
		}

		return self::parse($data);
	}

	private static function doActualDownload(string $url, string $file): string {
		flock($fp = fopen($file . '_download', 'wb'), LOCK_EX);
		$data = file_get_contents($url);
		file_put_contents($file, $data);
		flock($fp, LOCK_UN);
		fclose($fp);
		return $data;
	}

	public static function parse(string $file): self
	{
		if (empty($file)) {
			throw new \InvalidArgumentException('File is empty');
		}

		$lines = explode("\n", $file);
		$emails = [];

		foreach ($lines as $r => $line) {
			if (str_contains($line, 'nav') && str_contains($line, 'previous')) {
				$start = strpos($line, 'href="') + 6;
				$end = strpos($line, '"', $start);
				$prev = substr($line, $start, $end - $start);
			}

			if (!str_contains($line, 'vcard')) {
				continue;
			}

			if (str_contains($line, 'mailto:internals-subscribe@lists.php.net')) {
				continue;
			}

			if (str_contains($line, 'mailto:')) {
				$start = strpos($line, 'mailto:') + 7;
				$end = strpos($line, '"', $start);
				$email = substr($line, $start, $end - $start);
			} else {
				$start = strpos($line, '"vcard">') + 8;
				$end = strpos($line, '<', $start);
				$email = substr($line, $start, $end - $start);
			}

			$id = $lines[$r - 1];
			$start = strpos($id, '/php.internals/');
			$end = strpos($id, '"', $start);
			$id = substr($id, $start + 15, $end - $start - 15);

			$date = $lines[$r + 1];
			$date = trim($date);
			$date = substr($date, strlen('<td class="align-center"><span class=\'monospace mod-small\'>'));
			$date = substr($date, 0, strpos($date, '<'));
			$date = str_replace('&nbsp;', ' ', $date);
			$date = html_entity_decode($date);
			$date = substr($date, strpos($date, ',') + 2);
			$date = strtotime($date);

			$emails[$id] = new Email((int)$id, $email, $date);
		}

		return new self($emails, $prev ?? null);
	}
}
