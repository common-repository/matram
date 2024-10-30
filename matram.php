<?php
/**
* Plugin Name: Matram
* Plugin URI: http://matram.io/
* Description: Compares screenshots of your WordPress site - Before and After updates.
* Version: 0.0.2
* Author: Revmakx
* Author URI: http://revmakx.com/
* Text Domain: matram
**/

define('MATRAM_PLUGIN_FILE',__FILE__);
define('MATRAM_PLUGIN_DIR',dirname(__FILE__));
if(strpos($_SERVER['SERVER_PROTOCOL'],'HTTPS') === false ){//$pos = strpos($mystring, $findme);
	$protocol = 'http://';
}else{
	$protocol = 'https://';
}

$path = dirname(dirname(dirname(MATRAM_PLUGIN_DIR)));

$updateInfo = array();

define('MATRAM_HOST',get_bloginfo('wpurl')."/");

function matram_get_plugin_data($file,$slugP){
	$section = file_get_contents($file);
	preg_match("/Version: ([0-9,.]*)/", $section, $version);
	preg_match("/Text Domain: ([a-z]*)/", $section, $slug);
	if( empty($slug) ){
		$slug = $slugP;
	}
	$versionInfo = array();
	$versionInfo[$slug[1]] = $version[1];

	return $versionInfo;
}

function matram_pre_init( $a1,$a2 ) {
	if( isset($a2['plugin'])){
		$file = WP_PLUGIN_DIR.'/'.$a2['plugin'];
		preg_match("/\/([a-z]*).php/", $file, $slugParam);
	}elseif( isset($a2['theme'])){
		$file = WP_CONTENT_DIR.'/themes/'.$a2['theme'].'/style.css';
		$slugParam = array('',$a2['theme']);
	}

	$TUI = matram_get_plugin_data($file,$slugParam);
	foreach ($TUI as $slug => $version) {
		if(!isset($updateInfo[$slug])){
			$updateInfo[$slug] = array('old'=>$version);
		}else{
			$updateInfo[$slug]['old'] = $version;
		}
	}
	@session_start();
	$_SESSION['mui'] = $updateInfo;
	@session_write_close();
}

function matram_init($a1, $a2, $a3){
	//$a = func_get_args();
	$type = '';
	if(isset($a2['plugin']) ){
		$type = 'plugin';
		$file = WP_PLUGIN_DIR.'/'.$a2['plugin'];
		preg_match("/\/([a-z]*).php/", $file, $slugParam);
	}elseif( isset($a2['theme']) ){
		$type = 'theme';
		$file = WP_CONTENT_DIR.'/themes/'.$a2['theme'].'/style.css';
		$slugParam = array('',$a2['theme']);
	}
	$TUI = matram_get_plugin_data($file,$slugParam);
	@session_start();
	$updateInfo = $_SESSION['mui'];
	unset($_SESSION['mui']);
	@session_write_close();
	foreach ($TUI as $slug => $version) {
		if(!isset($updateInfo[$slug])){
			$updateInfo[$slug] = array('new'=>$version);
		}else{
			$updateInfo[$slug]['new'] = $version;
		}
		$updateInfo[$slug]['type'] = $type;
	}

	if(isset($a2['type']) && !empty($a2['type'])){
		matram_send_message_to_server('getSiteShot',array('url'=>MATRAM_HOST,'updateInfo'=>$updateInfo));
	}else{
		@session_start();
		if(!isset($_SESSION['bulk_mui']) ){
			$_SESSION['bulk_mui'] = $updateInfo;
		}else{
			foreach ($updateInfo as $slug => $uInfo) {
				$_SESSION['bulk_mui'][$slug] = $uInfo;
			}
		}
		@session_write_close();
	}
}

function matram_init_core($a1){
	$newVersion = $a1;
	matram_send_message_to_server('getSiteShotCore',array('url'=>MATRAM_HOST,'updateInfo'=>$newVersion));
}

function matram_init_bulk($a1,$a2){
	@session_start();
	$updateInfo = $_SESSION['bulk_mui'];
	unset($_SESSION['bulk_mui']);
	@session_write_close();
	matram_send_message_to_server('getSiteShot',array('url'=>MATRAM_HOST,'updateInfo'=>$updateInfo));
}

add_filter('upgrader_pre_install','matram_pre_init',0,2);
add_filter('upgrader_post_install','matram_init', 5, 3);
add_filter('_core_updated_successfully','matram_init_core', 5, 1);

add_filter('update_bulk_plugins_complete_actions','matram_init_bulk', 5, 2);
add_filter('update_bulk_theme_complete_actions','matram_init_bulk', 5, 2);

register_activation_hook( __FILE__, 'matram_activation' );
 
function matram_activation() {
	$wp_version = get_bloginfo( 'version' );
	matram_send_message_to_server('activate',array('url'=>MATRAM_HOST,'version'=>$wp_version));
}


register_deactivation_hook( __FILE__, 'matram_deactivation' );
 
function matram_deactivation() {
	matram_send_message_to_server('deactivate',array('url'=>MATRAM_HOST));
}

function matram_send_message_to_server($action,$data){
	wp_remote_post( 'http://app.matram.io/matram-api.php', array(
			'method' => 'POST',
			'timeout' => 15,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(),
			'body' => array('action'=>$action,'data'=>$data),
			'cookies' => array()
		)
	);
}
