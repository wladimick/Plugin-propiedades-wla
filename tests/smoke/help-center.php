<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$help = file_get_contents($root . '/plugin/wla-inmo/src/Admin/HelpCenter.php');
$onboarding = file_get_contents($root . '/plugin/wla-inmo/src/Admin/Onboarding.php');
$context = file_get_contents($root . '/plugin/wla-inmo/src/Admin/ContextHelp.php');
$assets = file_get_contents($root . '/plugin/wla-inmo/src/Admin/Assets.php');
$script = file_get_contents($root . '/plugin/wla-inmo/assets/admin/help-center.js');

function wlaHelpExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

wlaHelpExpect(is_string($help) && str_contains($help, 'final class HelpCenter'), 'HelpCenter class is missing.');
wlaHelpExpect(substr_count((string) $help, "'id'       =>") >= 13, 'HelpCenter should expose the documented initial topic set.');
wlaHelpExpect(str_contains((string) $help, "'status'   => 'planned'"), 'Future modules must be marked as planned.');
wlaHelpExpect(str_contains((string) $help, 'No. El Core no depende de ellos.'), 'FAQ should state that legacy builder/commerce dependencies are not required.');
wlaHelpExpect(!preg_match('/wp_remote_|curl_|file_get_contents\s*\(\s*[\'\"]https?:/i', (string) $help), 'Help content must not make remote calls.');

wlaHelpExpect(is_string($onboarding) && str_contains($onboarding, 'update_user_meta'), 'Onboarding progress must be per user.');
wlaHelpExpect(str_contains((string) $onboarding, 'wp_verify_nonce'), 'Onboarding writes must verify a nonce.');
wlaHelpExpect(str_contains((string) $onboarding, 'current_user_can'), 'Onboarding writes must enforce capabilities.');
wlaHelpExpect(!str_contains((string) $onboarding, 'update_option('), 'Onboarding progress must not be global option state.');

wlaHelpExpect(is_string($context) && str_contains($context, 'Datos de la propiedad') && str_contains($context, 'Multimedia') && str_contains($context, 'Calidad'), 'Property context help should explain data, media and quality in one focused tab.');
wlaHelpExpect(is_string($assets) && str_contains($assets, 'help-center.css') && str_contains($assets, 'help-center.js'), 'Help assets must be packaged and scoped.');
wlaHelpExpect(is_string($script) && str_contains($script, 'data-wla-help-topic'), 'Help search must operate on local packaged topics.');
wlaHelpExpect(!preg_match('/fetch\s*\(|XMLHttpRequest|axios|\.ajax\s*\(/i', (string) $script), 'Help search must not make network requests.');

echo "WLA Inmo help center smoke tests passed.\n";
