<?php
/**
 * Public read-only search form, server-side rendered with no JS dependency.
 */
if (!defined('ABSPATH')) {
	exit;
}

$filters = isset($wla_args['filters']) && is_array($wla_args['filters']) ? $wla_args['filters'] : array();
$archiveUrl = get_post_type_archive_link(\WLA\Inmo\Properties\PostType::POST_TYPE);
$archiveUrl = is_string($archiveUrl) ? $archiveUrl : '';

$taxOptions = array(
	'operacion' => __('Operación', 'wla-inmo'),
	'tipo' => __('Tipo', 'wla-inmo'),
	'region' => __('Región', 'wla-inmo'),
	'comuna' => __('Comuna', 'wla-inmo'),
	'sector' => __('Sector', 'wla-inmo'),
);
?>
<form class="wla-inmo-search" method="get" action="<?php echo esc_url($archiveUrl); ?>" role="search">
	<div class="wla-inmo-search__fields">
		<div class="wla-inmo-search__field wla-inmo-search__field--wide">
			<label for="wla-search-q"><?php echo esc_html__('Buscar propiedades', 'wla-inmo'); ?></label>
			<input type="search" id="wla-search-q" name="q" maxlength="100" value="<?php echo esc_attr((string) ($filters['q'] ?? '')); ?>" placeholder="<?php echo esc_attr__('Ej.: casa con terraza', 'wla-inmo'); ?>" />
		</div>

		<?php foreach ($taxOptions as $param => $label) : ?>
			<?php
			$taxonomy = \WLA\Inmo\Frontend\SearchFilters::TAXONOMIES[$param];
			$terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 150, 'orderby' => 'name'));
			if (is_wp_error($terms) || !is_array($terms)) {
				$terms = array();
			}
			?>
			<div class="wla-inmo-search__field">
				<label for="<?php echo esc_attr('wla-search-' . $param); ?>"><?php echo esc_html($label); ?></label>
				<select id="<?php echo esc_attr('wla-search-' . $param); ?>" name="<?php echo esc_attr($param); ?>">
					<option value=""><?php echo esc_html__('Todas', 'wla-inmo'); ?></option>
					<?php foreach ($terms as $term) : ?>
						<option value="<?php echo esc_attr((string) $term->slug); ?>" <?php selected((string) ($filters[$param] ?? ''), (string) $term->slug); ?>>
							<?php echo esc_html((string) $term->name); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endforeach; ?>

		<div class="wla-inmo-search__field">
			<label for="wla-search-moneda"><?php echo esc_html__('Moneda', 'wla-inmo'); ?></label>
			<select name="moneda" id="wla-search-moneda">
				<option value=""><?php echo esc_html__('Cualquier moneda', 'wla-inmo'); ?></option>
				<?php foreach (array('CLP', 'UF', 'USD') as $currency) : ?>
					<option value="<?php echo esc_attr($currency); ?>" <?php selected((string) ($filters['moneda'] ?? ''), $currency); ?>><?php echo esc_html($currency); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php
		$numeric = array(
			'precio_min' => array(__('Precio mínimo', 'wla-inmo'), '0.01'),
			'precio_max' => array(__('Precio máximo', 'wla-inmo'), '0.01'),
			'dormitorios' => array(__('Dormitorios mín.', 'wla-inmo'), '1'),
			'banos' => array(__('Baños mín.', 'wla-inmo'), '1'),
			'superficie_min' => array(__('Superficie construida mín. (m²)', 'wla-inmo'), '0.01'),
		);
		?>
		<?php foreach ($numeric as $param => $config) : ?>
			<div class="wla-inmo-search__field">
				<label for="<?php echo esc_attr('wla-search-' . $param); ?>"><?php echo esc_html($config[0]); ?></label>
				<input type="number" id="<?php echo esc_attr('wla-search-' . $param); ?>" name="<?php echo esc_attr($param); ?>" value="<?php echo esc_attr((string) ($filters[$param] ?? '')); ?>" min="0" step="<?php echo esc_attr($config[1]); ?>" />
			</div>
		<?php endforeach; ?>
	</div>
	<div class="wla-inmo-search__actions">
		<button type="submit"><?php echo esc_html__('Buscar propiedades', 'wla-inmo'); ?></button>
		<a href="<?php echo esc_url($archiveUrl); ?>"><?php echo esc_html__('Limpiar filtros', 'wla-inmo'); ?></a>
	</div>
</form>
