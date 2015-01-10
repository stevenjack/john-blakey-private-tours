<?php
/**
 * uninstall file for WP-FFPC; uninstall hook does not remove the databse options
 */

// exit if uninstall not called from WordPress
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/* get the worker file */
include_once ( 'wp-ffpc.php' );

/* run uninstall function */
$wp_ffpc->plugin_uninstall();

?>
