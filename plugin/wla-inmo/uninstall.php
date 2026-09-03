<?php
/**
 * WLA Inmo uninstall policy.
 *
 * Data is retained by default. Deleting the plugin files must not silently
 * remove properties, settings, media, leads, logs or future index tables.
 * A future destructive purge, if implemented, must be an explicit opt-in
 * operation with its own capability, confirmation and audit trail.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Intentionally no destructive cleanup.
