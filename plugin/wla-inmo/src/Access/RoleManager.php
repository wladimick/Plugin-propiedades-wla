<?php

namespace WLA\Inmo\Access;

final class RoleManager
{
	public const VERSION = '1';
	public const VERSION_OPTION = 'wla_inmo_roles_version';

	public static function install(): void
	{
		self::installCustomRoles();
		self::grantAdministratorCapabilities();
		update_option(self::VERSION_OPTION, self::VERSION, false);
	}

	public static function maybeUpgrade(): void
	{
		$current = (string) get_option(self::VERSION_OPTION, '0');

		if ($current === self::VERSION) {
			return;
		}

		self::install();
	}

	private static function installCustomRoles(): void
	{
		foreach (RoleMatrix::definitions() as $roleSlug => $definition) {
			$desired = array_values(array_unique($definition['capabilities']));
			$role = get_role($roleSlug);

			if ($role === null) {
				$role = add_role(
					$roleSlug,
					(string) $definition['label'],
					array_fill_keys($desired, true)
				);
			}

			if ($role === null || !is_object($role)) {
				continue;
			}

			self::reconcileCustomRole($role, $desired);
		}
	}

	/**
	 * @param object            $role WordPress role object.
	 * @param array<int,string> $desired Desired capabilities.
	 */
	private static function reconcileCustomRole($role, array $desired): void
	{
		$managed = RoleMatrix::managedCapabilities();

		foreach ($managed as $capability) {
			if (in_array($capability, $desired, true)) {
				$role->add_cap($capability, true);
			} else {
				$role->remove_cap($capability);
			}
		}

		foreach (array('read', 'upload_files') as $coreCapability) {
			if (in_array($coreCapability, $desired, true)) {
				$role->add_cap($coreCapability, true);
			} elseif ($coreCapability === 'upload_files') {
				$role->remove_cap($coreCapability);
			}
		}

		// Plugin-owned roles must never become full WordPress administrators.
		$role->remove_cap('manage_options');
	}

	private static function grantAdministratorCapabilities(): void
	{
		$administrator = get_role('administrator');

		if ($administrator === null || !is_object($administrator)) {
			return;
		}

		foreach (RoleMatrix::administratorCapabilities() as $capability) {
			$administrator->add_cap($capability, true);
		}
	}
}
