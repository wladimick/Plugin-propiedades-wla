<?php
/**
 * WLA Inmo fallback property archive foundation.
 *
 * Themes may override this file at wla-inmo/archive-property.php.
 * Property listing/cards/pagination are introduced in PR 4.2.
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
	</div>
</main>
<?php
get_footer();
