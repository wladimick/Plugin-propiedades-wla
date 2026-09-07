<?php

namespace WLA\Inmo\Admin;

final class ImportExportHub
{
	public static function render(): void
	{
		$format = ImportRequest::queryScalar('wla_format');
		$format = $format === 'json' ? 'json' : 'csv';

		$csvUrl = add_query_arg(array('page' => 'wla-inmo-import-export'), admin_url('admin.php'));
		$jsonUrl = add_query_arg(array('page' => 'wla-inmo-import-export', 'wla_format' => 'json'), admin_url('admin.php'));

		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__('Formato de importación', 'wla-inmo') . '">';
		echo '<a class="nav-tab' . ($format === 'csv' ? ' nav-tab-active' : '') . '" href="' . esc_url($csvUrl) . '">' . esc_html__('CSV', 'wla-inmo') . '</a>';
		echo '<a class="nav-tab' . ($format === 'json' ? ' nav-tab-active' : '') . '" href="' . esc_url($jsonUrl) . '">' . esc_html__('JSON WLA', 'wla-inmo') . '</a>';
		echo '</nav>';

		if ($format === 'json') {
			JsonImportExportPage::render();
			return;
		}

		ImportExportPage::render();
	}
}
