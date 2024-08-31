<?php

use Withinboredom\MailingList\Downloader;
use Withinboredom\MailingList\Stats;
use Withinboredom\Time\TimeUnit;

use function Withinboredom\Time\Days;
use function Withinboredom\Time\Weeks;

require_once __DIR__ . '/vendor/autoload.php';

function download(string $url, bool $force = false): Downloader
{
	$id = basename($url);
	if (is_numeric($id)) {
		// clamp to a number divisible by 20
		$new = floor($id / 20) * 20;
		$url = str_replace($id, $new, $url);
	}

	if (! $force && file_exists(__DIR__ . '/downloads/' . basename($url))) {
		$data = file_get_contents(__DIR__ . '/downloads/' . basename($url));
	} else {
		sleep(2);
		$data = file_get_contents($url);
		if (is_numeric($id)) {
			file_put_contents(__DIR__ . '/downloads/' . basename($url), $data);
		}
	}

	return Downloader::parse($data);
}

const DOWNLOAD_DIR = __DIR__ . '/downloads/';

$base = 'https://news-web.php.net';
$url = 'https://news-web.php.net/php.internals';

$range = Weeks(52);
$window = Days(365);
$start = (new DateTimeImmutable('tomorrow'))->sub(($range->add($window->multiply(2))->toDateInterval()));
$stats = new Stats();

do {
	$data = Downloader::download($url);
	if (count($data->emails) !== 20) {
		$data = download($url, force: true);
	}

	$url = $base . $data->previous;
	foreach ($data->emails as $email) {
		$stats->addStats($email);
	}
	echo (new DateTimeImmutable('@' . $data->startTimestamp))->format('Y-m-d') . "\n";
	echo $stats->renderTopEmails();
	echo "\n";
	echo "------\n";
} while ($data->startTimestamp > $start->getTimestamp());

$stats->renderHistory('php_internals.csv');
$top = $stats->getStats($window, new DateTimeImmutable('now'));
var_dump(array_slice($top, 0, 10));

$days = [];
for ($i = 0; $i < $range->as(TimeUnit::Days); $i++) {
	$start = new DateTimeImmutable("tomorrow - $i day");
	$days[$start->format('Y-m-d')] = $stats->getStats($window, $start);
}

$cutoff = 10;

$ok = [];
foreach ($days as $day => $data) {
	foreach ($data as $email => $count) {
		if ($ok[$email] ?? false) {
			continue;
		}
		if ($count > $cutoff) {
			$ok[$email] = true;
		}
	}
}

foreach ($days as $day => $data) {
	foreach ($data as $email => $count) {
		if ($ok[$email] ?? false) {
			continue;
		}
		unset($days[$day][$email]);
	}
}

file_put_contents('public/php_internals.json', json_encode($days));
