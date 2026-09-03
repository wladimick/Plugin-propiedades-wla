<?php

namespace WLA\Inmo\Search;

final class IndexRepository
{
	private $wpdb;

	public function __construct($wpdb = null)
	{
		if ($wpdb === null) {
			global $wpdb as $globalWpdb;
		}

		$this->wpdb = $wpdb;
	}

	/**
	 * Insert or replace one complete projection row.
	 *
	 * @param array<string, mixed> $row Projection row.
	 */
	public function replace(array $row): bool
	{
		if ($this->wpdb === null) {
			return false;
		}

		$formats = self::formats();
		$rowFormats = array();

		foreach (array_keys($row) as $column) {
			if (!isset($formats[$column])) {
				return false;
			}

			$rowFormats[] = $formats[$column];
		}

		$result = $this->wpdb->replace(
			IndexSchema::tableName($this->wpdb),
			$row,
			$rowFormats
		);

		return $result !== false;
	}

	public function delete(int $propertyId): bool
	{
		if ($this->wpdb === null || $propertyId < 1) {
			return false;
		}

		$result = $this->wpdb->delete(
			IndexSchema::tableName($this->wpdb),
			array('property_id' => $propertyId),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * @return array<string, string>
	 */
	public static function formats(): array
	{
		return array(
			'property_id'    => '%d',
			'property_code'  => '%s',
			'external_id'    => '%s',
			'status'         => '%s',
			'operation_slug' => '%s',
			'type_slug'      => '%s',
			'region_slug'    => '%s',
			'commune_slug'   => '%s',
			'sector_slug'    => '%s',
			'price_clp'      => '%d',
			'price_uf'       => '%f',
			'price_usd'      => '%f',
			'bedrooms'       => '%d',
			'bathrooms'      => '%d',
			'parking'        => '%d',
			'land_area_m2'   => '%f',
			'built_area_m2'  => '%f',
			'latitude'       => '%f',
			'longitude'      => '%f',
			'featured'       => '%d',
			'updated_at'     => '%s',
		);
	}
}
