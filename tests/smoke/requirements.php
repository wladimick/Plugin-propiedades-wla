<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Core/Requirements.php';

use WLA\Inmo\Core\Requirements;

function wlaSmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

wlaSmokeExpect(Requirements::supportsPhp('8.1.0'), 'PHP 8.1 must be supported.');
wlaSmokeExpect(Requirements::supportsPhp('8.4.0'), 'Newer PHP versions must be supported.');
wlaSmokeExpect(!Requirements::supportsPhp('8.0.30'), 'PHP 8.0 must be rejected.');
wlaSmokeExpect(Requirements::supportsWordPress('6.6'), 'WordPress 6.6 must be supported.');
wlaSmokeExpect(Requirements::supportsWordPress('7.0'), 'Newer WordPress versions must be supported.');
wlaSmokeExpect(!Requirements::supportsWordPress('6.5.5'), 'WordPress 6.5 must be rejected.');
wlaSmokeExpect(count(Requirements::failures('8.0.30', '6.5.5')) === 2, 'Both unsupported platforms must be reported.');
wlaSmokeExpect(Requirements::failures('8.1.0', '6.6') === array(), 'Supported platform must have no failures.');

$pluginFile = dirname(__DIR__, 2) . '/plugin/wla-inmo/wla-inmo.php';
$header = file_get_contents($pluginFile);

wlaSmokeExpect(is_string($header), 'Plugin bootstrap must be readable.');
wlaSmokeExpect(strpos($header, 'Requires PHP: ' . Requirements::MIN_PHP) !== false, 'Plugin header PHP minimum must match Requirements.');
wlaSmokeExpect(strpos($header, 'Requires at least: ' . Requirements::MIN_WP) !== false, 'Plugin header WordPress minimum must match Requirements.');
wlaSmokeExpect(strpos($header, "define('WLA_INMO_MIN_PHP', '" . Requirements::MIN_PHP . "')") !== false, 'Bootstrap PHP constant must match Requirements.');
wlaSmokeExpect(strpos($header, "define('WLA_INMO_MIN_WP', '" . Requirements::MIN_WP . "')") !== false, 'Bootstrap WordPress constant must match Requirements.');

echo "WLA Inmo requirements smoke tests passed.\n";
