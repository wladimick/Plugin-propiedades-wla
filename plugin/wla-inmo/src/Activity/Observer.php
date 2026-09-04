<?php

namespace WLA\Inmo\Activity;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Settings\Schema as SettingsSchema;

final class Observer
{
	/** @var array<string,mixed> */
	private static array $previousMeta = array();
	private static bool $registered = false;

	public static function register(): void
	{
		if (self::$registered) {
			return;
		}
		self::$registered = true;

		add_action('wp_after_insert_post', array(self::class, 'onPostInserted'), 20, 4);
		add_action('transition_post_status', array(self::class, 'onPostStatusChanged'), 20, 3);
		add_action('update_post_meta', array(self::class, 'beforeMetaUpdate'), 10, 4);
		add_action('updated_post_meta', array(self::class, 'afterMetaUpdate'), 10, 4);
		add_action('added_post_meta', array(self::class, 'afterMetaAdded'), 10, 4);
		add_action('delete_post_meta', array(self::class, 'beforeMetaDelete'), 10, 4);
		add_action('deleted_post_meta', array(self::class, 'afterMetaDelete'), 10, 4);
		add_action('add_option_' . SettingsSchema::OPTION_NAME, array(self::class, 'onSettingsAdded'), 30, 2);
		add_action('update_option_' . SettingsSchema::OPTION_NAME, array(self::class, 'onSettingsUpdated'), 30, 2);
		add_action('wla_inmo_rewrite_rules_applied', array(self::class, 'onRewriteRulesApplied'), 10, 1);
	}

	public static function onPostInserted(int $postId, $post, bool $update, $postBefore): void
	{
		unset($postBefore);
		if ($update || !self::isProperty($postId, $post)) {
			return;
		}

		Recorder::record(
			EventTypes::PROPERTY_CREATED,
			PostType::POST_TYPE,
			$postId,
			array('post_status' => isset($post->post_status) ? (string) $post->post_status : '')
		);
	}

	public static function onPostStatusChanged(string $newStatus, string $oldStatus, $post): void
	{
		if ($newStatus === $oldStatus || $oldStatus === 'new' || !is_object($post) || !isset($post->ID)) {
			return;
		}
		$postId = (int) $post->ID;
		if (!self::isProperty($postId, $post)) {
			return;
		}

		Recorder::record(
			EventTypes::PROPERTY_WP_STATUS_CHANGED,
			PostType::POST_TYPE,
			$postId,
			array('old' => $oldStatus, 'new' => $newStatus)
		);
	}

	public static function beforeMetaUpdate($metaId, int $objectId, string $metaKey, $metaValue): void
	{
		unset($metaId, $metaValue);
		if (!self::shouldObserveMeta($objectId, $metaKey)) {
			return;
		}
		self::$previousMeta[self::metaStateKey($objectId, $metaKey)] = get_post_meta($objectId, $metaKey, true);
	}

	public static function afterMetaUpdate($metaId, int $objectId, string $metaKey, $metaValue): void
	{
		unset($metaId);
		if (!self::shouldObserveMeta($objectId, $metaKey)) {
			return;
		}
		$key = self::metaStateKey($objectId, $metaKey);
		$old = array_key_exists($key, self::$previousMeta) ? self::$previousMeta[$key] : null;
		unset(self::$previousMeta[$key]);
		self::recordMetaChange($objectId, $metaKey, $old, $metaValue);
	}

	public static function afterMetaAdded($metaId, int $objectId, string $metaKey, $metaValue): void
	{
		unset($metaId);
		if (self::shouldObserveMeta($objectId, $metaKey)) {
			self::recordMetaChange($objectId, $metaKey, null, $metaValue);
		}
	}

	public static function beforeMetaDelete($metaIds, int $objectId, string $metaKey, $metaValue): void
	{
		unset($metaIds, $metaValue);
		if (!self::shouldObserveMeta($objectId, $metaKey)) {
			return;
		}
		self::$previousMeta[self::metaStateKey($objectId, $metaKey)] = get_post_meta($objectId, $metaKey, true);
	}

	public static function afterMetaDelete($metaIds, int $objectId, string $metaKey, $metaValue): void
	{
		unset($metaIds, $metaValue);
		if (!self::shouldObserveMeta($objectId, $metaKey)) {
			return;
		}
		$key = self::metaStateKey($objectId, $metaKey);
		$old = array_key_exists($key, self::$previousMeta) ? self::$previousMeta[$key] : null;
		unset(self::$previousMeta[$key]);
		self::recordMetaChange($objectId, $metaKey, $old, null);
	}

	public static function onSettingsAdded($optionName, $value): void
	{
		unset($optionName);
		self::recordSettingsDiff(SettingsSchema::defaults(), is_array($value) ? $value : array());
	}

	public static function onSettingsUpdated($oldValue, $newValue): void
	{
		self::recordSettingsDiff(
			is_array($oldValue) ? $oldValue : array(),
			is_array($newValue) ? $newValue : array()
		);
	}

	public static function onRewriteRulesApplied(string $propertyBase): void
	{
		Recorder::record(
			EventTypes::REWRITE_RULES_APPLIED,
			'settings',
			null,
			array('property_base' => $propertyBase)
		);
	}

	private static function recordSettingsDiff(array $oldValue, array $newValue): void
	{
		$old = SettingsSchema::sanitize($oldValue);
		$new = SettingsSchema::sanitize($newValue);
		$changedKeys = array();
		foreach ($new as $key => $value) {
			if (!array_key_exists($key, $old) || $old[$key] !== $value) {
				$changedKeys[] = $key;
			}
		}
		if ($changedKeys === array()) {
			return;
		}

		Recorder::record(EventTypes::SETTINGS_CHANGED, 'settings', null, array('keys' => $changedKeys));

		if (in_array('property_base', $changedKeys, true)) {
			Recorder::record(
				EventTypes::PROPERTY_BASE_CHANGED,
				'settings',
				null,
				array('old' => $old['property_base'] ?? '', 'new' => $new['property_base'] ?? '')
			);
		}
	}

	private static function recordMetaChange(int $postId, string $metaKey, $old, $new): void
	{
		if ($old === $new || (string) $old === (string) $new) {
			return;
		}

		$fields = self::observedMetaFields();
		$field = array_search($metaKey, $fields, true);
		if (!is_string($field)) {
			return;
		}

		if (in_array($field, array('price_clp', 'price_uf', 'price_usd'), true)) {
			$currency = get_post_meta($postId, (string) MetaSchema::metaKey('currency_primary'), true);
			Recorder::record(
				EventTypes::PROPERTY_PRICE_CHANGED,
				PostType::POST_TYPE,
				$postId,
				array('field' => $field, 'old' => $old, 'new' => $new, 'currency' => $currency)
			);
			return;
		}

		if ($field === 'status') {
			Recorder::record(
				EventTypes::PROPERTY_COMMERCIAL_STATUS_CHANGED,
				PostType::POST_TYPE,
				$postId,
				array('old' => $old, 'new' => $new)
			);
			return;
		}

		if ($field === 'featured') {
			Recorder::record(
				EventTypes::PROPERTY_FEATURED_CHANGED,
				PostType::POST_TYPE,
				$postId,
				array('old' => (bool) $old, 'new' => (bool) $new)
			);
		}
	}

	/** @return array<string,string> */
	private static function observedMetaFields(): array
	{
		$fields = array();
		foreach (array('price_clp', 'price_uf', 'price_usd', 'status', 'featured') as $field) {
			$metaKey = MetaSchema::metaKey($field);
			if (is_string($metaKey)) {
				$fields[$field] = $metaKey;
			}
		}
		return $fields;
	}

	private static function shouldObserveMeta(int $postId, string $metaKey): bool
	{
		return in_array($metaKey, self::observedMetaFields(), true) && self::isProperty($postId, get_post($postId));
	}

	private static function isProperty(int $postId, $post): bool
	{
		if ($postId < 1 || !is_object($post) || !isset($post->post_type) || $post->post_type !== PostType::POST_TYPE) {
			return false;
		}
		if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
			return false;
		}
		return true;
	}

	private static function metaStateKey(int $postId, string $metaKey): string
	{
		return $postId . ':' . $metaKey;
	}

	public static function resetForTests(): void
	{
		self::$previousMeta = array();
		self::$registered = false;
	}
}
