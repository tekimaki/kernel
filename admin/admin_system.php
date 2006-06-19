<?php
// $Header: /cvsroot/bitweaver/_bit_kernel/admin/admin_system.php,v 1.5 2006/06/19 20:28:43 squareing Exp $

// Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

require_once( '../../bit_setup_inc.php' );

$gBitSystem->verifyPermission( 'p_admin' );
$feedback = array();

$diskUsage = array(
	'templates_c' => array(
		'url' => TEMP_PKG_URL.'templates_c',
		'title' => tra( 'Templates' ),
	),
	'lang' => array(
		'url' => TEMP_PKG_URL.'lang',
		'title' => tra( 'Language Files' ),
	),
	'modules' => array(
		'url' => TEMP_PKG_URL.'modules/cache',
		'title' => tra( 'Modules' ),
		'subdir' => $bitdomain,
	),
	'cache' => array(
		'url' => TEMP_PKG_URL.'cache',
		'title' => tra( 'System' ),
		'subdir' => $bitdomain,
	),
	'liberty' => array(
		'url' => LibertyContent::getCacheBaseUrl(),
		'title' => tra( 'Liberty' ),
	),
);

if( !empty( $_GET['prune'] ) ) {
	foreach( $diskUsage as $key => $item ) {
		if( $_GET['prune'] == $key || $_GET['prune'] == 'all' ) {
			if( unlink_r( BIT_ROOT_PATH.$item['url'].( !empty( $item['subdir'] ) ? '/'.$item['subdir'] : '' ) ) ) {
				$feedback['success'] = tra( 'The cache was successfully cleared.' );
			} elseif( is_dir( BIT_ROOT_PATH.$item['url'].( !empty( $item['subdir'] ) ? '/'.$item['subdir'] : '' ) ) ) {
				$feedback['error'] = tra( 'There was a problem clearing out the cache.' );
			}
		}
	}
}

if( !empty( $_GET['compiletemplates'] ) ) {
	cache_templates( BIT_ROOT_PATH, $gBitLanguage->getLanguage(), $_GET['compiletemplates'] );
}

foreach( $diskUsage as $key => $item ) {
	$diskUsage[$key]['du'] = du( BIT_ROOT_PATH.$item['url'] );
}
$gBitSmarty->assign( 'diskUsage', $diskUsage );

$languages = array();
$languages = $gBitLanguage->listLanguages();
ksort( $languages );

$templates = array();
$langdir = TEMP_PKG_PATH."templates_c/".$gBitSystem->getConfig('style')."/";
foreach( array_keys( $languages ) as $clang ) {
	if( is_dir( $langdir.$clang ) ) {
		$templates[$clang] = array(
			'url'   => TEMP_PKG_URL."templates_c/".$gBitSystem->getConfig( 'style' )."/",
			'title' => $languages[$clang]['full_name'],
			'du'    => du( $langdir.$clang ),
		);
	} else {
		$templates[$clang] = array(
			'url'   => TEMP_PKG_URL."templates_c/".$gBitSystem->getConfig( 'style' )."/",
			'title' => $languages[$clang]['full_name'],
			'du'    => array(
				"count" => 0,
				"size" => 0,
			),
		);
	}
}
$gBitSmarty->assign( 'templates', $templates );
$gBitSmarty->assign( 'feedback', $feedback );

$gBitSystem->display( 'bitpackage:kernel/admin_system.tpl', tra( "System Cache" ) );


// ----------------------- Functions ----------------------- //
function du( $path ) {
	$size = $count = 0;
	if( !$path or !is_dir( $path ) ) {
		$ret['size'] = $size;
		$ret['count'] = $count;
		return $ret;
	}
	$all = opendir( $path );
	while( $file = readdir( $all ) ) {
		if( is_dir( $path.'/'.$file ) and $file <> ".." and $file <> "." and $file <> "CVS" ) {
			$du = du( $path.'/'.$file );
			$size += $du['size'];
			$count += $du['count'];
			unset( $file );
		} elseif( !is_dir( $path.'/'.$file ) ) {
			$stats = stat( $path.'/'.$file );
			$size += $stats['size'];
			$count++;
			unset( $file );
		}
	}
	closedir( $all );
	unset( $all );
	$ret['size'] = $size;
	$ret['count'] = $count;
	return $ret;
}

function cache_templates( $path, $oldlang, $newlang ) {
	global $gBitLanguage, $gBitSmarty;

	if( !$path or !is_dir( $path ) ) {
		return 0;
	}

	if( $dir = opendir( $path ) ) {
		while( FALSE !== ( $file = readdir( $dir ) ) ) {
			$a = explode( ".", $file );
			$ext = strtolower( end( $a ) );
			if( substr( $file, 0, 1 ) == "." or $file == 'CVS' ) {
				continue;
			}

			if( is_dir( $path."/".$file ) ) {
				cache_templates( $path."/".$file, $oldlang, $newlang );
			} else {
				if( $ext == "tpl" ) {
					$file = str_replace( '//', '/', $path."/".$file );
					$gBitLanguage->setLanguage( $newlang );
					$gBitSmarty->verifyCompileDir();
					$comppath = $gBitSmarty->_get_compile_path( $file );
					$gBitLanguage->setLanguage( $oldlang );
					// ignore files in sudirectories of templates/ - will break stuff as in the case of phpbb
					if( preg_match( "!/templates/\w*\.tpl!i", $file ) && !$gBitSmarty->_is_compiled( $file, $comppath ) ) {
						$gBitSmarty->_compile_resource( $file, $comppath );
					}
				}
			}
		}
		closedir( $dir );
	}
}
?>
