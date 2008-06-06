<?php
/**
 * Smarty Library Inteface Class
 *
 * @package Smarty
 * @version $Header: /cvsroot/bitweaver/_bit_kernel/BitSmarty.php,v 1.19 2008/06/06 16:18:09 squareing Exp $
 *
 * Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * All Rights Reserved. See copyright.txt for details and a complete list of authors.
 * Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.
 */

/**
 * required setup
 */
if( file_exists( UTIL_PKG_PATH.'smarty/libs/Smarty.class.php' )) {
	// set SMARTY_DIR that we have the absolute path
	define( 'SMARTY_DIR', UTIL_PKG_PATH.'smarty/libs/' );
	// If we have smarty in our kernel, use that.
	$smartyIncFile = SMARTY_DIR . 'Smarty.class.php';
} else {
	// assume it is in php's global include_path
	// don't set SMARTY_DIR if we are not using the bw copy
	$smartyIncFile = 'Smarty.class.php';
}

require_once( $smartyIncFile );

/**
 * PermissionCheck
 *
 * @package kernel
 */
class PermissionCheck {
	function check( $perm ) {
		global $gBitUser;
		return $gBitUser->hasPermission( $perm );
	}
}

/**
 * BitSmarty
 *
 * @package kernel
 */
class BitSmarty extends Smarty {
	/**
	 * BitSmarty initiation
	 * 
	 * @access public
	 * @return void
	 */
	function BitSmarty() {
		global $smarty_force_compile;
		Smarty::Smarty();
		$this->mCompileRsrc = NULL;
		$this->config_dir = "configs/";
		// $this->caching = FALSE;
		$this->force_compile = $smarty_force_compile;
		$this->assign( 'app_name', 'bitweaver' );
		$this->plugins_dir = array_merge( array( KERNEL_PKG_PATH . "smarty_bit" ), $this->plugins_dir );
		$this->register_prefilter( "add_link_ticket" );

		global $permCheck;
		$permCheck = new PermissionCheck();
		$this->register_object( 'perm', $permCheck, array(), TRUE, array( 'autoComplete' ));
		$this->assign_by_ref( 'perm', $permCheck );
	}

	/**
	 * override some smarty functions to bend them to our will
	 */
	function _smarty_include( $pParams ) {
		if( defined( 'TEMPLATE_DEBUG' ) && TEMPLATE_DEBUG == TRUE ) {
			echo "\n<!-- - - - {$pParams['smarty_include_tpl_file']} - - - -->\n";
		}
		$this->includeSiblingPhp( $pParams['smarty_include_tpl_file'] );
		return parent::_smarty_include( $pParams );
	}

	function _compile_resource( $pResourceName, $pCompilePath ) {
		// this is used when auto-storing untranslated master strings
		$this->mCompileRsrc = $pResourceName;
		return parent::_compile_resource( $pResourceName, $pCompilePath );
	}

	function _fetch_resource_info( &$pParams ) {
		if( empty( $pParams['resource_name'] )) {
			return FALSE;
		} else {
			return parent::_fetch_resource_info( $pParams );
		}
	}

	function fetch( $pTplFile, $pCacheId = NULL, $pCompileId = NULL, $pDisplay = FALSE ) {
		global $gBitSystem;
		$this->verifyCompileDir();
		if( strpos( $pTplFile, ':' )) {
			list( $resource, $location ) = split( ':', $pTplFile );
			if( $resource == 'bitpackage' ) {
				list( $package, $template ) = split( '/', $location );
				// exclude temp, as it contains nexus menus
				if( !$gBitSystem->isPackageActive( $package ) && $package != 'temp' ) {
					return '';
				}
			}
		}

		// the PHP sibling file needs to be included here, before the fetch so caching works properly
		$this->includeSiblingPhp( $pTplFile );
		if( defined( 'TEMPLATE_DEBUG' ) && TEMPLATE_DEBUG == TRUE ) {
			echo "\n<!-- - - - {$pTplFile} - - - -->\n";
		}
		return parent::fetch( $pTplFile, $pCacheId, $pCompileId, $pDisplay );
	}

	/**
	 * THE method to invoke if you want to be sure a tpl's sibling php file gets included if it exists. This
	 * should not need to be invoked from anywhere except within this class
	 *
	 * @param string $pRsrc resource of the template, should be of the form "bitpackage:<packagename>/<templatename>"
	 * @return TRUE if a sibling php file was included
	 * @access private
	 */
	function includeSiblingPhp( $pRsrc ) {
		$ret = FALSE;
		if( strpos( $pRsrc, ':' )) {
			list( $resource, $location ) = split( ':', $pRsrc );
			if( $resource == 'bitpackage' ) {
				list( $package, $template ) = split( '/', $location );
				// print "( $resource, $location )  ( $package, $template )<br/>";
				$subdir = preg_match( '/mod_/', $template ) ? 'modules' : 'templates';
				if( preg_match('/mod_/', $template ) || preg_match( '/center_/', $template )) {
					global $gBitSystem;
					$path = $gBitSystem->mPackages[$package]['path'];
					$modPhpFile = str_replace( '.tpl', '.php', "$path$subdir/$template" );
					if( file_exists( $modPhpFile )) {
						global $gBitSmarty, $gBitSystem, $gBitUser, $gQueryUserId, $moduleParams;
						// Module Params were passed in from the template, like kernel/dynamic.tpl
						$moduleParams = $this->get_template_vars( 'moduleParams' );
						include( $modPhpFile );
						$ret = TRUE;
					}
				}
			}
		}
	}

	/**
	 * verifyCompileDir 
	 * 
	 * @access public
	 * @return void
	 */
	function verifyCompileDir() {
		global $gBitSystem, $gBitLanguage, $bitdomain, $gBitThemes;
		if( !defined( "TEMP_PKG_PATH" )) {
			$temp = BIT_ROOT_PATH . "temp/";
		} else {
			$temp = TEMP_PKG_PATH;
		}
		$style = $gBitThemes->getStyle();
		$endPath = "$bitdomain/$style/".$gBitLanguage->mLanguage;

		// Compile directory
		$compDir = $temp . "templates_c/$endPath";
		$compDir = str_replace( '//', '/', $compDir );
		$compDir = clean_file_path( $compDir );
		mkdir_p( $compDir );
		$this->compile_dir = $compDir;

		// Cache directory
		$cacheDir = $temp . "cache/$endPath";
		$cacheDir = str_replace( '//', '/', $cacheDir );
		$cacheDir = clean_file_path( $cacheDir );
		mkdir_p( $cacheDir );
		$this->cache_dir = $cacheDir;
	}
}

/**
 * add_link_ticket This will insert a ticket on all template URL's that have GET parameters.
 * 
 * @param array $pTplSource source of template
 * @access public
 * @return ammended template source
 */
function add_link_ticket( $pTplSource ) {
	global $gBitUser;

	if( is_object( $gBitUser ) && $gBitUser->isValid() ) {
		$from = '#href="(.*PKG_URL.*php)\?(.*)&(.*)"#i';
		$to = 'href="\\1?\\2&amp;tk={$gBitUser->mTicket}&\\3"';
		$pTplSource = preg_replace( $from, $to, $pTplSource );
		$from = '#<form([^>]*)>#i';
		$to = '<form\\1><input type="hidden" name="tk" value="{$gBitUser->mTicket}" />';
		$pTplSource = preg_replace( $from, $to, $pTplSource );
		if( strpos( $pTplSource, '{form}' )) {
			$pTplSource = str_replace( '{form}', '{form}<input type="hidden" name="tk" value="{$gBitUser->mTicket}" />', $pTplSource );
		} elseif( strpos( $pTplSource, '{form ' ) ) {
			$from = '#\{form(\}| [^\}]*)\}#i';
			$to = '{form\\1}<input type="hidden" name="tk" value="{$gBitUser->mTicket}" />';
			$pTplSource = preg_replace( $from, $to, $pTplSource );
		}
	}

	return $pTplSource;
}
?>
