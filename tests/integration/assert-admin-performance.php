<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Activity\Repository as ActivityRepository;
use WLA\Inmo\Dashboard\Snapshot as DashboardSnapshot;
use WLA\Inmo\Properties\PostType;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

global $wpdb;

$insertSyntheticProperties = static function (int $count, int $offset) use ($wpdb, $fail): void {
	$batchSize = 250;
	$now = current_time('mysql');
	$nowGmt = current_time('mysql', true);

	for ($start = 0; $start < $count; $start += $batchSize) {
		$current = min($batchSize, $count - $start);
		$rows = array();
		$args = array();

		for ($index = 0; $index < $current; $index++) {
			$number = $offset + $start + $index + 1;
			$rows[] = '(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s,%d,%s,%s,%d)';
			array_push(
				$args,
				1,
				$now,
				$nowGmt,
				'',
				'Performance Property ' . $number,
				'',
				'draft',
				'closed',
				'closed',
				'',
				'performance-property-' . $number,
				'',
				'',
				$now,
				$nowGmt,
				'',
				0,
				'',
				0,
				PostType::POST_TYPE,
				'',
				0
			);
		}

		$sql = 'INSERT INTO ' . $wpdb->posts . ' '
			. '(post_author,post_date,post_date_gmt,post_content,post_title,post_excerpt,post_status,comment_status,ping_status,post_password,post_name,to_ping,pinged,post_modified,post_modified_gmt,post_content_filtered,post_parent,guid,menu_order,post_type,post_mime_type,comment_count) VALUES '
			. implode(',', $rows);
		$prepared = $wpdb->prepare($sql, $args);
		$result = $wpdb->query($prepared);
		if ($result === false) {
			$fail('Unable to insert synthetic administration performance catalogue.');
		}
	}
};

$baselineCount = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft','pending','private','future')",
		PostType::POST_TYPE
	)
);

$milestones = array(100, 1000, 5000);
$inserted = 0;

foreach ($milestones as $target) {
	$toInsert = $target - $inserted;
	$insertSyntheticProperties($toInsert, $inserted);
	$inserted = $target;

	$beforeQueries = (int) $wpdb->num_queries;
	$startedAt = microtime(true);
	$snapshot = (new DashboardSnapshot())->build(false);
	$elapsed = microtime(true) - $startedAt;
	$queryDelta = (int) $wpdb->num_queries - $beforeQueries;

	$expect($queryDelta <= 5, "Dashboard exceeded 5-query budget at {$target} synthetic properties: {$queryDelta}.");
	$expect($elapsed < 5.0, sprintf('Dashboard exceeded conservative 5s CI budget at %d synthetic properties: %.3fs.', $target, $elapsed));
	$expect(
		(int) ($snapshot['properties']['total'] ?? 0) === $baselineCount + $target,
		"Dashboard total is incorrect at {$target} synthetic properties."
	);

	fwrite(
		STDOUT,
		sprintf(
			"Dashboard benchmark %d synthetic: %d queries, %.4fs\n",
			$target,
			$queryDelta,
			$elapsed
		)
	);
}

$beforeQueries = (int) $wpdb->num_queries;
$startedAt = microtime(true);
$listQuery = new WP_Query(
	array(
		'post_type' => PostType::POST_TYPE,
		'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
		'posts_per_page' => 20,
		'paged' => 1,
		'orderby' => 'modified',
		'order' => 'DESC',
		'fields' => 'ids',
	)
);
$listElapsed = microtime(true) - $startedAt;
$listQueryDelta = (int) $wpdb->num_queries - $beforeQueries;

$expect(count($listQuery->posts) <= 20, 'Administration list benchmark returned an unbounded first page.');
$expect($listQueryDelta <= 4, "Administration list exceeded 4-query reference budget: {$listQueryDelta}.");
$expect($listElapsed < 5.0, sprintf('Administration list exceeded conservative 5s CI budget: %.3fs.', $listElapsed));

fwrite(
	STDOUT,
	sprintf("Property list benchmark 5k: %d queries, %.4fs\n", $listQueryDelta, $listElapsed)
);

$beforeQueries = (int) $wpdb->num_queries;
$startedAt = microtime(true);
$activity = ActivityRepository::paginate(array(), 1, 30);
$activityElapsed = microtime(true) - $startedAt;
$activityQueryDelta = (int) $wpdb->num_queries - $beforeQueries;

$expect($activityQueryDelta <= 2, "Activity pagination exceeded 2-query budget: {$activityQueryDelta}.");
$expect(count($activity['items']) <= 30, 'Activity pagination returned more than one bounded page.');
$expect($activityElapsed < 5.0, sprintf('Activity pagination exceeded conservative 5s CI budget: %.3fs.', $activityElapsed));

fwrite(
	STDOUT,
	sprintf("Activity benchmark: %d queries, %.4fs\n", $activityQueryDelta, $activityElapsed)
);

echo "WLA Inmo administration performance integration tests passed.\n";
