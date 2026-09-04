<?php

namespace WLA\Inmo\Localization;

final class ChilePreset
{
	public const COUNTRY_CODE = 'CL';

	/**
	 * Initial country-specific defaults. The core remains country-agnostic:
	 * these values are merged by Settings\Schema and can be replaced later.
	 *
	 * @return array<string,string>
	 */
	public static function settings(): array
	{
		return array(
			'country_code'     => self::COUNTRY_CODE,
			'currency_primary' => 'CLP',
			'area_unit'        => 'm2',
			'map_provider'     => 'osm',
		);
	}
}
