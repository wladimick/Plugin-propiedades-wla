<?php
/**
 * Integration checks for public-only archive/card projection and query limits.
 * Executed by WP-CLI against an installed release ZIP, never production.
 */
if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if (!class_exists(\WLA\Inmo\Frontend\PropertyCard::class)
	|| !class_exists(\WLA\Inmo\Frontend\ArchiveQuery::class)
) {
	$fail('Archive classes are not available in the installed plugin.');
}

$propertyId = wp_insert_post(array(
	'post_type' => \WLA\Inmo\Properties\PostType::POST_TYPE,
	'post_status' => 'publish',
	'post_title' => 'Casa Test Pública WLA',
	'post_excerpt' => 'Casa de prueba para validar tarjeta.',
), true);
if (is_wp_error($propertyId) || (int) $propertyId < 1) {
	$fail('Unable to create published property.');
}
$propertyId = (int) $propertyId;
$draftId = wp_insert_post(array(
	'post_type' => \WLA\Inmo\Properties\PostType::POST_TYPE,
	'post_status' => 'draft',
	'post_title' => 'No debe verse públicamente',
), true);
if (is_wp_error($draftId) || (int) $draftId < 1) {
	$fail('Unable to create draft fixture.');
}
$draftId = (int) $draftId;

update_post_meta($propertyId, '_wla_inmo_currency_primary', 'CLP');
update_post_meta($propertyId, '_wla_inmo_price_clp', 120000000);
update_post_meta($propertyId, '_wla_inmo_bedrooms', 3);
update_post_meta($propertyId, '_wla_inmo_private_address', 'PRIVATE-WLA-ADDRESS-DO-NOT-PRINT');
update_post_meta($propertyId, '_wla_inmo_internal_notes', 'PRIVATE-WLA-NOTES-DO-NOT-PRINT');
update_post_meta($propertyId, '_wla_inmo_external_id', 'PRIVATE-WLA-EXTERNAL-ID');

$public = \WLA\Inmo\Frontend\PropertyCard::forPost($propertyId);
if (!is_array($public) || empty($public['title']) || empty($public['url']) || empty($public['price'])) {
	$fail('Published property lacks public card fields.');
}
if (\WLA\Inmo\Frontend\PropertyCard::forPost($draftId) !== null) {
	$fail('Draft property entered public card projection.');
}
if (preg_replace('/\D+/', '', (string) $public['price']) !== '120000000') {
	$fail('CLP price was not formatted from canonical public data.');
}
if (!is_array($public['details']) || $public['details'] === array()) {
	$fail('Public card did not load available attributes.');
}

$html = \WLA\Inmo\Frontend\Renderer::render('parts/property-card.php', array('property' => $public));
if (!is_string($html) || !str_contains($html, 'Casa Test Pública WLA') || !str_contains($html, 'wla-inmo-card')) {
	$fail('Public card partial failed to render.');
}
foreach (array('PRIVATE-WLA-ADDRESS', 'PRIVATE-WLA-NOTES', 'PRIVATE-WLA-EXTERNAL-ID') as $marker) {
	if (str_contains($html, $marker) || str_contains(wp_json_encode($public), $marker)) {
		$fail('Private metadata leaked into a public card.');
	}
}

update_post_meta($propertyId, '_wla_inmo_hide_price', 1);
$hidden = \WLA\Inmo\Frontend\PropertyCard::forPost($propertyId);
if (!is_array($hidden) || $hidden['price'] !== 'Precio a consultar') {
	$fail('hide_price setting did not suppress the published amount.');
}

global $wp_query, $wp_the_query;
$originalQuery = $wp_query;
$originalMainQuery = $wp_the_query;
$archive = new WP_Query();
$wp_query = $archive;
$wp_the_query = $archive;
$archive->is_post_type_archive = true;
$archive->set('post_type', \WLA\Inmo\Properties\PostType::POST_TYPE);

\WLA\Inmo\Frontend\ArchiveQuery::configure($archive);

if ((int) $archive->get('posts_per_page') !== 12 || $archive->get('post_status') !== 'publish') {
	$fail('Archive query is not bounded to 12 published properties.');
}
if ($archive->get('no_found_rows') !== false || $archive->get('update_post_meta_cache') !== true) {
	$fail('Archive query lacks pagination count or meta-cache preload.');
}

$unrelated = new WP_Query();
$unrelated->set('post_type', 'post');
$unrelated->set('posts_per_page', 7);
\WLA\Inmo\Frontend\ArchiveQuery::configure($unrelated);
if ((int) $unrelated->get('posts_per_page') !== 7) {
	$fail('Archive query modified an unrelated post query.');
}

$wp_query = $originalQuery;
$wp_the_query = $originalMainQuery;
wp_delete_post($propertyId, true);
wp_delete_post($draftId, true);

echo "WLA Inmo public property archive/card integration passed.\n";
