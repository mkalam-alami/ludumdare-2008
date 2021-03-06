<?php
defined('ABSPATH') or die("No.");
// - ----------------------------------------------------------------------------------------- - //
require_once "lib.php";				// Helper Functions //
require_once "wp_functions.php";	// WordPress Database Functions //
//require_once "ld_functions.php";	// General Database Functions //
// - ----------------------------------------------------------------------------------------- - //

// - ----------------------------------------------------------------------------------------- - //
global $ldvar;
global $ld_urlcache;
global $ld_table_prefix;
// - ----------------------------------------------------------------------------------------- - //
$ldvar = NULL;
$ld_urlcache = NULL;
$ld_table_prefix = "ld_";
// - ----------------------------------------------------------------------------------------- - //

// - ----------------------------------------------------------------------------------------- - //
global $ld_vars_table_name;
$ld_vars_table_name = $ld_table_prefix . "vars";
global $ld_urlcache_table_name;
$ld_urlcache_table_name = $ld_table_prefix . "urlcache";
// - ----------------------------------------------------------------------------------------- - //
global $ld_content_table_name;
$ld_content_table_name = $ld_table_prefix . "content";
// - ----------------------------------------------------------------------------------------- - //

// - ----------------------------------------------------------------------------------------- - //
// LD Variable Cache - APCU //
global $has_apcu;
if ( $has_apcu ) {
	function ld_get_vars_cache() {
		global $ld_vars_table_name;
		return apcu_fetch( $ld_vars_table_name );
	}
	function ld_put_vars_cache( $vars ) {
		global $ld_vars_table_name;
		apcu_store( $ld_vars_table_name, $vars );
	}
	function ld_get_urlcache_cache() {
		global $ld_urlcache_table_name;
		return apcu_fetch( $ld_urlcache_table_name );
	}
	function ld_put_urlcache_cache( $vars ) {
		global $ld_urlcache_table_name;
		apcu_store( $ld_urlcache_table_name, $vars );
	}
}
// LD Variable Cache - None //
else {
	function ld_get_vars_cache() { return NULL; }
	function ld_put_vars_cache( $vars ) { }	
	function ld_get_urlcache_cache() { return NULL; }
	function ld_put_urlcache_cache( $vars ) { }	
}
// LD Variable Cache //
// - ----------------------------------------------------------------------------------------- - //

// - ----------------------------------------------------------------------------------------- - //
function ld_get_vars() {
	global $ldvar;
	$ldvar = ld_get_vars_cache();
	if ( $ldvar ) {
		return;
	}	

	$ldvar = ld_get_vars_table();
	ld_put_vars_cache( $ldvar );
}
// - ----------------------------------------------------------------------------------------- - //
function ld_set_var_table( $key, $value ) {
	global $ld_vars_table_name;
	
	// store in database //
	lddb_query("
		INSERT INTO {$ld_vars_table_name} (
			name,
			value
		)
		VALUES (
			\"{$key}\",
			\"{$value}\"
		)
		ON DUPLICATE KEY UPDATE
			value=VALUES(value)
	;");
}	
// - ----------------------------------------------------------------------------------------- - //
function ld_set_var( $key, $value ) {
	ld_set_var_table($key,$value);

	global $ldvar;
	$ldvar[$key] = $value;
	ld_put_vars_cache( $ldvar );
}
// - ----------------------------------------------------------------------------------------- - //

// - ----------------------------------------------------------------------------------------- - //
function ld_init_vars() {
	global $ld_vars_table_name;
	if ( lddb_query( 
			"CREATE TABLE IF NOT EXISTS {$ld_vars_table_name} (
				name VARCHAR(32) NOT NULL UNIQUE,
				value TEXT NOT NULL
			) ENGINE=InnoDB;"
		) ) 
	{					
		// Populate with some default values //
		ld_set_var_table("event","root");
		ld_set_var_table("event_active","true");
	}
}
// - ----------------------------------------------------------------------------------------- - //
function ld_get_vars_table() {
	global $ld_vars_table_name;
	$vars = lddb_get( "SELECT * FROM {$ld_vars_table_name};" );
	$ret = [];
	foreach ( $vars as $var ) {
		$ret[$var['name']] = $var['value'];
	}
	return $ret;
}
// - ----------------------------------------------------------------------------------------- - //




// - ----------------------------------------------------------------------------------------- - //
function ld_get_urlcache() {
	global $ld_urlcache;
	$ld_urlcache = ld_get_urlcache_cache();
	if ( $ld_urlcache ) {
		return;
	}	

	$ld_urlcache = ld_get_urlcache_table();
	ld_put_urlcache_cache( $ld_urlcache );
}
// - ----------------------------------------------------------------------------------------- - //
function ld_set_urlcache_table( $url, $id ) {
	global $ld_urlcache_table_name;
	
	// store in database //
	lddb_query("
		INSERT INTO {$ld_urlcache_table_name} (
			`url`,
			`content_id`
		)
		VALUES (
			\"{$url}\",
			\"{$id}\"
		)
		ON DUPLICATE KEY UPDATE
			`content_id`=VALUES(content_id)
	;");
}	
// - ----------------------------------------------------------------------------------------- - //
function ld_set_urlcache( $url, $id ) {
	ld_set_urlcache_table($url,$id);

	global $ld_urlcache;
	$ld_urlcache[$url] = $id;
	ld_put_urlcache_cache( $ld_urlcache );
}
// - ----------------------------------------------------------------------------------------- - //

// - ----------------------------------------------------------------------------------------- - //
function ld_init_urlcache() {
	global $ld_urlcache_table_name;
	// NOTE: InnoDB has a VarChar limit of 768 chars, or UTF-8 255 //
	if ( lddb_query( 
			"CREATE TABLE IF NOT EXISTS {$ld_urlcache_table_name} (
				url VARCHAR(255) NOT NULL UNIQUE,
				content_id BIGINT UNSIGNED NOT NULL
			) ENGINE=InnoDB;"
		) )
	{
		ld_set_urlcache_table("/",1);
	}
	
	ld_get_urlcache();
}
// - ----------------------------------------------------------------------------------------- - //
function ld_get_urlcache_table() {
	global $ld_urlcache_table_name;
	$urls = lddb_get( "SELECT * FROM {$ld_urlcache_table_name};" );
	$ret = [];
	foreach ( $urls as $url ) {
		$ret[$url['url']] = $url['content_id'];
	}
	return $ret;
}
// - ----------------------------------------------------------------------------------------- - //


// - ----------------------------------------------------------------------------------------- - //
function ld_init_content() {
	global $ld_content_table_name;

	if ( lddb_query( 
			"CREATE TABLE IF NOT EXISTS {$ld_content_table_name} (
				ID SERIAL,
				parent_id BIGINT UNSIGNED NOT NULL,
				owner_id BIGINT UNSIGNED NOT NULL,
				type VARCHAR(16) NOT NULL,
				slug VARCHAR(64) NOT NULL,
				updated TIMESTAMP NOT NULL,
				published TIMESTAMP NOT NULL,
				
				title TEXT NOT NULL,
				body TEXT NOT NULL
				
			) ENGINE=InnoDB;"
		) )
	{
		// ID - Unique ID of this object.
		// parent_id - What I belong to. 
		// owner_id - User Whom I belong to.
		// type - TYPES SHOULD BE 10 CHARACTERS OR LESS (so we can append 'Draft-')
		// slug - nice name
		// published - publish date (or 000000)
		// updated - last time it was changed

//				title TEXT NOT NULL,
//				body TEXT NOT NULL

	}
}
// - ----------------------------------------------------------------------------------------- - //

?>