<?php

require_once __DIR__ . '/../vendor/autoload.php';


$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}


require_once $_tests_dir . '/functions.php';

function _manually_load_plugin() {
        require dirname( __FILE__ ) . '/../wp-push.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/bootstrap.php';


