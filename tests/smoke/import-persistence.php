<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/SourceKey.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/BatchStatus.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/BatchSchema.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/IdentitySchema.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/IdentityMeta.php';

use WLA\Inmo\Import\BatchSchema;
use WLA\Inmo\Import\IdentityMeta;
use WLA\Inmo\Import\IdentitySchema;

final class WlaImportPersistenceSmokeDatabase
{
	public string $prefix = 'wp_';

	public function get_charset_collate(): string
	{
		return 'DEFAULT CHARACTER SET utf8mb4';
	}
}

function wlaPersistenceExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$database = new WlaImportPersistenceSmokeDatabase();
wlaPersistenceExpect(IdentityMeta::sanitize('Portal Á') === 'portal_a', 'Source key normalization mismatch.');
wlaPersistenceExpect(
	str_contains(IdentitySchema::sql($database), 'UNIQUE KEY external_identity (source_key,external_id)'),
	'External identity unique index missing.'
);
wlaPersistenceExpect(
	str_contains(BatchSchema::sql($database), 'revision bigint(20) unsigned'),
	'Batch optimistic lock revision missing.'
);
wlaPersistenceExpect(
	str_contains(BatchSchema::sql($database), 'cursor_row int(10) unsigned'),
	'Batch resume cursor missing.'
);

echo "WLA Inmo import persistence smoke tests passed.\n";
