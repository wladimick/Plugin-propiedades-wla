<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if (!class_exists(WLA\Inmo\Frontend\TemplateResolver::class)
	|| !class_exists(WLA\Inmo\Frontend\Renderer::class)
	|| !class_exists(WLA\Inmo\Frontend\Bootstrap::class)
	|| !class_exists(WLA\Inmo\Frontend\Assets::class)
) {
	$fail('Frontend foundation classes are unavailable from the active release ZIP.');
}

WLA\Inmo\Frontend\Bootstrap::register();

if (has_filter('template_include', array(WLA\Inmo\Frontend\Bootstrap::class, 'filterTemplate')) === false) {
	$fail('Frontend template_include hook is not registered.');
}
if (has_action('wp_enqueue_scripts', array(WLA\Inmo\Frontend\Assets::class, 'enqueue')) === false) {
	$fail('Frontend asset hook is not registered.');
}

$pluginArchive = WLA\Inmo\Frontend\TemplateResolver::locate('archive-property.php');
$pluginSingle = WLA\Inmo\Frontend\TemplateResolver::locate('single-property.php');
if (!is_string($pluginArchive) || !is_file($pluginArchive) || !str_starts_with($pluginArchive, realpath(WLA_INMO_DIR . 'templates') . DIRECTORY_SEPARATOR)) {
	$fail('Archive fallback does not resolve inside the plugin template root.');
}
if (!is_string($pluginSingle) || !is_file($pluginSingle) || !str_starts_with($pluginSingle, realpath(WLA_INMO_DIR . 'templates') . DIRECTORY_SEPARATOR)) {
	$fail('Single fallback does not resolve inside the plugin template root.');
}

if (WLA\Inmo\Frontend\TemplateResolver::locate('../archive-property.php') !== null
	|| WLA\Inmo\Frontend\TemplateResolver::locate('page.php') !== null
) {
	$fail('Unsafe or non-allowlisted template name was resolved.');
}

$outside = wp_tempnam('wla-frontend-outside.php');
if (!is_string($outside)) {
	$fail('Unable to create template-path security fixture.');
}
file_put_contents($outside, "<?php // outside allowed roots\n");
$escapeFilter = static fn () => $outside;
add_filter('wla_inmo_template_path', $escapeFilter, 99);
if (WLA\Inmo\Frontend\TemplateResolver::locate('archive-property.php') !== null) {
	$fail('Template path filter escaped the approved roots.');
}
remove_filter('wla_inmo_template_path', $escapeFilter, 99);
@unlink($outside);

$originalTheme = get_stylesheet();
$themeRoot = get_theme_root();
$parentSlug = 'wla-ci-parent';
$childSlug = 'wla-ci-child';
$parentDir = $themeRoot . '/' . $parentSlug;
$childDir = $themeRoot . '/' . $childSlug;

foreach (array($parentDir, $childDir) as $dir) {
	wp_mkdir_p($dir . '/wla-inmo');
	file_put_contents($dir . '/index.php', "<?php\n");
}
file_put_contents($parentDir . '/style.css', "/*\nTheme Name: WLA CI Parent\n*/\n");
file_put_contents($childDir . '/style.css', "/*\nTheme Name: WLA CI Child\nTemplate: {$parentSlug}\n*/\n");
file_put_contents($parentDir . '/wla-inmo/archive-property.php', "<?php // parent WLA override\n");
file_put_contents($childDir . '/wla-inmo/archive-property.php', "<?php // child WLA override\n");

wp_clean_themes_cache(true);
switch_theme($childSlug);

$childOverride = realpath($childDir . '/wla-inmo/archive-property.php');
$parentOverride = realpath($parentDir . '/wla-inmo/archive-property.php');
if (!is_string($childOverride) || WLA\Inmo\Frontend\TemplateResolver::locate('archive-property.php') !== $childOverride) {
	$fail('Child theme override does not have first precedence.');
}
unlink($childDir . '/wla-inmo/archive-property.php');
if (!is_string($parentOverride) || WLA\Inmo\Frontend\TemplateResolver::locate('archive-property.php') !== $parentOverride) {
	$fail('Parent theme override does not precede plugin fallback.');
}
unlink($parentDir . '/wla-inmo/archive-property.php');
if (WLA\Inmo\Frontend\TemplateResolver::locate('archive-property.php') !== $pluginArchive) {
	$fail('Plugin fallback is not restored when theme overrides are absent.');
}

$childSinglePath = $childDir . '/wla-inmo/single-property.php';
file_put_contents($childSinglePath, "<?php echo isset(\$marker) ? 'leaked-variable' : (string) (\$wla_args['marker'] ?? '');\n");
$beforeHook = static function ($template, $args): void {
	echo 'before:' . $template . ':' . ($args['marker'] ?? '') . '|';
};
$afterHook = static function ($template, $args): void {
	echo '|after:' . $template . ':' . ($args['marker'] ?? '');
};
add_action('wla_inmo_before_template', $beforeHook, 10, 2);
add_action('wla_inmo_after_template', $afterHook, 10, 2);
$rendered = WLA\Inmo\Frontend\Renderer::render('single-property.php', array('marker' => 'integration-ok'));
remove_action('wla_inmo_before_template', $beforeHook, 10);
remove_action('wla_inmo_after_template', $afterHook, 10);
if ($rendered !== 'before:single-property.php:integration-ok|integration-ok|after:single-property.php:integration-ok') {
	$fail('Scoped renderer or public template hooks failed in WordPress.');
}
if (WLA\Inmo\Frontend\Renderer::render('../single-property.php') !== null) {
	$fail('Renderer accepted an unsafe template path.');
}
unlink($childSinglePath);

global $wp_query;
$originalQuery = $wp_query;
$hadCurrentScreen = array_key_exists('current_screen', $GLOBALS);
$originalScreen = $GLOBALS['current_screen'] ?? null;
$GLOBALS['current_screen'] = new class {
	public function in_admin($admin = null): bool
	{
		unset($admin);
		return false;
	}
};
$currentTemplate = $themeRoot . '/index.php';

$wp_query = new WP_Query();
if (WLA\Inmo\Frontend\Bootstrap::filterTemplate($currentTemplate) !== $currentTemplate) {
	$fail('Unrelated WordPress request was intercepted by WLA frontend routing.');
}

$wp_query = new WP_Query();
$wp_query->is_post_type_archive = true;
$wp_query->queried_object = get_post_type_object(WLA\Inmo\Properties\PostType::POST_TYPE);
if (WLA\Inmo\Frontend\Bootstrap::filterTemplate($currentTemplate) !== $pluginArchive) {
	$fail('WLA property archive did not route to the fallback template.');
}

$propertyId = wp_insert_post(
	array(
		'post_type'    => WLA\Inmo\Properties\PostType::POST_TYPE,
		'post_status'  => 'publish',
		'post_title'   => 'Frontend Foundation CI Property',
		'post_content' => 'Contenido público de prueba para el fallback single.',
	),
	true
);
if (is_wp_error($propertyId) || (int) $propertyId < 1) {
	$fail('Unable to create frontend property fixture.');
}
$propertyId = (int) $propertyId;
update_post_meta($propertyId, '_wla_inmo_private_address', 'PRIVATE-FRONTEND-MARKER');
update_post_meta($propertyId, '_wla_inmo_internal_notes', 'INTERNAL-FRONTEND-MARKER');

$wp_query = new WP_Query();
$wp_query->is_singular = true;
$wp_query->is_single = true;
$wp_query->queried_object = get_post($propertyId);
if (WLA\Inmo\Frontend\Bootstrap::filterTemplate($currentTemplate) !== $pluginSingle) {
	$fail('WLA property single did not route to the fallback template.');
}

wp_dequeue_style(WLA\Inmo\Frontend\Assets::STYLE_HANDLE);
$wp_query = new WP_Query();
WLA\Inmo\Frontend\Assets::enqueue();
if (wp_style_is(WLA\Inmo\Frontend\Assets::STYLE_HANDLE, 'enqueued')) {
	$fail('WLA frontend stylesheet loaded on an unrelated request.');
}

$wp_query = new WP_Query();
$wp_query->is_post_type_archive = true;
$wp_query->queried_object = get_post_type_object(WLA\Inmo\Properties\PostType::POST_TYPE);
WLA\Inmo\Frontend\Assets::enqueue();
if (!wp_style_is(WLA\Inmo\Frontend\Assets::STYLE_HANDLE, 'enqueued')) {
	$fail('WLA frontend stylesheet did not load on the property archive.');
}
$style = wp_styles()->registered[WLA\Inmo\Frontend\Assets::STYLE_HANDLE] ?? null;
if (!$style instanceof _WP_Dependency || $style->deps !== array()) {
	$fail('WLA frontend stylesheet unexpectedly depends on another runtime.');
}

foreach (array($pluginArchive, $pluginSingle) as $templatePath) {
	$source = file_get_contents($templatePath);
	if (!is_string($source) || preg_match('/private_address|internal_notes|external_id|get_post_meta/i', $source)) {
		$fail('Fallback template contains private or arbitrary meta access.');
	}
}

$wp_query = $originalQuery;
if ($hadCurrentScreen) {
	$GLOBALS['current_screen'] = $originalScreen;
} else {
	unset($GLOBALS['current_screen']);
}
wp_delete_post($propertyId, true);
switch_theme($originalTheme);
wp_clean_themes_cache(true);

foreach (array($childDir, $parentDir) as $dir) {
	@unlink($dir . '/wla-inmo/archive-property.php');
	@unlink($dir . '/wla-inmo/single-property.php');
	@unlink($dir . '/style.css');
	@unlink($dir . '/index.php');
	@rmdir($dir . '/wla-inmo');
	@rmdir($dir);
}

echo "WLA Inmo frontend foundation integration tests passed.\n";
