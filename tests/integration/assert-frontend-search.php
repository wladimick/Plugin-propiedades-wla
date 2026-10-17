<?php
/**
 * WordPress integration of bounded read-only search/filter normalization.
 * This harness runs only against the CI installed release ZIP.
 */
if (!defined('ABSPATH')) {
	exit(1);
}
$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};
$filters = \WLA\Inmo\Frontend\SearchFilters::normalize(array(
	'q' => '<b>Casa</b>   centro',
	'operacion' => 'venta',
	'region' => 'maule',
	'precio_min' => '30000000',
	'precio_max' => '20000000',
	'dormitorios' => '3',
	'banos' => '2',
	'superficie_min' => '90,5',
	'unknown_meta' => '_wla_inmo_private_address',
));
if (!isset($filters['q']) || $filters['q'] !== 'Casa centro'
	|| $filters['precio_min'] !== '20000000'
	|| $filters['precio_max'] !== '30000000'
	|| $filters['superficie_min'] !== '90.5'
	|| isset($filters['unknown_meta'])) {
	$fail('Search allowlist, keyword cleaning, range normalization or numeric filter failed.');
}
if (\WLA\Inmo\Frontend\SearchFilters::normalize(array(
	'operacion' => array('venta', 'arriendo'),
	'moneda' => 'FOO',
	'precio_min' => '1e15',
	'banos' => '-2',
	'superficie_min' => '1 OR 1=1',
	'password' => 'x',
)) !== array()) {
	$fail('Invalid, array-valued or unknown GET inputs were accepted.');
}
if (\WLA\Inmo\Frontend\SearchFilters::normalize(array()) !== array()) {
	$fail('Empty input must produce unfiltered archive.');
}

$query = new WP_Query();
$applied = \WLA\Inmo\Frontend\SearchFilters::apply($query, array(
	'operacion' => 'venta',
	'comuna' => 'curico',
	'moneda' => 'UF',
	'precio_min' => '2000',
	'precio_max' => '4000',
	'dormitorios' => '3',
));
$tax = $query->get('tax_query');
$meta = $query->get('meta_query');
if (empty($tax) || empty($meta) || $applied['moneda'] !== 'UF' || $query->get('post__in')) {
	$fail('Allowed filters were not translated to standard WordPress query vars.');
}
foreach ($tax as $clause) {
	if (is_array($clause) && isset($clause['taxonomy'])
		&& !in_array((string) $clause['taxonomy'], \WLA\Inmo\Frontend\SearchFilters::TAXONOMIES, true)) {
		$fail('Non-allowlisted taxonomy entered query vars.');
	}
}

$searchHtml = \WLA\Inmo\Frontend\Renderer::render(
	'parts/property-search.php',
	array('filters' => array('q' => 'Casa', 'operacion' => 'venta'))
);
if (!is_string($searchHtml) || !str_contains($searchHtml, 'method="get"')
	|| !str_contains($searchHtml, 'name="q"')
	|| !str_contains($searchHtml, 'name="operacion"')
	|| !str_contains($searchHtml, 'type="submit"')) {
	$fail('Public form does not render accessible SSR GET contract.');
}
if (str_contains($searchHtml, '_wla_inmo_private_address') || str_contains($searchHtml, 'name="internal_notes"')) {
	$fail('Private metadata was exposed in public search form.');
}
echo "WLA Inmo read-only public search filters integration passed.\n";
