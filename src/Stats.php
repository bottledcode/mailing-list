<?php

namespace Withinboredom\MailingList;

use Withinboredom\Time\Time;
use Withinboredom\Time\TimeUnit;

class Stats
{
	public array $stats = [];

	public int $totalEmails = 0;

	public function addStats(Email $email): void
	{
		$this->stats[$email->address][$email->id] = $email->time;
		$this->totalEmails++;
	}

	public function renderTopEmails(): string
	{
		arsort($this->stats);
		$top = array_slice($this->stats, 0, 10);
		$top = array_map(fn($emails) => count($emails), $top);
		$top = array_map(
			fn($email, $count) => "$email: " . number_format($count / $this->totalEmails * 100, 2),
			array_keys($top),
			$top,
		);

		return implode("\n", $top);
	}

	public function renderHistory(string $file): void
	{
		$history = fopen($file, 'wb');
		foreach ($this->stats as $email => $emails) {
			foreach ($emails as $id => $time) {
				fputcsv($history, [$email, $id, (new \DateTimeImmutable('@' . $time))->format('Y-m-d H:i:s')]);
			}
		}
		fclose($history);
	}

	public function getStats(Time $windowSize, \DateTimeImmutable $from): array
	{
		$currentTime = $from->getTimestamp();
		$totalEmails = 0;
		$start = $currentTime - $windowSize->as(TimeUnit::Seconds);
		foreach ($this->stats as $address => $emails) {
			foreach ($emails as $time) {
				if ($time > $start && $time < $currentTime) {
					$totalEmails++;
				}
			}
		}

		$userCounts = [];
		foreach ($this->stats as $address => $emails) {
			$userCounts[$address] = 0;
			foreach ($emails as $time) {
				if ($time > $start && $time < $currentTime) {
					$userCounts[$address]++;
				}
			}
			$userCounts[$address] = ($userCounts[$address] / ($totalEmails ?: 1)) * 100;
		}

		arsort($userCounts);

		return $userCounts;
	}
}
