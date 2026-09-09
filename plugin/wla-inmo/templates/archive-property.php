<?php
/**
 * WLA Inmo fallback property archive.
 *
 * Themes may override this file at wla-inmo/archive-property.php.
 */

if (!defined('ABSPATH')) {
	exit;
}

get_header();

$archiveTitle = post_type_archive_title('', false);
if (!is_string($archiveTitle) || $archiveTitle === '') {
	$archiveTitle = __('Properties', 'wla-inmo');
}
?>
<main id="wla-inmo-main" class="wla-inmo wla-inmo-archive">
	<div class="wla-inmo__container">
		<header class="wla-inmo__header">
			<h1 class="wla-inmo__title"><?php echo esc_html($archiveTitle); ?></h1>
		</header>

		<?php if (have_posts()) : ?>
			<div class="wla-inmo-property-list">
				<?php while (have_posts()) : ?>
					<?php
					the_post();
					$permalink = get_permalink();
					$excerpt = get_the_excerpt();
					?>
					<article class="wla-inmo-property-item">
						<h2 class="wla-inmo-property-item__title">
							<a class="wla-inmo-property-item__link" href="<?php echo esc_url(is_string($permalink) ? $permalink : ''); ?>">
								<?php echo esc_html(get_the_title()); ?>
							</a>
						</h2>

						<?php if (is_string($excerpt) && $excerpt !== '') : ?>
							<div class="wla-inmo-property-item__excerpt">
								<?php echo wp_kses_post(wpautop($excerpt)); ?>
							</div>
						<?php endif; ?>
					</article>
				<?php endwhile; ?>
			</div>

			<div class="wla-inmo-pagination">
				<?php
				the_posts_pagination(
					array(
						'mid_size'  => 1,
						'prev_text' => __('Previous', 'wla-inmo'),
						'next_text' => __('Next', 'wla-inmo'),
					)
				);
				?>
			</div>
		<?php else : ?>
			<p class="wla-inmo-empty-state"><?php echo esc_html__('No properties are available.', 'wla-inmo'); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
