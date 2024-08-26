<?php

require_once __DIR__ . '/../vendor/autoload.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = $_POST['email'];
	$hash = md5($email . (getenv('SALT') ?? ''), true);
	$hash[6] = chr(ord($hash[6]) & 0x0f | 0x40);
	$hash[8] = chr(ord($hash[8]) & 0x3f | 0x80);
	$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($hash), 4));
	$existing = json_decode($_COOKIE['knownEmails'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
	$existing[$email] = '';
	setcookie('knownEmails', json_encode($existing, JSON_THROW_ON_ERROR), time() + 60 * 60 * 24 * 365);
	header('Location: /');
	http_response_code(303);
	exit;
}

const DOWNLOAD_DIR = __DIR__ . '/../downloads/';

use Withinboredom\MailingList\Downloader;use Withinboredom\MailingList\Stats;use Withinboredom\Time\TimeUnit;use function Withinboredom\Time\Days;use function Withinboredom\Time\Weeks;

$base = 'https://news-web.php.net';
$url = "https://news-web.php.net/php.internals";


$range = $_GET['range'] ?? 6;
if ($range < 1 || $range > 52) {
	$range = 6;
}
$range = Weeks($range);
$window = $_GET['window'] ?? 14;
$window = Days($window);

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
} while ($data->startTimestamp > $start->getTimestamp());

$days = [];
for($i = 0; $i < $range->as(TimeUnit::Days); $i++) {
	$start = new DateTimeImmutable("tomorrow - $i day");
	$days[$start->format('Y-m-d')] = $stats->getStats($window, $start);
}

$cutoff = 10;
$ok = [];
foreach($days as $day => $data) {
	foreach($data as $email => $count) {
		if($ok[$email] ?? false) {
			continue;
		}
		if($count > $cutoff) {
			$ok[$email] = true;
		}
	}
}

foreach($days as $day => $data) {
	foreach($data as $email => $count) {
		if($ok[$email] ?? false) {
			continue;
		}
		unset($days[$day][$email]);
	}
}

$json = json_encode($days, JSON_PRETTY_PRINT);


?><!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<meta name='viewport' content='width=device-width, initial-scale=1.0'>
	<title>Posting Ratios Plot</title>
	<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
	<script src='https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns'></script>
</head>
<body>
<div style='display:flex; justify-content: space-between;'>
	<div>
		<form method='get'>
			<label for='range' title='The lower/upper bounds of the graph'>Range (weeks)</label>
			<input
					type='number'
					id='range'
					name='range'
					value='<?= $range->as(TimeUnit::Weeks) ?>'
					min='1'
					max='52'
					required
			>
			<label for='window' title='The number of days to average over'>Window (days)</label>
			<input type='number' id='window' name='window' value='<?= $window->as(TimeUnit::Days) ?>' min='1' max='365' required>
			<button type='submit'>Update</button>
		</form>
	</div>
	<div>
		<form method='post'>
			<label for='email' title='The email address of the participant'>Email</label>
			<input type='email' id='email' name='email' required>
			<button type='submit'>Guess</button>
		</form>
	</div>
</div>
<hr>
<canvas id='postingRatiosChart' width='800' height='400'></canvas>
<script>
    async function t() {
        const data = <?= $json ?>;

        // Prepare the data for Chart.js
        const labels = Object.keys(data);
        const participants = Object.keys(data[labels[0]]);
        const datasets = participants.map((participant, index) => {
            return {
                label: participant,
                data: labels.map(date => data[date][participant]),
                borderColor: `hsl(${index * 360 / participants.length}, 100%, 50%)`,
                fill: false
            };
        });

        // Create the Chart
        const ctx = document.getElementById('postingRatiosChart').getContext('2d');
        const postingRatiosChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            tooltipFormat: 'yyyy-MM-dd',
                            displayFormats: {
                                day: 'yyyy-MM-dd'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Posting Ratio (%)'
                        }
                    }
                }
            }
        });
    }

    t();
</script>
</body>
</html>
