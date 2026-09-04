<?php

declare(strict_types=1);

$GLOBALS['wla_role_options'] = array();
$GLOBALS['wla_role_option_updates'] = 0;
$GLOBALS['wla_roles'] = array();

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}

final class WlaRoleSmokeRole
{
	/** @var array<string,bool> */
	public array $capabilities = array();

	/** @param array<string,bool> $capabilities */
	public function __construct(array $capabilities = array())
	{
		$this->capabilities = $capabilities;
	}

	public function add_cap($capability, $grant = true): void
	{
		$this->capabilities[(string) $capability] = (bool) $grant;
	}

	public function remove_cap($capability): void
	{
		unset($this->capabilities[(string) $capability]);
	}

	public function has_cap($capability): bool
	{
		return !empty($this->capabilities[(string) $capability]);
	}
}

$GLOBALS['wla_roles']['administrator'] = new WlaRoleSmokeRole(
	array(
		'read'           => true,
		'manage_options' => true,
	)
);

if (!function_exists('get_role')) {
	function get_role($role)
	{
		return $GLOBALS['wla_roles'][(string) $role] ?? null;
	}
}

if (!function_exists('add_role')) {
	function add_role($role, $displayName, $capabilities = array())
	{
		unset($displayName);
		$role = (string) $role;
		if (isset($GLOBALS['wla_roles'][$role])) {
			return null;
		}

		$GLOBALS['wla_roles'][$role] = new WlaRoleSmokeRole($capabilities);
		return $GLOBALS['wla_roles'][$role];
	}
}

if (!function_exists('get_option')) {
	function get_option($key, $default = false)
	{
		return $GLOBALS['wla_role_options'][(string) $key] ?? $default;
	}
}

if (!function_exists('update_option')) {
	function update_option($key, $value, $autoload = null)
	{
		unset($autoload);
		$GLOBALS['wla_role_options'][(string) $key] = $value;
		$GLOBALS['wla_role_option_updates']++;
		return true;
	}
}

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/Capabilities.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Taxonomies/Capabilities.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Access/Capabilities.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Access/RoleMatrix.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Access/RoleManager.php';

use WLA\Inmo\Access\Capabilities as ModuleCapabilities;
use WLA\Inmo\Access\RoleManager;
use WLA\Inmo\Access\RoleMatrix;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;
use WLA\Inmo\Taxonomies\Capabilities as TaxonomyCapabilities;

function wlaRoleExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$metaCaps = PropertyCapabilities::meta();
$primitiveCaps = PropertyCapabilities::primitive();
wlaRoleExpect(array_intersect($metaCaps, $primitiveCaps) === array(), 'Property meta and primitive capabilities must be disjoint.');
wlaRoleExpect(!in_array(PropertyCapabilities::EDIT_POST, $primitiveCaps, true), 'edit_wla_property meta cap must not be assigned directly to roles.');
wlaRoleExpect(in_array(PropertyCapabilities::EDIT_POSTS, $primitiveCaps, true), 'Plural property edit capability must be primitive.');

$managed = RoleMatrix::managedCapabilities();
wlaRoleExpect(!in_array('manage_options', $managed, true), 'WLA managed capabilities must never alias manage_options.');
wlaRoleExpect(!in_array('edit_posts', $managed, true), 'WLA managed capabilities must never alias generic edit_posts.');
wlaRoleExpect(count($managed) === count(array_unique($managed)), 'Managed capability list must be unique.');

RoleManager::install();

foreach (array(RoleMatrix::ROLE_MANAGER, RoleMatrix::ROLE_EDITOR, RoleMatrix::ROLE_LEAD_MANAGER) as $roleSlug) {
	wlaRoleExpect(get_role($roleSlug) instanceof WlaRoleSmokeRole, "$roleSlug must be created on install.");
}

$administrator = get_role('administrator');
wlaRoleExpect($administrator instanceof WlaRoleSmokeRole, 'Administrator role fixture missing.');
foreach (RoleMatrix::administratorCapabilities() as $capability) {
	wlaRoleExpect($administrator->has_cap($capability), "Administrator must receive $capability.");
}
wlaRoleExpect($administrator->has_cap('manage_options'), 'Plugin must preserve Administrator manage_options capability.');

$manager = get_role(RoleMatrix::ROLE_MANAGER);
wlaRoleExpect($manager instanceof WlaRoleSmokeRole, 'Manager role missing.');
foreach (PropertyCapabilities::primitive() as $capability) {
	wlaRoleExpect($manager->has_cap($capability), "Manager must receive property capability $capability.");
}
foreach (TaxonomyCapabilities::all() as $capability) {
	wlaRoleExpect($manager->has_cap($capability), "Manager must receive taxonomy capability $capability.");
}
wlaRoleExpect($manager->has_cap(ModuleCapabilities::IMPORT_PROPERTIES), 'Manager must be able to import properties.');
wlaRoleExpect($manager->has_cap(ModuleCapabilities::MANAGE_SETTINGS), 'Manager must manage allowed real-estate settings.');
wlaRoleExpect(!$manager->has_cap(ModuleCapabilities::MANAGE_TOOLS), 'Technical tools must remain reserved from manager by default.');
wlaRoleExpect(!$manager->has_cap('manage_options'), 'Manager must not become a WordPress administrator.');
wlaRoleExpect($manager->has_cap('upload_files'), 'Manager needs media upload permission.');

$editor = get_role(RoleMatrix::ROLE_EDITOR);
wlaRoleExpect($editor instanceof WlaRoleSmokeRole, 'Property editor role missing.');
foreach (array(PropertyCapabilities::EDIT_POSTS, PropertyCapabilities::PUBLISH_POSTS, PropertyCapabilities::EDIT_PUBLISHED_POSTS, PropertyCapabilities::DELETE_POSTS) as $capability) {
	wlaRoleExpect($editor->has_cap($capability), "Editor must receive $capability.");
}
wlaRoleExpect($editor->has_cap(TaxonomyCapabilities::ASSIGN_TERMS), 'Editor must be able to assign existing classifications.');
wlaRoleExpect(!$editor->has_cap(TaxonomyCapabilities::MANAGE_TERMS), 'Editor must not manage taxonomy structure.');
wlaRoleExpect(!$editor->has_cap(PropertyCapabilities::EDIT_OTHERS_POSTS), 'Editor must not edit other authors properties.');
wlaRoleExpect(!$editor->has_cap(PropertyCapabilities::DELETE_OTHERS_POSTS), 'Editor must not delete other authors properties.');
foreach (array(ModuleCapabilities::IMPORT_PROPERTIES, ModuleCapabilities::EXPORT_PROPERTIES, ModuleCapabilities::VIEW_LEADS, ModuleCapabilities::MANAGE_SEO, ModuleCapabilities::MANAGE_SETTINGS, ModuleCapabilities::MANAGE_TOOLS) as $forbidden) {
	wlaRoleExpect(!$editor->has_cap($forbidden), "Editor must not receive $forbidden.");
}
wlaRoleExpect($editor->has_cap('upload_files'), 'Editor needs media upload permission.');
wlaRoleExpect(!$editor->has_cap('manage_options'), 'Editor must not receive manage_options.');

$leadManager = get_role(RoleMatrix::ROLE_LEAD_MANAGER);
wlaRoleExpect($leadManager instanceof WlaRoleSmokeRole, 'Lead manager role missing.');
foreach (array(ModuleCapabilities::VIEW_LEADS, ModuleCapabilities::EDIT_LEADS, ModuleCapabilities::MANAGE_LEADS) as $capability) {
	wlaRoleExpect($leadManager->has_cap($capability), "Lead manager must receive $capability.");
}
foreach (PropertyCapabilities::primitive() as $propertyCapability) {
	wlaRoleExpect(!$leadManager->has_cap($propertyCapability), "Lead manager must not receive property capability $propertyCapability.");
}
wlaRoleExpect(!$leadManager->has_cap(TaxonomyCapabilities::ASSIGN_TERMS), 'Lead manager must not assign property terms.');
wlaRoleExpect(!$leadManager->has_cap('upload_files'), 'Lead manager does not need media uploads.');
wlaRoleExpect(!$leadManager->has_cap('manage_options'), 'Lead manager must not receive manage_options.');

wlaRoleExpect(get_option(RoleManager::VERSION_OPTION, '0') === RoleManager::VERSION, 'Role schema version must be persisted.');
$updatesAfterInstall = $GLOBALS['wla_role_option_updates'];
RoleManager::maybeUpgrade();
wlaRoleExpect($GLOBALS['wla_role_option_updates'] === $updatesAfterInstall, 'Current role schema must not reconcile on every admin request.');

// Prove reconciliation removes a stale plugin-managed capability from a limited role.
$editor->add_cap(ModuleCapabilities::MANAGE_SEO, true);
wlaRoleExpect($editor->has_cap(ModuleCapabilities::MANAGE_SEO), 'Test precondition: stale capability was not added.');
$GLOBALS['wla_role_options'][RoleManager::VERSION_OPTION] = '0';
RoleManager::maybeUpgrade();
wlaRoleExpect(!$editor->has_cap(ModuleCapabilities::MANAGE_SEO), 'Role schema upgrade must remove stale managed caps from custom roles.');

// Running install again must remain deterministic/idempotent.
$before = array();
foreach ($GLOBALS['wla_roles'] as $slug => $role) {
	$before[$slug] = $role->capabilities;
	ksort($before[$slug]);
}
RoleManager::install();
$after = array();
foreach ($GLOBALS['wla_roles'] as $slug => $role) {
	$after[$slug] = $role->capabilities;
	ksort($after[$slug]);
}
wlaRoleExpect($before === $after, 'Repeated role installation must be idempotent.');

echo "WLA Inmo access role smoke tests passed.\n";
