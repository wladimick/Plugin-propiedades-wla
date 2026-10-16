<?php
/**
 * WLA Inmo fallback single property template.
 *
 * Themes may override this file at wla-inmo/single-property.php.
 */

if (!defined('ABSPATH')) {
	exit;
}

get_header();
?>
<main id="wla-inmo-main" class="wla-inmo wla-inmo-single">
	<div class="wla-inmo__container">
		<?php while (have_posts()) : ?>
			<?php
			the_post();
			$content = apply_filters('the_content', get_the_content());
			?>
			<article class="wla-inmo-property">
				<header class="wla-inmo-property__header">
					<h1 class="wla-inmo-property__title"><?php echo esc_html(get_the_title()); ?></h1>
				</header>

				<?php if (is_string($content) && $content !== '') : ?>
					<div class="wla-inmo-property__content">
						<?php echo wp_kses_post($content); ?>
					</div>
				<?php endif; ?>
			</article>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
