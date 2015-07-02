<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = __DIR__ .  '/../wordpress-tests-lib';
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once $_tests_dir . '/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../wp_push.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/bootstrap.php';

