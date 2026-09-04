<?php

namespace WLA\Inmo\Activity;

final class Recorder
{
	/** @return int|false */
	public static function record(
		string $eventType,
		string $objectType,
		?int $objectId = null,
		array $context = array(),
		?int $actorUserId = null
	) {
		$eventType = strtolower(trim($eventType));
		$eventType = preg_replace('/[^a-z0-9._-]+/', '', $eventType) ?? '';
		$objectType = sanitize_key($objectType);

		if (!EventTypes::isAllowed($eventType) || $objectType === '') {
			return false;
		}

		$objectId = $objectId !== null ? absint($objectId) : null;
		if ($objectId === 0) {
			$objectId = null;
		}

		if ($actorUserId === null && function_exists('get_current_user_id')) {
			$current = (int) get_current_user_id();
			$actorUserId = $current > 0 ? $current : null;
		} elseif ($actorUserId !== null) {
			$actorUserId = absint($actorUserId);
			if ($actorUserId === 0) {
				$actorUserId = null;
			}
		}

		$context = EventTypes::sanitizeContext($eventType, $context);
		$event = array(
			'event_type' => $eventType,
			'object_type' => $objectType,
			'object_id' => $objectId,
			'actor_user_id' => $actorUserId,
			'summary' => $eventType,
			'context' => $context,
			'created_at' => gmdate('Y-m-d H:i:s'),
		);

		$id = Repository::insert($event);
		if ($id === false) {
			return false;
		}

		/**
		 * Fires after a WLA Inmo activity event has been persisted.
		 *
		 * @param int                 $id Event ID.
		 * @param array<string,mixed> $event Sanitized event payload.
		 */
		do_action('wla_inmo_activity_recorded', $id, $event);

		return $id;
	}
}
