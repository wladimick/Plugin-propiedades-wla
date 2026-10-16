<?php
/**
 * Public property archive. Cards are rendered from explicit public data only.
 *
 * Themes may override this file at wla-inmo/archive-property.php.
 */
if (!defined('ABSPATH')) {
	exit;
}

get_header();

$archiveTitle = post_type_archive_title('', false);
if (!is_string($archiveTitle) || $archiveTitle === '') {
	$archiveTitle = __('Propiedades', 'wla-inmo');
}
?>
<main id="wla-inmo-main" class="wla-inmo wla-inmo-archive">
	<div class="wla-inmo__container">
		<header class="wla-inmo__header">
			<h1 class="wla-inmo__title"><?php echo esc_html($archiveTitle); ?></h1>
			<p class="wla-inmo-archive__description"><?php echo esc_html__('Explora las propiedades disponibles.', 'wla-inmo'); ?></p>
		</header>

		<?php if (have_posts()) : ?>
			<div class="wla-inmo-property-list">
				<?php while (have_posts()) : ?>
					<?php
					the_post();
					$propertyId = get_the_ID();
					$property = \WLA\Inmo\Frontend\PropertyCard::forPost((int) $propertyId);
					if ($property === null) {
						continue;
					}

					do_action('wla_inmo_before_property_card', (int) $propertyId);
					$card = \WLA\Inmo\Frontend\Renderer::render(
						'parts/property-card.php',
						array('property' => $property)
					);
					if (is_string($card)) {
						echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template partial escapes all dynamic content.
					}
					do_action('wla_inmo_after_property_card', (int) $propertyId);
					?>
				<?php endwhile; ?>
			</div>

			<nav class="wla-inmo-pagination" aria-label="<?php echo esc_attr__('Páginas de propiedades', 'wla-inmo'); ?>">
				<?php
				the_posts_pagination(
					array(
						'mid_size' => 1,
						'prev_text' => __('Anterior', 'wla-inmo'),
						'next_text' => __('Siguiente', 'wla-inmo'),
						'screen_reader_text' => __('Navegación de propiedades', 'wla-inmo'),
					)
				);
				?>
			</nav>
		<?php else : ?>
			<p class="wla-inmo-empty-state"><?php echo esc_html__('Por ahora no hay propiedades publicadas.', 'wla-inmo'); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
