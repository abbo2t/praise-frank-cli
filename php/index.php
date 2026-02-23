#!/usr/bin/env php
<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
	require $autoload;
} else {
	spl_autoload_register(static function (string $class): void {
		$prefix = 'PraiseFrank\\';
		$baseDir = __DIR__ . '/src/';
		$len = strlen($prefix);
		if (strncmp($prefix, $class, $len) !== 0) {
			return;
		}
		$relative = substr($class, $len);
		$relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
		$file = $baseDir . $relativePath;
		if (is_file($file)) {
			require $file;
		}
	});
}

use PraiseFrank\AnsiAnimationPlayer;

function print_usage(): void
{
	$script = basename(__FILE__);
	echo <<<USAGE
Usage: php {$script} [options]

Options:
  -f, --file <path>          Animation JSON file (default: fareway-frank-keir.json)
	  --no-loop              Play once then exit
	  --fps <value>          Override FPS when frames omit duration
	  --no-color             Disable colored output
	  --force-truecolor      Force 24-bit color sequences
	  --force-256            Force 256-color sequences
	  --prefer-flicker       Clear the screen each frame (reduces stutter)
	  --help                 Show this help message

USAGE;
}

$shortOpts = 'f:';
$longOpts = [
	'file:',
	'no-loop',
	'fps:',
	'no-color',
	'force-truecolor',
	'force-256',
	'prefer-flicker',
	'help',
];

$optind = null;
$options = getopt($shortOpts, $longOpts, $optind);
if ($options === false) {
	fwrite(STDERR, "Failed to parse options.\n");
	exit(64);
}

if (isset($options['help'])) {
	print_usage();
	exit(0);
}

$file = $options['f'] ?? $options['file'] ?? 'fareway-frank-keir.json';
$loop = !isset($options['no-loop']);
$fps = isset($options['fps']) ? (float)$options['fps'] : null;
$enableColor = !isset($options['no-color']);
$forcedMode = null;
if (isset($options['force-truecolor'])) {
	$forcedMode = 'truecolor';
} elseif (isset($options['force-256'])) {
	$forcedMode = '256';
}
$preferFlicker = isset($options['prefer-flicker']);

$player = new AnsiAnimationPlayer();

if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal') && defined('SIGTERM')) {
	pcntl_async_signals(true);
	pcntl_signal(SIGTERM, static function (): void {
		fwrite(STDOUT, "\x1b[?25h\x1b[?1049l");
		exit(0);
	});
}

try {
	[$frames, $metadata, $canvas] = $player->loadAnimation($file);
} catch (Throwable $throwable) {
	fwrite(STDERR, $throwable->getMessage() . "\n");
	exit(2);
}

if ($fps !== null) {
	foreach ($frames as &$frame) {
		if (!isset($frame['duration']) || !is_numeric($frame['duration'])) {
			$frame['duration'] = 1000.0 / max(1.0, $fps);
		}
	}
	unset($frame);
}

$canvasWidth = null;
if (is_array($canvas) && isset($canvas['width']) && is_numeric($canvas['width'])) {
	$canvasWidth = (int)$canvas['width'];
}

try {
	$player->play(
		$frames,
		[
			'loop' => $loop,
			'fps' => $fps,
			'canvasWidth' => $canvasWidth,
			'enableColor' => $enableColor,
			'forcedColorMode' => $forcedMode,
			'preferFlicker' => $preferFlicker,
		]
	);
} catch (Throwable $throwable) {
	fwrite(STDERR, 'Animation failed: ' . $throwable->getMessage() . "\n");
	exit(1);
}

exit(0);
