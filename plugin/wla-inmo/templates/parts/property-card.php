<?php
/**
 * Public card; its $wla_args contract contains explicit public fields only.
 */
if (!defined('ABSPATH')) {
	exit;
}

$property = isset($wla_args['property']) && is_array($wla_args['property']) ? $wla_args['property'] : array();
$url = isset($property['url']) ? (string) $property['url'] : '';
$title = isset($property['title']) ? (string) $property['title'] : '';
if ($url === '' || $title === '') {
	return;
}

$image = isset($property['image']) && is_string($property['image']) ? $property['image'] : '';
$operation = isset($property['operation']) ? (string) $property['operation'] : '';
$type = isset($property['type']) ? (string) $property['type'] : '';
$commune = isset($property['commune']) ? (string) $property['commune'] : '';
$sector = isset($property['sector']) ? (string) $property['sector'] : '';
$location = implode(' · ', array_filter(array($commune, $sector), static fn (string $value): bool => $value !== ''));
$price = isset($property['price']) ? (string) $property['price'] : '';
$excerpt = isset($property['excerpt']) ? (string) $property['excerpt'] : '';
$details = isset($property['details']) && is_array($property['details']) ? $property['details'] : array();
?>
<article class="wla-inmo-card">
	<?php if ($image !== '') : ?>
		<a class="wla-inmo-card__media" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr(sprintf(__('Ver propiedad: %s', 'wla-inmo'), $title)); ?>">
			<?php echo wp_kses_post($image); ?>
		</a>
	<?php endif; ?>

	<div class="wla-inmo-card__body">
		<?php if ($operation !== '' || $type !== '') : ?>
			<p class="wla-inmo-card__classification">
				<?php echo esc_html(implode(' · ', array_filter(array($operation, $type), static fn (string $value): bool => $value !== ''))); ?>
			</p>
		<?php endif; ?>

		<h2 class="wla-inmo-card__title">
			<a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
		</h2>

		<?php if ($location !== '') : ?>
			<p class="wla-inmo-card__location"><?php echo esc_html($location); ?></p>
		<?php endif; ?>

		<?php if ($price !== '') : ?>
			<p class="wla-inmo-card__price"><?php echo esc_html($price); ?></p>
		<?php endif; ?>

		<?php if ($details !== array()) : ?>
			<ul class="wla-inmo-card__features" aria-label="<?php echo esc_attr__('Características de la propiedad', 'wla-inmo'); ?>">
				<?php foreach ($details as $detail) : ?>
					<?php if (!is_array($detail) || empty($detail['label']) || empty($detail['value'])) { continue; } ?>
					<li>
						<span class="wla-inmo-card__feature-label"><?php echo esc_html((string) $detail['label']); ?>:</span>
						<strong><?php echo esc_html((string) $detail['value']); ?></strong>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ($excerpt !== '') : ?>
			<p class="wla-inmo-card__excerpt"><?php echo esc_html($excerpt); ?></p>
		<?php endif; ?>

		<a class="wla-inmo-card__action" href="<?php echo esc_url($url); ?>"><?php echo esc_html__('Ver propiedad', 'wla-inmo'); ?></a>
	</div>
</article>
