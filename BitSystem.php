<?php /* -*- Mode: php; tab-width: 4; indent-tabs-mode: t; c-basic-offset: 4; -*- */
/**
 * Main bitweaver systems functions
 *
 * @package kernel
 * @version $Header$
 * @author spider <spider@steelsun.com>
 */
// +----------------------------------------------------------------------+
// | PHP version 4.??
// +----------------------------------------------------------------------+
// | Copyright (c) 2005 bitweaver.org
// +----------------------------------------------------------------------+
// | Copyright (c) 2004-2005, Christian Fowler, et. al.
// | All Rights Reserved. See below for details and a complete list of authors.
// | Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details
// |
// | For comments, please use PEAR documentation standards!!!
// | -> see http://pear.php.net/manual/en/standards.comments.php
// |    and http://www.phpdoc.org/
// +----------------------------------------------------------------------+

/**
 * required setup
 */
require_once( KERNEL_PKG_PATH . 'BitBase.php' );
require_once( KERNEL_PKG_PATH . 'BitDate.php' );
require_once( THEMES_PKG_PATH . 'BitSmarty.php' );

define( 'DEFAULT_PACKAGE', 'kernel' );
define( 'CENTER_COLUMN', 'c' );
define( 'HOMEPAGE_LAYOUT', 'home' );

define( 'PKG_PLUGIN_TYPE_FUNCTION', 'function' );
define( 'PKG_PLUGIN_TYPE_SQL', 'sql' );
define( 'PKG_PLUGIN_TYPE_TPL', 'tpl' );

/**
 * kernel::BitSystem
 *
 * Purpose:
 *
 *     This is the main system class that does the work of seeing bitweaver has an
 * 	operable environment and has methods for modifying that environment.
 *
 * 	Currently gBitSystem derives from this class for backward compatibility sake.
 * 	Ultimate goal is to put system code from BitBase here, and base code from
 * 	gBitSystem (code that ALL features need) into BitBase and code gBitSystem that
 * 	is Package specific should be moved into that package
 *
 * @author spider <spider@steelsun.com>
 *
 * @package kernel
 */
class BitSystem extends BitBase {

	// Initiate class variables

	// Essential information about packages
	// DEPRECATED @TODO Delete
	var $mPackages = array();

	// Active Packages
	var $mPackagesConfig = array();

	// Installed Packages
	var $mPackagesInstalled = array();

	// Active (ONLY) Package Plugins
	var $mPackagePluginsConfig = array();
	
	// Installed Package Plugins
	var $mPackagePluginsInstalled = array();

	// An array of registered plugin handlers
	var $mPackagePluginsHandlers = array();

	// Cross Reference Package Directory Name => Package Key used as index into $mPackages
	var $mPackagesDirNameXref = array();

	// Contains site style information
	var $mStyle = array();

	// Information about package menus used in all menu modules and top bar
	var $mAppMenu = array();

	// The currently active page
	var $mActivePackage;

	// Javascript to be added to the <body onload> attribute
	var $mOnload = array();

	// Javascript to be added to the <body onunload> attribute
	var $mOnunload = array();

	// Used by packages to register notification events that can be subscribed to.
	var $mNotifyEvents = array();

	// Used to store contents of kernel_config
	var $mConfig;

	// Used to monitor if ::registerPackage() was called. This is used to determine whether to auto-register a package
	var $mRegisterCalled;

	// The name of the package that is currently being processed
	var $mPackageFileName;

	// Content classes. 
	var $mContentClasses = array();

	// Debug HTML to be displayed just after the HTML headers
	var $mDebugHtml = "";

	/**
	 * mPackagesSchemas
	 */
	var $mPackagesSchemas = array();

	/**
	 * mPermissionsSchema
	 */
	var $mPermissionsSchema = array();


	// === BitSystem constructor
	/**
	 * base constructor, auto assigns member db variable
	 *
	 * @access public
	 */
	// Constructor receiving a PEAR::Db database object.
	function BitSystem() {
		global $gBitTimer;
		// Call DB constructor which will create the database member variable
		BitBase::BitBase();

		$this->mAppMenu = array();

		$this->mTimer = $gBitTimer;
		$this->mServerTimestamp = new BitDate();

		$this->loadConfig();

		// Critical Preflight Checks
		$this->checkEnvironment();

		$this->initSmarty();
		$this->mRegisterCalled = FALSE;
	}

	// === initSmarty
	/**
	 * Define and load Smarty components
	 *
	 * @param none $
	 * @return none
	 * @access private
	 */
	function initSmarty() {
		global $_SERVER, $gBitSmarty;

		// Set the separator for PHP generated tags to be &amp; instead of &
		// This is necessary for XHTML compliance
		ini_set( "arg_separator.output", "&amp;" );
		// Remove automatic quotes added to POST/COOKIE by PHP
		if( get_magic_quotes_gpc() ) {
			foreach( $_REQUEST as $k => $v ) {
				if( !is_array( $_REQUEST[$k] ) ) {
					$_REQUEST[$k] = stripslashes( $v );
				}
			}
		}

		// make sure we only create one BitSmarty
		if( !is_object( $gBitSmarty ) ) {
			$gBitSmarty = new BitSmarty();
			// set the default handler
			$gBitSmarty->load_filter( 'pre', 'tr' );
			// $gBitSmarty->load_filter('output','trimwhitespace');
			if( isset( $_REQUEST['highlight'] ) ) {
				$gBitSmarty->load_filter( 'output', 'highlight' );
			}
		}
	}

	/**
	 * Load all preferences and store them in $this->mConfig
	 *
	 * @param $pPackage string optional load preferences only for selected package
	 * $param $pForce boolean force reload of config settings 
	 */
	public function loadConfig( $pPackage = NULL, $pForce = FALSE ) {
		$queryVars = array();
		$whereClause = '';

		if( $pPackage ) {
			array_push( $queryVars, $pPackage );
			$whereClause = ' WHERE `package`=? ';
		}

		if ( empty( $this->mConfig ) || $pForce ) {
			$this->mConfig = array();
			$query = "SELECT `config_name` ,`config_value`, `package` FROM `" . BIT_DB_PREFIX . "kernel_config` " . $whereClause;
			if( $rs = $this->mDb->query( $query, $queryVars, -1, -1 ) ) {
				while( $row = $rs->fetchRow() ) {
					$this->mConfig[$row['config_name']] = $row['config_value'];
				}
			}
		}
		return count( $this->mConfig );
	}

	// <<< getConfig
	/**
	 * Add getConfig / setConfig for more uniform handling of config variables instead of spreading global vars.
	 * easily get the value of any given preference stored in kernel_config
	 *
	 * @access public
	 **/
	public function getConfig( $pName, $pDefault = NULL ) {
		if( empty( $this->mConfig ) ) {
			$this->loadConfig();
		}
		return( empty( $this->mConfig[$pName] ) ? $pDefault : $this->mConfig[$pName] );
	}

	// <<< getConfigMatch
	/**
	 * retreive a group of config variables
	 *
	 * @access public
	 **/
	function getConfigMatch( $pPattern, $pSelectValue="" ) {
		if( empty( $this->mConfig ) ) {
			$this->loadConfig();
		}

		$matching_keys = preg_grep( $pPattern, array_keys( $this->mConfig ));
		$new_array = array();
		foreach( $matching_keys as $key=>$value ) {
			if ( empty( $pSelectValue ) || ( !empty( $pSelectValue ) && $this->mConfig[$value] == $pSelectValue )) {
				$new_array[$value] = $this->mConfig[$value];
			}
		}
		return( $new_array );
	}

	/**
	 * storeConfigMatch set a group of config variables
	 * 
	 * @param string $pPattern Perl regular expression
	 * @param string $pSelectValue only manipulate settings with this value set
	 * @param string $pNewValue New value that should be set for the matching settings (NULL will remove the entries from the DB)
	 * @param string $pPackage Package for which the settings are
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function storeConfigMatch( $pPattern, $pSelectValue = "", $pNewValue = NULL, $pPackage = NULL ) {
		if( empty( $this->mConfig ) ) {
			$this->loadConfig();
		}

		$matchingKeys = preg_grep( $pPattern, array_keys( $this->mConfig ));
		foreach( $matchingKeys as $key => $config_name ) {
			if( empty( $pSelectValue ) || ( !empty( $pSelectValue ) && $this->mConfig[$config_name] == $pSelectValue )) {
				$this->storeConfig( $config_name, $pNewValue, $pPackage );
			}
		}
	}

	/**
	 * Set a hash value in the mConfig hash. This does *NOT* store the value in 
	 * the database. It does no checking for existing or duplicate values. the 
	 * main point of this function is to limit direct accessing of the mConfig 
	 * hash. I will probably make mConfig private one day.
	 *
	 * @param string Hash key for the mConfig value
	 * @param string Value for the mConfig hash key
	 */
	function setConfig( $pName, $pValue ) {
		$this->mConfig[$pName] = $pValue;
		return( TRUE );
	}

	// <<< storeConfig
	/**
	 * bitweaver needs lots of settings just to operate.
	 * loadConfig assigns itself the default preferences, then loads just the differences from the database.
	 * In storeConfig (and only when storeConfig is called) we make a second copy of defaults to see if
	 * preferences you are changing is different from the default.
	 * if it is the same, don't store it!
	 * So instead updating the whole prefs table, only updat "delta" of the changes delta from defaults.
	 *
	 * @access public
	 **/
	function storeConfig( $pName, $pValue, $pPackage = NULL ) {
		global $gMultisites;
		//stop undefined offset error being thrown after packages are installed
		if( !empty( $this->mConfig )) {
			// store the pref if we have a value _AND_ it is different from the default
			if( ( empty( $this->mConfig[$pName] ) || ( $this->mConfig[$pName] != $pValue ))) {
				// make sure the value doesn't exceede database limitations
				$pValue = substr( $pValue, 0, 250 );

				// store the preference in multisites, if used
				if( $this->isPackageActive( 'multisites' ) && @BitBase::verifyId( $gMultisites->mMultisiteId ) && isset( $gMultisites->mConfig[$pName] )) {
					$query = "UPDATE `".BIT_DB_PREFIX."multisite_preferences` SET `config_value`=? WHERE `multisite_id`=? AND `config_name`=?";
					$result = $this->mDb->query( $query, array( empty( $pValue ) ? '' : $pValue, $gMultisites->mMultisiteId, $pName ) );
				} else {
					$query = "DELETE FROM `".BIT_DB_PREFIX."kernel_config` WHERE `config_name`=?";
					$result = $this->mDb->query( $query, array( $pName ) );
					// make sure only non-empty values get saved, including '0'
					if( isset( $pValue ) && ( !empty( $pValue ) || is_numeric( $pValue ))) {
						$query = "INSERT INTO `".BIT_DB_PREFIX."kernel_config`(`config_name`,`config_value`,`package`) VALUES (?,?,?)";
						$result = $this->mDb->query( $query, array( $pName, $pValue, strtolower( $pPackage )));
					}
				}

				// Force the ADODB cache to flush
				$isCaching = $this->mDb->isCachingActive();
				$this->mDb->setCaching( FALSE );
				$this->loadConfig();
				$this->mDb->setCaching( $isCaching );
			}
		}
		$this->setConfig( $pName, $pValue );
		return TRUE;
	}

	// <<< expungePackageConfig
	/**
	 * Delete all prefences for the given package
	 * @access public
	 **/
	function expungePackageConfig( $pPackageName ) {
		if( !empty( $pPackageName ) ) {
			$query = "DELETE FROM `".BIT_DB_PREFIX."kernel_config` WHERE `package`=?";
			$result = $this->mDb->query( $query, array( strtolower( $pPackageName ) ) );
			// let's force a reload of the prefs
			unset( $this->mConfig );
			$this->loadConfig();
		}
	}

	// === hasValidSenderEmail
	/**
	 * Determines if this site has a legitimate sender address set.
	 *
	 * @param  $mid the name of the template for the page content
	 * @access public
	 */
	function hasValidSenderEmail( $pSenderEmail=NULL ) {
		if( empty( $pSenderEmail ) ) {
			$pSenderEmail = $this->getConfig( 'site_sender_email' );
		}
		return( !empty( $pSenderEmail ) && !preg_match( '/.*localhost$/', $pSenderEmail ) );
	}

	// === getErrorEmail
	/**
	 * Smartly determines where error emails should go
	 *
	 * @access public
	 */
	function getErrorEmail() {
		$ret = NULL;
		if( defined('ERROR_EMAIL') ) {
			$ret = ERROR_EMAIL;
		} elseif( $this->getConfig( 'bitmailer_sysadmin_email' ) ) {
			$ret = $this->getConfig( 'bitmailer_sysadmin_email' );
		} elseif( !empty( $_SERVER['SERVER_ADMIN'] ) ) {
			$ret = $_SERVER['SERVER_ADMIN'];
		} else {
			$ret = 'root@localhost';
		}
		return $ret;
	}

	// === sendEmail
	/**
	 * centralized function for send emails
	 *
	 * @param  $mid the name of the template for the page content
	 * @access public
	 */
	function sendEmail( $pMailHash ) {
		$extraHeaders = '';
		if( $this->getConfig( 'bcc_email' ) ) {
			$extraHeaders = "Bcc: ".$this->getConfig( 'bcc_email' )."\r\n";
		}
		if( !empty( $pMailHash['Reply-to'] ) ) {
			$extraHeaders = "Reply-to: ".$pMailHash['Reply-to']."\r\n";
		}

		mail($pMailHash['email'],
			$pMailHash['subject'].' '.$_SERVER["SERVER_NAME"],
			$pMailHash['body'],
			"From: ".$this->getConfig( 'site_sender_email' )."\r\nContent-type: text/plain;charset=utf-8\r\n$extraHeaders"
		);
	}


	/**
	 * Set the http status, most notably for 404 not found for deleted content
	 *
	 * @param  $pHttpStatus numerical HTTP status, most typically 404 (not found) or 403 (forbidden)
	 * @access public
	 */
	function setHttpStatus( $pHttpStatus ) {
		$this->mHttpStatus = $pHttpStatus;
	}


	/**
	 * Display the main page template
	 *
	 * @param  $mid the name of the template for the page content
	 * @param  $browserTitle a string to be displayed in the top browser bar
	 * @param  $format the output format - xml, ajax, content, full - relays to setRenderFormat
	 * @access public
	 */
	function display( $pMid, $pBrowserTitle = NULL, $pOptionsHash = array() ) {
		global $gBitSmarty, $gBitThemes, $gContent;
		$gBitSmarty->verifyCompileDir();

		// see if we have a custom status other than 200 OK
		if( isset( $this->mHttpStatus ) ) {
			switch( $this->mHttpStatus ) {
				// before you can spunky and decide to enter every HTTP status code under the sun here, please have the code needed someplace first
			case '403':
				header( "HTTP/1.0 403 Forbidden" );
				break;
			case '404':
				header( "HTTP/1.0 404 Not Found" );
				break;
			}
		}

		// set the correct headers if it hasn't been done yet
		if( empty( $gBitThemes->mFormatHeader )) {
			// display is the last thing we call and therefore we need to set a default
			$gBitThemes->setFormatHeader( !empty( $pOptionsHash['format'] ) ? $pOptionsHash['format'] : 'html' );
		}

		// set the desired display mode - this lets bitweaver know what type of page we are viewing
		if( empty( $gBitThemes->mDisplayMode )) {
			// display is the last thing we call and therefore we need to set a default
			$gBitThemes->setDisplayMode( !empty( $pOptionsHash['display_mode'] ) ? $pOptionsHash['display_mode'] : 'display' );
		}

		if( $pMid == 'error.tpl' ) {
			$this->setBrowserTitle( !empty( $pBrowserTitle ) ? $pBrowserTitle : tra( 'Error' ) );
			$pMid = 'bitpackage:kernel/error.tpl';
		}

		// only using the default html header will print modules and all the rest of it.
		if( $gBitThemes->mFormatHeader != 'html' ) {
			$gBitSmarty->assign_by_ref( 'gBitSystem', $this );
			$gBitSmarty->display( $pMid );
			return;
		}

		// @TODO debug - causes where problems loading existing data
		// $gBitThemes->loadAjax('jquery');
		// $gBitThemes->loadJavascript( UTIL_PKG_PATH.'uniform/js/uni-form.jquery.js' );

		if( !empty( $pBrowserTitle )) {
			$this->setBrowserTitle( $pBrowserTitle );
		}

		// populate meta description with something useful so you are not penalized/ignored by web crawlers
		if( is_object( $gContent ) && $gContent->isValid() ) {
			if( $summary = $gContent->getField( 'summary' ) ) {
				$desc = $gContent->parseData( $summary );
			} elseif( $desc = $gContent->getField( 'parsed' ) ) {
			} elseif( $summary = $gContent->getField( 'data' ) ) {
				$desc = $gContent->parseData( $summary );
			}
			if( !empty( $desc ) ) {
				$desc = $gContent->getContentTypeName().': '.$desc;
				$gBitSmarty->assign_by_ref( 'metaDescription', substr( strip_tags( $desc ), 0, 256 ) );
			}
		}

		$this->preDisplay( $pMid );
		$gBitSmarty->assign( 'mid', $pMid );
		//		$gBitSmarty->assign( 'page', !empty( $_REQUEST['page'] ) ? $_REQUEST['page'] : NULL );
		// Make sure that the gBitSystem symbol available to templates is correct and up-to-date.
		$gBitSmarty->assign_by_ref('gBitSystem', $this);
		if( !empty( $pOptionsHash['return'] ) && $pOptionsHash['return'] == 'fetch' ){
			return $gBitSmarty->fetch( 'bitpackage:kernel/bitweaver.tpl' );
		}else{
			$gBitSmarty->display( 'bitpackage:kernel/bitweaver.tpl' );
		}
		$this->postDisplay( $pMid );
	}


	// === preDisplay
	/**
	 * Take care of any processing that needs to happen just before the template is displayed
	 *
	 * @param none $
	 * @access private
	 */
	function preDisplay( $pMid ) {
		global $gCenterPieces, $gBitSmarty, $gBitThemes, $gDefaultCenter;

		$gBitThemes->loadLayout();

		// check to see if we are working with a dynamic center area
		if( $pMid == 'bitpackage:kernel/dynamic.tpl' ) {
			// pre-render dynamic center content
			$dynamicContent = "";
			if( !empty( $gCenterPieces ) ){
				foreach ( $gCenterPieces as $centerPiece ){
					$gBitSmarty->assign( 'moduleParams', $centerPiece );
					$dynamicContent .= $gBitSmarty->fetch( $centerPiece['module_rsrc'] );
				}
			}elseif( $gDefaultCenter ){
				$dynamicContent = $gBitSmarty->fetch( $gDefaultCenter );
			}
			$gBitSmarty->assign( 'dynamicContent', $dynamicContent );
		}

		$gBitThemes->preLoadStyle();

		/* @TODO - fetch module php files before rendering tpls.
		 * The basic problem here is center_list and module files are 
		 * processed during page rendering, which means code in those
		 * files can not set <head> information before rendering. Kinda sucks.
		 *
		 * So what this does is, this calls on a service function allowing any
		 * package to check if its center or other module file is going to be 
		 * called and gives it a chance to set any information for <head> first.
		 * 
		 * Remove when TODO is complete. -wjames5
		 */
		global $gBitUser;
		if( isset( $gBitUser )) {
			$gBitUser->invokeServices( 'module_display_function' );
		}

		// process layout
		require_once( THEMES_PKG_PATH.'modules_inc.php' );

		$gBitThemes->loadStyle();

		/* force the session to close *before* displaying. Why? Note this very important comment from http://us4.php.net/exec
			edwin at bit dot nl
			23-Jan-2002 04:47
				If you are using sessions and want to start a background process, you might
				have the following problem:
				The first time you call your script everything goes fine, but when you call it again
				and the process is STILL running, your script seems to "freeze" until you kill the
				process you started the first time.

				You'll have to call session_write_close(); to solve this problem. It has something
				to do with the fact that only one script/process can operate at once on a session.
				(others will be lockedout until the script finishes)

				I don't know why it somehow seems to be influenced by background processes,
				but I tried everything and this is the only solution. (i had a perl script that
				"daemonized" it's self like the example in the perl manuals)

				Took me a long time to figure out, thanks ian@virtisp.net! :-)

			... and a similar issue can happen for very long display times.
		 */
		session_write_close();
	}

	// === postDisplay
	/**
	 * Take care of any processing that needs to happen just after the template is displayed
	 *
	 * @param none $
	 * @access private
	 */
	function postDisplay( $pMid ) {
	}

	// === setHelpInfo
	/**
	 * Set the smarty variables needed to display the help link for a page.
	 *
	 * @param  $package Package Name
	 * @param  $context Context of the help within the package
	 * @param  $desc Description of the help link (not the help itself)
	 * @access private
	 */
	function setHelpInfo( $package, $context, $desc ) {
		global $gBitSmarty;
		$gBitSmarty->assign( 'TikiHelpInfo', array( 'URL' => 'http://doc.bitweaver.org/wiki/index.php?page=' . $package . $context , 'Desc' => $desc ) );
	}

	// === isPackageActive
	/**
	 * check's if a package is active.
	 * @param $pPackageGuid the guid of the package to test
	 * @return boolean
	 * @access public
	 */
	function isPackageActive( $pPackageGuid ) {
		return( $this->getPackageConfigValue( $pPackageGuid, 'active' ) == 'y' );
	}

	// === isPackageActiveEarly
	/**
	 * check if a package is active; but only do this after making sure a package
	 * has had it's bit_setup_inc loaded if possible.  This func exists for use in
	 * other packages bit_setup_inc's to avoid dependency on load order and ugly code
	 * @param $pPackageName the name of the package to test
	 *        where the package name is in the form used to index $mPackages
	 *        See comments in scanPackages for more information
	 * @return boolean
	 * @access public
	 */
	function isPackageActiveEarly( $pPackageName ) {

		$ret = FALSE;
		$pkgname_l = strtolower( $pPackageName );
		if( is_file(BIT_ROOT_PATH.$pkgname_l.'/bit_setup_inc.php') ) {
			require_once(BIT_ROOT_PATH.$pkgname_l.'/bit_setup_inc.php');
			$ret = $this->isPackageActive( $pPackageName );
		} elseif( $pkgname_l == 'kernel' ) {
			$ret = TRUE;
		}

		return( $ret );
	}

	// === isPackageInstalled
	/**
	 * check's if a package is Installed
	 * @param $pPackageGuid the guid of the package to test
	 * @return boolean
	 * @access public
	 */
	function isPackageInstalled( $pPackageGuid ){
		return !is_null( $this->getInstalledPackageConfig( $pPackageGuid ) );
	}

	// === isPackageRequired
	/**
	 * check's if a package is required.
	 * @param $pPackageGuid the guid of the package to test
	 * @return boolean
	 * @access public
	 */
	function isPackageRequired( $pPackageGuid ) {
		return( $this->getPackageSchemaValue( $pPackageGuid, 'required' ) == 'y' );
	}

	// === verifyPackage
	/**
	 * It will verify that the given package is active or it will display the error template and die()
	 * @param $pPackageGuid the name of the package to test
	 * @return boolean
	 * @access public
	 */
	function verifyPackage( $pPackageGuid ) {
		if( !$this->isPackageActive( $pPackageGuid ) ) {
			$this->fatalError( tra("This package is disabled").": package_$pPackageGuid" );
		}

		return( TRUE );
	}

	// === getPermissionInfo
	/**
	 * It will get information about a permissions
	 * @param $pPermission value of a given permission
	 * @return none
	 * @access public
	 */
	function getPermissionInfo( $pPermission = NULL, $pPackageName = NULL ) {
		$ret = NULL;
		$bindVars = array();
		$sql = 'SELECT * FROM `'.BIT_DB_PREFIX.'users_permissions` ';
		if( !empty( $pPermission ) ) {
			$sql .= ' WHERE `perm_name`=? ';
			array_push( $bindVars, $pPermission );
		} elseif( !empty( $pPackageName ) ) {
			$sql .= ' WHERE `package` = ? ';
			array_push( $bindVars, substr($pPackageName,0,100));
		}
		$ret = $this->mDb->getAssoc( $sql, $bindVars );
		return $ret;
	}

	// === verifyPermission
	/**
	 * DEPRECATED - this function has been moved into BitPermUser, use that 
	 */
	function verifyPermission( $pPermission, $pMsg = NULL ) {
		global $gBitUser;
		return $gBitUser->verifyPermission( $pPermission, $pMsg );
	}

	// === fatalPermission
	/**
	 * Interupt code execution and show a permission denied message.
	 * This does not show a big nasty denied message if user is simply not logged in.
	 * This *could* lead to a user seeing a denied message twice, however this is 
	 * unlikely as logic permission checks should prevent access to non-permed page REQUEST in the first place
	 * @param $pPermission value of a given permission
	 * @param $pMsg optional additional information to present to user
	 * @return none
	 * @access public
	 */
	function fatalPermission( $pPermission, $pMsg=NULL ) {
		global $gBitUser, $gBitSmarty;
		if( !$gBitUser->isRegistered() ) {
			$gBitSmarty->assign( 'errorHeading', 'Please login&nbsp;&hellip;' );
			$title = 'Please login&nbsp;&hellip;';
			$pMsg .= '</p><p>You must be logged in. Please <a href="'.USERS_PKG_URL.'login.php">login</a>';
			if( $this->getConfig( 'users_allow_register', 'y' ) == 'y' ) {
				$pMsg .= ' or <a href="'.USERS_PKG_URL.'register.php">register</a>.';
			}
			$gBitSmarty->assign( 'template', 'bitpackage:users/login_inc.tpl' );
		} else {
			$title = 'Oops!';
			if( empty( $pMsg ) ) {
				$permDesc = $this->getPermissionInfo( $pPermission );
				$pMsg = "You do not have the required permissions ";
				if( !empty( $permDesc[$pPermission]['perm_desc'] ) ) {
					if( preg_match( '/administrator,/i', $permDesc[$pPermission]['perm_desc'] ) ) {
						$pMsg .= preg_replace( '/^administrator, can/i', ' to ', $permDesc[$pPermission]['perm_desc'] );
					} else {
						$pMsg .= preg_replace( '/^can /i', ' to ', $permDesc[$pPermission]['perm_desc'] );
					}
				}
			}
			$gBitSmarty->assign( 'fatalTitle', tra( "Permission denied." ) );
		}
// bit_log_error( "PERMISSION DENIED: $pPermission $pMsg" ); 
		$gBitSmarty->assign( 'msg', tra( $pMsg ) );
		$this->display( "error.tpl" , tra("Error"), array( 'display_mode' => 'error' ) );
		die;
	}

	/**
	 * This code was duplicated _EVERYWHERE_ so here is an easy template to cut that down.
	 * @param $pFormHash documentation needed
	 * @param $pMsg documentation needed
	 * @return none
	 * @access public
	 */
	function confirmDialog( $pFormHash, $pMsg, $pDisplayMode = 'edit' ) {
		global $gBitSmarty;
		if( !empty( $pMsg ) ) {
			// automatically preserve pagination
			if( !empty( $_REQUEST['find'] ) )
				$pFormHash['find'] = $_REQUEST['find'];
			if( !empty( $_REQUEST['sort_mode'] ) )
				$pFormHash['sort_mode'] = $_REQUEST['sort_mode'];
			if( !empty( $_REQUEST['list_page'] ) )
				$pFormHash['list_page'] = $_REQUEST['list_page'];
			if( !empty( $_REQUEST['offset'] ) )
				$pFormHash['offset'] = $_REQUEST['offset'];
			if( !empty( $_REQUEST['max_records'] ) )
				$pFormHash['max_records'] = $_REQUEST['max_records'];
			// cancel
			if( empty( $pParamHash['cancel_url'] ) ) {
				$gBitSmarty->assign( 'backJavascript', 'onclick="history.back();"' );
			}
			// reserved hash param 'input' injects custom input html
			if( !empty( $pFormHash['input'] ) ) {
				$gBitSmarty->assign( 'inputFields', $pFormHash['input'] );
				unset( $pFormHash['input'] );
			}
			// render and exit
			$gBitSmarty->assign( 'msgFields', $pMsg );
			$gBitSmarty->assign_by_ref( 'hiddenFields', $pFormHash );
			$this->display( 'bitpackage:kernel/confirm.tpl', NULL, array( 'display_mode' => $pDisplayMode ));
			die;
		}
	}

	// === isFeatureActive
	/**
	 * check's if the specfied feature is active
	 *
	 * @param  $pKey hash key
	 * @return none
	 * @access public
	 */
	function isFeatureActive( $pFeatureName ) {
		$ret = FALSE;
		if( $pFeatureName ) {
			$featureValue = $this->getConfig($pFeatureName);
			$ret = !empty( $featureValue ) && ( $featureValue != 'n' );
		}

		return( $ret );
	}

	// === verifyFeature
	/**
	 * It will verify that the given feature is active or it will display the error template and die()
	 * @param $pFeatureName the name of the package to test
	 * @return none
	 * @access public
	 *
	 * @param  $pKey hash key
	 */
	function verifyFeature( $pFeatureName ) {
		if( !$this->isFeatureActive( $pFeatureName ) ) {
			$this->fatalError( tra("This feature is disabled").": $pFeatureName" );
		}

		return( TRUE );
	}

	// === registerPackage
	/**
	 * Define name, location and url DEFINE's
	 *
	 * @param  $pKey hash key
	 * @return none
	 * @access public
	 */
	function registerPackage( $pRegisterHash ) {
		global $gBitSystem;
		if( $gBitSystem->isFeatureActive( 'kernel_autoscan_pkgs', FALSE ) ){
			$this->configPackage( $pRegisterHash );
		}
	}

	function configPackage( $pRegisterHash ){
		if( !isset( $pRegisterHash['package_name'] )) {
			$this->fatalError( tra("Package name not set in ")."registerPackage: $this->mPackageFileName" );;
		} else {
			$name = $pRegisterHash['package_name'];
		}

		if( !isset( $pRegisterHash['package_path'] )) {
			$this->fatalError( tra("Package path not set in ")."registerPackage: $this->mPackageFileName" );;
		} else {
			$path = $pRegisterHash['package_path'];
		}

		$this->mRegisterCalled = TRUE;
		if( empty( $this->mPackagesConfig )) {
			$this->loadPackagesConfig();
		}
		$pkgName = str_replace( ' ', '_', strtoupper( $name ));
		$packageGuid = $pkgNameKey = strtolower( $pkgName );

		// Define <PACKAGE>_PKG_PATH
		$pkgDefine = $pkgName.'_PKG_PATH';
		if( !defined( $pkgDefine )) {
			define( $pkgDefine, $path );
		}

		// Define <PACKAGE>_PKG_URL
		$pkgDefine = $pkgName.'_PKG_URL';
		if( !defined( $pkgDefine )) {
			// Force full URI's for offline or exported content (newsletters, etc.)
			$root = !empty( $_REQUEST['uri_mode'] ) ? BIT_BASE_URI . BIT_ROOT_URL : BIT_ROOT_URL;
			define( $pkgDefine, $root . basename( $path ) . '/' );
		}

		// Define <PACKAGE>_PKG_URI
		$pkgDefine = $pkgName.'_PKG_URI';
		if( !defined( $pkgDefine ) && defined( 'BIT_BASE_URI' )) {
			define( $pkgDefine, BIT_BASE_URI . BIT_ROOT_URL . basename( $path ) . '/' );
		}

		// Define <PACKAGE>_PKG_NAME
		$pkgDefine = $pkgName.'_PKG_NAME';
		if( !defined( $pkgDefine )) {
			define( $pkgDefine, $packageGuid );
		}

		// Define <PACKAGE>_PKG_DIR
		$package_dir_name = basename( $path );
		$pkgDefine = $pkgName.'_PKG_DIR';
		if( !defined( $pkgDefine )) {
			define( $pkgDefine, $package_dir_name );
		}

		// Define <PACKAGE>_PKG_TITLE
		$pkgDefine = $pkgName.'_PKG_TITLE';
		if( !defined( $pkgDefine )) {
			define( $pkgDefine, ucfirst( constant( $pkgName.'_PKG_DIR' ) ) );
		}

		if( $this->isPackageInstalled( $packageGuid ) ){
			// @TODO move this to yaml and registration table
			// $this->mPackagesConfig[$pkgNameKey]['service']  = !empty( $pRegisterHash['service'] ) ? $pRegisterHash['service'] : FALSE;

			// pass package settings to installed packages hash
			$this->mPackagesConfig[$pkgNameKey]['url']  = BIT_ROOT_URL . basename( $path ) . '/';
			$this->mPackagesConfig[$pkgNameKey]['path']  = BIT_ROOT_PATH . basename( $path ) . '/';
			$this->mPackagesConfig[$pkgNameKey]['dir'] = $package_dir_name;
			$this->mPackagesDirNameXref[$package_dir_name] = $pkgNameKey;
		}	
	}

	function defineActivePackage(){
		if( !empty( $this->mPackagesConfig ) && !defined( 'ACTIVE_PACKAGE' ) ){ 
			$activePackage = NULL;

			// Work around for old versions of IIS that do not support $_SERVER['SCRIPT_FILENAME'] - wolff_borg
			if( !array_key_exists( 'SCRIPT_FILENAME', $_SERVER )) {
				//remove double-backslashes and return
				$_SERVER['SCRIPT_FILENAME'] =  str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED'] );
			}
			// Define the package we are currently in
			// I tried strpos instead of preg_match here, but it didn't like strings that begin with slash?! - spiderr
			$scriptDir = ( basename( dirname( $_SERVER['SCRIPT_FILENAME'] ) ) );

			if( isset( $_SERVER['ACTIVE_PACKAGE'] )) {
				// perhaps the webserver told us the active package (probably because of mod_rewrites)
				$activePackage = $_SERVER['ACTIVE_PACKAGE'];
			}else{
				foreach( $this->mPackagesConfig as $pkgGuid => $pkgHash ){
					if( $scriptDir == $pkgHash['dir']  
						|| isset( $_SERVER['ACTIVE_PACKAGE'] ) 
						|| preg_match( '!/'.$pkgHash['dir'].'/!', $_SERVER['PHP_SELF'] ) 
						|| preg_match( '!/'.$pkgGuid.'/!', $_SERVER['PHP_SELF'] )
					){
						$activePackage = $pkgGuid;
						break;
					}
				}
			}
			if( $activePackage ){
				define( 'ACTIVE_PACKAGE', $activePackage );
				$this->mActivePackage = $activePackage;
			}elseif( !defined( 'ACTIVE_PACKAGE' ) && $defaultPkg = $this->getConfig('bit_index' ) ){
				define( 'ACTIVE_PACKAGE', $defaultPkg );
				$this->mActivePackage = $defaultPkg;
			}
		}
	}

	// === registerAppMenu
	/**
	 * Register global system menu. Due to the startup nature of this method, it need to belong in BitSystem instead of BitThemes, where it would more naturally fit.
	 *
	 * @param  $pKey hash key
	 * @return none
	 * @access public
	 */
	function registerAppMenu( $pMenuHash, $pMenuTitle = NULL, $pTitleUrl = NULL, $pMenuTemplate = NULL, $pAdminPanel = FALSE ) {
		if( is_array( $pMenuHash ) ) {
			// shorthand
			$pkg = $pMenuHash['package_name'];

			// prepare hash
			$pMenuHash['style']       = 'display:'.( ( isset( $_COOKIE[$pMenuHash.'menu'] ) && ( $_COOKIE[$pMenuHash.'menu'] == 'o' ) ) ? 'block;' : 'none;' );
			$pMenuHash['is_disabled'] = ( $this->getConfig( 'menu_'.$pkg ) == 'n' );
			$pMenuHash['menu_title']  = $this->getConfig( $pkg.'_menu_text',
				( !empty( $pMenuHash['menu_title'] )
				? $pMenuHash['menu_title']
				: ucfirst( constant( strtoupper( $pkg ).'_PKG_DIR' )))
			);
			$pMenuHash['menu_position'] = $this->getConfig( $pkg.'_menu_position',
				( !empty( $pMenuHash['menu_position'] )
				? $pMenuHash['menu_position']
				: NULL )
			);

			$this->mAppMenu[$pkg] = $pMenuHash;
		} else {
			deprecated( 'Please use a menu registration hash instead of individual parameters: $gBitSystem->registerAppMenu( $menuHash )' );
			$this->mAppMenu[strtolower( $pMenuHash )] = array(
				'menu_title'    => $pMenuTitle,
				'is_disabled'   => ( $this->getConfig( 'menu_'.$pMenuHash ) == 'n' ),
				'index_url'     => $pTitleUrl,
				'menu_template' => $pMenuTemplate,
				'admin_panel'   => $pAdminPanel,
				'style'         => 'display:'.( empty( $pMenuTitle ) || ( isset( $_COOKIE[$pMenuHash.'menu'] ) && ( $_COOKIE[$pMenuHash.'menu'] == 'o' ) ) ? 'block;' : 'none;' )
			);
		}
		uasort( $this->mAppMenu, 'bit_system_menu_sort' );
	}

	/**
	 * registerNotifyEvent 
	 * 
	 * @param array $pEventHash 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function registerNotifyEvent( $pEventHash ) {
		$this->mNotifyEvents = array_merge( $this->mNotifyEvents, $pEventHash );
	}

	// === fatalError
	/**
	 * If an unrecoverable error has occurred, this method should be invoked. script exist occurs
	 *
	 * @param string $ pMsg error message to be displayed
	 * @param string template file used to display error
	 * @param string error dialog title. default gets site_error_title config, passing '' will result in no title
	 * @return none this function will DIE DIE DIE!!!
	 * @access public
	 */
	function fatalError( $pMsg, $pTemplate='error.tpl', $pErrorTitle=NULL ) {
		global $gBitSmarty;
		if( is_null( $pErrorTitle ) ) {
			$pErrorTitle = $this->getConfig( 'site_error_title', '' );
		}
		$gBitSmarty->assign( 'fatalTitle', tra( $pErrorTitle ) );
		$gBitSmarty->assign( 'msg', $pMsg );
		// if mHttpStatus is set, we can assume this was an expected fatal, such as a 404 or 403
		if( !isset( $this->mHttpStatus ) ) {
			error_log( "Fatal Error: $pMsg http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] );
		}
		$this->display( $pTemplate , tra("Error"), array( 'display_mode' => 'error' ) );
		die;
	}

	/**
	 * securityViolation
	 *
	 * @param string $pDisplayMsg a public message to display 
	 * @param string $pErrorMsg an error message to write to the server log 
	 */
	function securityViolation( $pErrorMsg='', $pDisplayMsg='' ){
		global $gBitUser, $gBitSystem;
		$userString = $gBitUser->isRegistered() ? "\nUSER ID: ".$gBitUser->mUserId.' ( '.$gBitUser->getField( 'email' ).' ) ' : '';
		@error_log( tra( "Security Violation" )."$pErrorMsg $userString ".$_SERVER['REMOTE_ADDR']."\nURI: $_SERVER[REQUEST_URI] \nREFERER: $_SERVER[HTTP_REFERER] " );
		$gBitSystem->fatalError( tra( "Security Violation" ).(!empty($pDisplayMsg)?': '.$pDisplayMsg:'') );
	}

	// === loadPackage
	/**
	 * Loads a package
	 *
	 * @param string $ pkgDir = Directory Name of package to load
	 * @param string $ pScanFile file to be looked for
	 * @param string $ pOnce - TRUE = do include_once to load file FALSE = do include to load the file
	 * @return none
	 * @access public
	 */
	function loadPackage( $pPkgDir, $pScanFile, $pOnce=TRUE ) { 
		$this->mRegisterCalled = FALSE;
		$scanFile = BIT_ROOT_PATH.$pPkgDir.'/'.$pScanFile;
		$file_exists = 0;

		if( file_exists( $scanFile ) ) {
			$file_exists = 1;
			global $gBitSystem, $gLibertySystem, $gBitSmarty, $gBitUser, $gBitLanguage;
			$this->mPackageFileName = $scanFile;
			if( $pOnce ) {
				include_once( $scanFile );
			} else {
				include( $scanFile );
			}
		}

		if( $pPkgDir == 'kernel' ) {
			$registerHash = array(
				//for auto registered packages Registration Package Name = Package Directory Name
				'package_name' => $pPkgDir,
				'package_path' => BIT_ROOT_PATH.$pPkgDir.'/',
				'activatable' => FALSE,
			);
			if( $pPkgDir == 'kernel' ) {
				$registerHash = array_merge( $registerHash, array( 'required_package'=>TRUE ) );
			}
			$this->registerPackage( $registerHash );
		}
	}

	function initPackage( $pPkg, $registerConfig = TRUE, $scanFiles = TRUE, $initPlugins = TRUE ) {
		$this->mRegisterCalled = FALSE;

		if ($registerConfig) {
			$registerHash = array(
				//for auto registered packages Registration Package Name = Package Directory Name
				'package_name' => $pPkg['guid'],
				'package_path' => BIT_ROOT_PATH.$pPkg['dir'].'/',
				'required_package' => $pPkg['required'],
				'homable' => !empty( $pPkg['homable'] )?TRUE:FALSE,
			);
			$this->configPackage( $registerHash );
		}
		if ($scanFiles) {
			$scanFile = BIT_ROOT_PATH.$pPkg['dir'].'/bit_setup_inc.php';
			if( file_exists( $scanFile ) ) {
				$file_exists = 1;
				global $gBitSystem, $gLibertySystem, $gBitSmarty, $gBitUser, $gBitLanguage;
				$this->mPackageFileName = $scanFile;
				include_once( $scanFile );
			}
		}
		if ($initPlugins) {
			$this->initPackagePlugins( $pPkg['guid'] );
		}
	}

	// === scanPackages
	/**
	 *
	 * scan all available packages. This is an *expensive* function. DO NOT call this functionally regularly , or arbitrarily. Failure to comply is punishable by death by jello suffication!
	 *
	 * @param string $ pScanFile file to be looked for
	 * @param string $ pOnce - TRUE = do include_once to load file FALSE = do include to load the file
	 * @param string $ pSelect - empty or 'all' = load all packages, 'installed' = load installed, 'active' = load active, 'x' = load packages with status x
	 * @param string $ autoRegister - TRUE = autoregister any packages that don't register on their own, FALSE = don't
	 * @param string $ fileSystemScan - TRUE = scan file system for packages to load, False = don't
	 * @return none
	 * 
	 * Packages have three different names:
	 *    The directory name where they reside on disk
	 *    The Name they register themselves as when they call registerPackage 
	 *    The Key for the array $this->mPackages
	 *    
	 * Example:
	 *    A package in directory 'stars' that registers itself with a name of 'Star Ratings'
	 *    would have these three names:
	 *    
	 *    Directory Name: 'stars'
	 *    Registered Name: Star Ratings'
	 *    $this->mPackages key: 'star_ratings'
	 *
	 *    Of course, its possible for all three names to be the same if the registered name
	 *    is all lower case without spaces and is the same as the diretory name.
	 *
	 *    Functions that expect a package name as a parameter should make clear which form
	 *    of the name they expect.
	 *    
	 * @access public
	 */
	function scanPackages( $pScanFile = 'bit_setup_inc.php', $pOnce=TRUE, $pSelect='' ) {
		global $gPreScan;
		if( !empty( $gPreScan ) && is_array( $gPreScan )) {
			// gPreScan may hold a list of packages that must be loaded first
			foreach( $gPreScan as $pkgDir ) {
				$loadPkgs[] = $pkgDir;
			}
		}

		// load lib configs
		if( $pkgDir = opendir( BIT_ROOT_PATH )) {
			while( FALSE !== ( $dirName = readdir( $pkgDir ))) {
				if( $dirName != '..'  && $dirName != '.' && is_dir( BIT_ROOT_PATH . '/' . $dirName ) && $dirName != 'CVS' && preg_match( '/^\w/', $dirName )) {
					$loadPkgs[] = $dirName;
				}
			}
		}
		$loadPkgs = array_unique( $loadPkgs );

		foreach( $loadPkgs as $loadPkg ) {
			$this->loadPackage( $loadPkg, $pScanFile, $pOnce );
		}
	}

	function initPackages(){
		if( empty( $this->mPackagesConfig ) ){
			$this->loadPackagesConfig();
		}
		global $gPreScan;
		$loadPkgs = array();
		// system is installed
		if( $pkgs = $this->mPackagesConfig ) {
			// gPreScan holds a list of packages that MUST be loaded first
			foreach( $gPreScan as $pkgGuid ) {
				if( !empty( $this->mPackagesConfig[$pkgGuid] ) ) 
					$loadPkgs[] = $this->getPackageConfig( $pkgGuid );
			}

			foreach( $pkgs as $pkg ){
				if( !in_array( $pkg['dir'], $gPreScan ) ){
					$loadPkgs[] = $pkg; 
				}
			}
		// system is not installed yet - init defaults
		}else{
			$schemas = $this->getPackagesSchemas();
			foreach( $gPreScan as $pkgGuid ) {
				if ( !empty( $schemas[ $pkgGuid ] ) ) {
					$loadPkgs[] = $schemas[$pkgGuid];
				}
			}
		}
		// load the pkg config.
		foreach( $loadPkgs as $loadPkg ) {
			$this->initPackage( $loadPkg,  TRUE, FALSE, FALSE);
		}
		// Now scan the setup files.
		foreach( $loadPkgs as $loadPkg ) {
			$this->initPackage( $loadPkg,  FALSE, TRUE, FALSE);
		}
		// Now load the plugins
		foreach( $loadPkgs as $loadPkg ) {
			$this->initPackage( $loadPkg,  FALSE, FALSE, TRUE);
		}
	}

	function initPackagePlugins( $pPkgGuid = NULL ){
		if( empty( $this->mPackagePluginsConfig ) ){
			$this->loadPackagePluginsConfig();
		}
		if( $plugins = $this->mPackagePluginsConfig ){
			foreach( $plugins as $plugin ){
				if( empty( $pPkgGuid ) || $plugin['package_guid'] == $pPkgGuid ){
					$this->initPlugin( $plugin );
				}
			}
		}
	}

	function getPackagePluginUrl( $pPlugin ) {
		$uri = NULL;
		switch( $pPlugin['path_type'] ){
		case 'package':
			$uri = constant( strtoupper( $pPlugin['package_guid'] ).'_PKG_URL' ); 
			break;
		case 'config':
			$uri = CONFIG_PKG_URL.$pPlugin['package_guid'].'/plugins/'.$pPlugin['guid'].'/';
			break;
		}
		return $uri;
	}

	function getPackagePluginPath( $pPlugin ){
		$path = NULL;
		// Did we get the whole config or just a guid?
		if (!is_array($pPlugin)) {
			$pPlugin = $this->getPluginConfig($pPlugin);
		}
		switch( $pPlugin['path_type'] ){
		case 'package':
			$path = constant( strtoupper( $pPlugin['package_guid'] ).'_PKG_PATH' ); 
			break;
		case 'config':
			$path = CONFIG_PKG_PATH.$pPlugin['package_guid'].'/plugins/'.$pPlugin['guid'].'/';
			break;
		}
		return $path;
	}

	function initPlugin( $pPlugin ){
		if( $path = $this->getPackagePluginPath( $pPlugin ) ){
			include_once( $path.$pPlugin['handler_file'] );
		}else{
			$gBitSystem->fatalError( 'Plugin '.$pPlugin['name'].'initialization failed in BitSystem::initPlugin. File location unknown.' );
		}
	}

	function configAllPackages(){
		if( empty( $this->mPackagesSchemas ) ){
			$this->loadPackagesSchemas();
		}
		if( $pkgs = $this->mPackagesSchemas ) {
			foreach( $pkgs as $pkg ){
				$registerHash = array(
					//for auto registered packages Registration Package Name = Package Directory Name
					'package_name' => $pkg['guid'],
					'package_path' => BIT_ROOT_PATH.$pkg['dir'].'/',
					'required_package' => !empty( $pkg['required'] )?TRUE:FALSE,
					'homable' => !empty( $pkg['homable'] )?TRUE:FALSE,
				);
				$this->configPackage( $registerHash );
			}
		}
	}


	// {{{=========================== Schema Getters ==============================

	/**
	 * loadPackagePluginsSchemas
	 */
	function loadPackagePluginSchemas( $pPkgDir, $pPkgGuid ){
		$ret = array();
		$paths[] = BIT_ROOT_PATH.$pPkgDir.'/plugins';
		$paths[] = CONFIG_PKG_PATH.$pPkgGuid.'/plugins';
		foreach( $paths as $path ){
			$ret = array_merge($ret, $this->loadPluginSchemasAtPath( $path ));
		}
		return $ret;
	}

	/**
	 * loadPluginSchemasAtPath
	 * @see BitInstaller::loadPackagePluginSchemas
	 */
	function loadPluginSchemasAtPath( $pPluginsPath ){
		$ret = array();
		if( is_dir( $pPluginsPath ) && $plugins = opendir( $pPluginsPath )) {
			while( FALSE !== ( $pluginDirName = readdir( $plugins ) ) ) {
				if ($pluginDirName != '.' && $pluginDirName != '..') {
					$pluginDirPath = $pPluginsPath.'/'.$pluginDirName;
					if( is_dir( $pluginDirPath ) && is_file( $pluginDirPath.'/schema.yaml' ) ) {
						$str = '#^'.addslashes( CONFIG_PKG_PATH ).'#';
						preg_match( $str, $pPluginsPath, $matches );
						$schema =  $this->loadPackageSchema($pluginDirPath, TRUE);
						$keys = array_keys( $schema ); // get the package guid 
						$guid = $keys[0];
						$schema[$guid]['path_type'] = !empty($matches)?'config':'package'; // keep track of where we found it to reduce scanning
						$ret = array_merge($ret, $schema);
					}
				}
			}
		}
		return $ret;
	}

	function getPackagePluginSchema( $pPlugin, $pPackage=NULL ){
		$schema = NULL;
		// is there any schema data? if not load it up
		if( empty( $this->mPackagesSchemas ) ){
			$this->loadPackagesSchemas();
		}
		// if we dont know the package try to find the plugin via traversal
		if( empty( $pPackage ) ){
			foreach( $this->mPackagesSchemas as $pkgGuid => $pkgData ){
				if( !empty( $pkgData['plugins'][$pPlugin] ) ){
					$schema = $this->mPackagesSchemas[$pkgGuid]['plugins'][$pPlugin];
				}
			}
		// we know the package lets get right to it
		}else{
			$schema = (!empty( $this->mPackagesSchemas[$pPackage]['plugins'][$pPlugin] )?$this->mPackagesSchemas[$pPackage]['plugins'][$pPlugin]:NULL);
		}
		return $schema;
	}

	function getPackagePluginSchemaValue( $pPlugin, $pProperty, $pPackage=NULL ){
		$schema = $this->getPackagePluginSchema( $pPlugin, $pPackage );
		return !empty( $schema[$pProperty] )?$schema[$pProperty]:NULL;
	}
	
	/**
	 * loadPackagesSchemas
	 *
	 * scans all packages and loads their schema.yaml file
	 */
	function loadPackagesSchemas( $pForce = FALSE ){
		$this->getPackagesSchemas( $pForce );

		// @TODO Deprecate this too - issue is in install pkg where it tries to reconcile permissions issues
		foreach( $this->mPackagesSchemas as $package=>$pkgHash ){
			$this->loadPermissionsSchema( $package, $pkgHash );
			/*
			if( !empty( $pkgHash['permissions'] ) ){
				foreach( $pkgHash['permissions'] as $perm => &$permHash ){
					$permHash['package'] = $package;
					$permHash['name'] = $perm;
					$this->mPermissionsSchema[$perm] = $permHash;
				}
			}
			*/
			if( !empty( $pkgHash['plugins'] ) ){
				foreach( $pkgHash['plugins'] as $guid => $pluginHash ){
					$pluginHash['plugin_guid'] = $guid;
					$this->loadPermissionsSchema( $package, $pluginHash );
				}
			}
		}
	}

	function loadPermissionsSchema( $package, $pHash ){
		if( !empty( $pHash['permissions'] ) ){
			foreach( $pHash['permissions'] as $perm => &$permHash ){
				$permHash['package'] = $package;
				$permHash['name'] = $perm;
				if( !empty( $pHash['plugin_guid'] ) ){
					$permHash['plugin_guid'] = $pHash['plugin_guid']; 
				}
				$this->mPermissionsSchema[$perm] = $permHash;
			}
		}
	}

	function getPackagesSchemas( $pForce = FALSE ){
		$ret = array();
		if( empty( $this->mPackagesSchemas ) || $pForce ){
			// gPreScan may hold a list of packages that must be loaded first
			global $gPreScan;
			if( !empty( $gPreScan ) && is_array( $gPreScan )) {
				foreach( $gPreScan as $pkgDir ) {
					$loadPkgs[] = $pkgDir;
				}
			}

			// load lib configs
			if( $pkgDir = opendir( BIT_ROOT_PATH )) {
				while( FALSE !== ( $dirName = readdir( $pkgDir ))) {
					if( $dirName != '..'  && $dirName != '.' && is_dir( BIT_ROOT_PATH . '/' . $dirName ) && $dirName != 'CVS' && preg_match( '/^\w/', $dirName )) {
						$loadPkgs[] = $dirName;
					}
				}
			}
			$loadPkgs = array_unique( $loadPkgs );

			// load the list of pkgs in the right order
			foreach( $loadPkgs as $loadPkg ) {
				// load the package schema
				if( $schema = $this->loadPackageSchema( $loadPkg ) ){
					$ret = array_merge( $ret, $schema );
				}
			}

			$this->mPackagesSchemas = $ret;
		}

		return $this->mPackagesSchemas;
	}

	function loadPackageSchema( $pPkgDir, $pIsPlugin = FALSE ){
		require_once( UTIL_PKG_PATH.'spyc/spyc.php' );

		if ($pIsPlugin) {
		  $scanFile = $pPkgDir.'/schema.yaml';
		} else {
		  $scanFile = BIT_ROOT_PATH.$pPkgDir.'/admin/schema.yaml';
		}
		if( file_exists( $scanFile ) ) {
			$pkgHash = Spyc::YAMLLoad( $scanFile );
			// modify the hash a little
			$keys = array_keys( $pkgHash ); // get the package guid 
			$guid = $keys[0];
			if (!$pIsPlugin) {
			  // assign the dir
			  $pkgHash[$guid]['dir'] = $pPkgDir;
			  // assign the path
			  $pkgHash[$guid]['path'] = BIT_ROOT_PATH.$pPkgDir.'/';
			}
		    // assign the guid
		    $pkgHash[$guid]['guid'] = $guid;
			// assign a name if none set
			if( empty( $pkgHash[$guid]['name'] ) ){
				$pkgHash[$guid]['name'] = ucfirst( $guid );
			}
			// load all plugin schemas for this package
			if( !$pIsPlugin ){
				if( $plugin_schemas = $this->loadPackagePluginSchemas( $pPkgDir, $guid ) ){
					if( empty($pkgHash[$guid]['plugins']) ){
						$pkgHash[$guid]['plugins'] = array();
					}
					$pkgHash[$guid]['plugins'] = array_merge( $pkgHash[$guid]['plugins'], $plugin_schemas );
				}
			}

			return $pkgHash;
		}

		return NULL;
	}

	function getPackageSchema( $pPackage ){
		// is there any schema data? if not load it up
		if( empty( $this->mPackagesSchemas ) ){
			$this->loadPackagesSchemas();
		}
		return (!empty( $this->mPackagesSchemas[$pPackage] )?$this->mPackagesSchemas[$pPackage]:NULL);
	}

	function getPackageSchemaValue( $pPackage, $pProperty ){
		if( empty( $this->mPackagesSchemas[$pPackage] ) ){
			$this->getPackageSchema( $pPackage, TRUE );
		}
		return !empty( $this->mPackagesSchemas[$pPackage][$pProperty] )?$this->mPackagesSchemas[$pPackage][$pProperty]:NULL;
	}

	function loadPackagesConfig( $pForce = FALSE ){
		$this->getPackagesConfig( $pForce );
		if( empty( $this->mPackages ) ){
			$this->mPackages = &$this->mPackagesConfig;
		}
		$this->defineActivePackage();
	}

	function getPackagesConfig( $pForce = FALSE ){
		if( empty( $this->mPackagesConfig ) || $pForce ){
			$query = "SELECT guid as guid_key, guid, version, homeable, active, required, dir, name, description 
						FROM `".BIT_DB_PREFIX."packages` p
						WHERE p.`active` = ?";
			if( $result = $this->mDb->getAssoc( $query, array('y') ) ){
				$this->mPackagesConfig = &$result;
			}
		}
		return $this->mPackagesConfig;
	}

	function getPackageConfig( $pPackage, $pForce = FALSE )
	{
		$ret = NULL;
		if( $pForce )
		{
			$ret = $this->loadPackageConfig( $pPackage, $pForce );
		}elseif( !empty( $this->mPackagesConfig[$pPackage] ) )
		{
			$ret = $this->mPackagesConfig[$pPackage]; 
		}
		return $ret;
	}

	function loadPackageConfig( $pPackage, $pForce = FALSE )
	{
		if( empty( $this->mPackagesConfig[$pPackage] ) || $pForce ){
			$query = "SELECT p.* 
						FROM `".BIT_DB_PREFIX."packages` p 
						WHERE p.`guid` = ? AND p.`active` = ?";
			if( $result = $this->mDb->query( $query, array( $pPackage, 'y' ) ) ){
				if( $row = $result->fetchRow() ){
					$this->mPackagesConfig[$pPackage] = $row;
				}
			}
		}
		return !empty( $this->mPackagesConfig[$pPackage] )?$this->mPackagesConfig[$pPackage]:NULL;
	}

	function getPluginConfig( $pPackagePlugin, $pForce = FALSE ){
		if( empty( $this->mPackagePluginsConfig[$pPackagePlugin] ) || $pForce ){
			$query = "SELECT * FROM `".BIT_DB_PREFIX."package_plugins` WHERE guid = ?";
			if( $result = $this->mDb->query( $query, array( $pPackagePlugin ) ) ){
				if( $row = $result->fetchRow() ){
					$this->mPackagePluginsConfig[$pPackagePlugin] = $row;
				}
			}
		}
		return !empty( $this->mPackagePluginsConfig[$pPackagePlugin] )? $this->mPackagePluginsConfig[$pPackagePlugin]:NULL;
	}

	function getPackageConfigValue( $pPackage, $pProperty, $pForce = FALSE ){
		if( empty( $this->mPackagesConfig[$pPackage] ) ){
			$this->getPackageConfig( $pPackage, $pForce );
		}
		return !empty( $this->mPackagesConfig[$pPackage][$pProperty] )?$this->mPackagesConfig[$pPackage][$pProperty]:NULL;
	}

	function getInstalledPackages( $pForce = FALSE ){
		if( empty( $this->mPackagesInstalled ) || $pForce ){
			$query = "SELECT guid as guid_key, guid, version, homeable, active, required, dir, name, description 
						FROM `".BIT_DB_PREFIX."packages` p";
			if( $ret = $this->mDb->getAssoc( $query ) ){
				$this->mPackagesInstalled = $ret;
			}
		}
		return $this->mPackagesInstalled;
	}

	function getInstalledPackageConfig( $pPackage, $pForce = FALSE ){
		if( empty( $this->mPackagesInstalled[$pPackage] ) || $pForce ){
			$this->getInstalledPackages( $pForce );
		}
		return !empty( $this->mPackagesInstalled[$pPackage] )? $this->mPackagesInstalled[$pPackage]:NULL;
	}

	function getInstalledPluginConfig( $pPackagePlugin, $pForce = FALSE ){
		if( empty( $this->mPackagePluginsInstalled[$pPackagePlugin] ) || $pForce ){
			$this->getInstalledPackagePlugins( $pForce );
		}
		return !empty( $this->mPackagePluginsInstalled[$pPackagePlugin] )? $this->mPackagePluginsInstalled[$pPackagePlugin]:NULL;
	}

	/// }}}

	// {{{=========================== Package Storage Methods ==============================

	/**
	 * stores/updates a single record in the package table
	 */
	function storePackage( &$pParamHash, $gAutoReload = TRUE ){
		if( $this->verifyPackageHash( $pParamHash ) ) {
			if ( !empty( $pParamHash['package_store'] )){
				$table = BIT_DB_PREFIX.'packages';
				if( !$this->isPackageInstalled( $pParamHash['guid'] ) ){
					$pParamHash['package_store']['guid'] = $pParamHash['guid'];
					$result = $this->mDb->associateInsert( $table, $pParamHash['package_store'] );
				}else{
					$locId = array( "guid" => $pParamHash['guid'] );
					$result = $this->mDb->associateUpdate( $table, $pParamHash['package_store'], $locId );
				}
				$this->getPackageConfig( $pParamHash['guid'], TRUE );
			}
		}
		return count( $this->mErrors ) == 0;
	}

	/** 
	 * verifies a data set for storage in the kernel2_Package table
	 * data is put into $pParamHash['package_store'] for storage
	 */
	function verifyPackageHash( &$pParamHash ){
		if( empty( $pParamHash['guid'] ) ){
			$this->mErrors['package'] = tra('A value for guid is required.');
		}
		if( empty( $pParamHash['dir'] ) ){
			$this->fatalError( 'Internal error: Unknown package directory' );
		}

		$pParamHash['package_store']['version'] = !empty( $pParamHash['version'] )?$pParamHash['version']:'0.0.0';
		$pParamHash['package_store']['homeable'] = (isset( $pParamHash['homeable'] ) && $pParamHash['homeable'] != TRUE)?'n':'y';
		$pParamHash['package_store']['active'] = (isset( $pParamHash['active'] ) && ( $pParamHash['active'] != TRUE || $pParamHash['active'] != 'y') )?'n':'y';
		$pParamHash['package_store']['required'] = (isset( $pParamHash['required'] ) && ( $pParamHash['required'] === TRUE || $pParamHash['required'] == 'y') )?'y':'n';
		$pParamHash['package_store']['name'] = !empty( $pParamHash['name'] )?$pParamHash['name']:ucfirst($pParamHash['guid']);
		$pParamHash['package_store']['description'] = !empty( $pParamHash['description'] )?$pParamHash['description']:NULL;
		$pParamHash['package_store']['dir'] = $pParamHash['dir'];

		return( count( $this->mErrors )== 0 );
	}


	function expungePackage( &$pPackageGuid ){
		$ret = FALSE;

		$query = "DELETE FROM `packages` WHERE `guid` = ?";
		if( $this->mDb->query( $query, array($pPackageGuid) ) ){
			$ret = TRUE;
		}

		return $ret;
	}

	function activatePackage( $pPackageGuid ){
		if( $this->isPackageInstalled( $pPackageGuid ) ){
			$storeHash = $this->getInstalledPackageConfig( $pPackageGuid );
			$storeHash['active'] = 'y';
			$this->storePackage( $storeHash );
		}
		return( count( $this->mErrors )== 0 );
	}

	function deactivatePackage( $pPackageGuid ){
		if( $this->isPackageInstalled( $pPackageGuid ) ){
			$storeHash = $this->getInstalledPackageConfig( $pPackageGuid );
			$storeHash['active'] = 'n';
			$this->storePackage( $storeHash );
		}
		return( count( $this->mErrors )== 0 );
	}

	/// }}}

	// {{{=========================== Plugin Storage Methods ==============================

	/**
	 * stores/updates a single record in the plugin table
	 */
	function storePlugin( &$pParamHash, $gAutoReload = TRUE ){
		if( $this->verifyPluginHash( $pParamHash ) ) {
			if ( !empty( $pParamHash['plugin_store'] )){
				$table = 'package_plugins';
				if( !$this->isPluginInstalled( $pParamHash['guid'] ) ){
					$pParamHash['plugin_store']['guid'] = $pParamHash['guid'];
					$result = $this->mDb->associateInsert( $table, $pParamHash['plugin_store'] );
				}else{
					$locId = array( "guid" => $pParamHash['guid'] );
					$result = $this->mDb->associateUpdate( $table, $pParamHash['plugin_store'], $locId );
				}
				$this->getPluginConfig( $pParamHash['guid'], TRUE );
			}
		}
		return count( $this->mErrors ) == 0;
	}

	/** 
	 * verifies a data set for storage in the kernel2_Plugin table
	 * data is put into $pParamHash['plugin_store'] for storage
	 */
	function verifyPluginHash( &$pParamHash ){
		if( empty( $pParamHash['guid'] ) ){
			$this->mErrors['plugin']['guid'] = tra('A value for guid is required.');
		}

		if( empty( $pParamHash['package_guid'] ) ){
			$this->mErrors['plugin']['package_guid'] = tra('A value for package_guid is required.');
		}

		if( empty( $pParamHash['handler_file'] ) ){
			$this->mErrors['plugin']['handler_file'] = tra('A value for handler_file is required.');
		}

		if( count( $this->mErrors )== 0 ){
			$path_type = $this->getPackagePluginSchemaValue( $pParamHash['guid'], 'path_type', $pParamHash['package_guid'] );

			$pParamHash['plugin_store']['package_guid'] = $pParamHash['package_guid'];
			$pParamHash['plugin_store']['version'] = !empty( $pParamHash['version'] )?$pParamHash['version']:'0.0.0';
			$pParamHash['plugin_store']['active'] = (isset( $pParamHash['active'] ) && ( $pParamHash['active'] != TRUE || $pParamHash['active'] != 'y') )?'n':'y';
		    $pParamHash['plugin_store']['required'] = (isset( $pParamHash['required'] ) && ( $pParamHash['required'] === TRUE || $pParamHash['required'] == 'y') )?'y':'n';
			$pParamHash['plugin_store']['name'] = !empty( $pParamHash['name'] )?$pParamHash['name']:ucfirst($pParamHash['guid']);
			$pParamHash['plugin_store']['description'] = !empty( $pParamHash['description'] )?$pParamHash['description']:NULL;
			$pParamHash['plugin_store']['path_type'] = !empty( $path_type )?$path_type:'package'; 		// if no path_type set we assume it came with the package
			$pParamHash['plugin_store']['handler_file'] = $pParamHash['handler_file'];
			$pParamHash['plugin_store']['pos'] = !empty( $pParamHash['pos'] )?$pParamHash['pos']:'1';
		}

		return( count( $this->mErrors )== 0 );
	}

	function expungePlugin( &$pPluginGuid ){
		$ret = FALSE;

		$query = "DELETE FROM `".BIT_DB_PREFIX."package_plugins` WHERE `guid` = ?";
		if( $this->mDb->query( $query, array($pPluginGuid) ) ){
			$ret = TRUE;
		}

		return $ret;
	}

	function isPluginActive( $pPluginGuid ) {
		return ($this->isPluginInstalled($pPluginGuid) &&
				$this->mPackagePluginsConfig[ $pPluginGuid ]['active'] == 'y');
	}

	function activatePlugin( $pPluginGuid ){
		if( $this->isPluginInstalled( $pPluginGuid ) ){
			$storeHash = $this->getPluginConfig( $pPluginGuid );
			$storeHash['active'] = 'y';
			$this->storePlugin( $storeHash );
		}
		return( count( $this->mErrors )== 0 );
	}

	function deactivatePlugin( $pPluginGuid ){
		if( $this->isPluginInstalled( $pPluginGuid ) ){
			$storeHash = $this->getPluginConfig( $pPluginGuid );
			$storeHash['active'] = 'n';
			$this->storePlugin( $storeHash );
		}
		return( count( $this->mErrors )== 0 );
	}

	// === isPluginInstalled
	/**
	 * check's if a package plugin is Installed
	 * @param $pPackagePluginGuid the guid of the package to test
	 * @return boolean
	 * @access public
	 */
	// @TODO this is a little problematic - its adding inactive plugins to config and config in places is expected to only carry active plugins
	function isPluginInstalled( $pPackagePluginGuid ){
		return !is_null( $this->getInstalledPluginConfig( $pPackagePluginGuid ) );
	}


	/// }}}
	
	// {{{=========================== Plugin Getters ==============================

	function getPackagePluginHandlers( $pAPIType, $pAPIGuid  ){
		$ret = NULL;
		if( empty( $this->mPackagePluginsHandlers ) ){
			$this->loadPackagePluginHandlers();
		}
		if( !empty( $this->mPackagePluginsHandlers[$pAPIType][$pAPIGuid] ) ){
			$ret = $this->mPackagePluginsHandlers[$pAPIType][$pAPIGuid];
		}
		return $ret;
	}

	// @TODO maybe all should load on first call
	function loadPackagePluginHandlers() {
		$ret = $bindVars = array();
		$query = "SELECT ppam.*, pp.guid, pp.package_guid, pp.path_type, pp.handler_file, pp.active, pp.pos 
					FROM `package_plugins_api_map` ppam 
					INNER JOIN `package_plugins` pp ON ( ppam.`plugin_guid` = pp.`guid` ) 
					INNER JOIN `packages` p ON p.`guid` = pp.`package_guid`
					WHERE pp.`active` = ? AND p.`active` = ? ORDER BY pp.`pos` ASC";
		$bindVars[] = 'y';
		$bindVars[] = 'y';
		if( $rslt = $this->mDb->getArray( $query, $bindVars ) ){
			// sort them
			foreach( $rslt as $row ){
				$this->mPackagePluginsHandlers[$row['api_type']][$row['api_hook']][] = $row;
			}
			$ret = $this->mPackagePluginsHandlers;
		}
		return $ret;
	}

	// === getPackagePluginsConfig
	/**
	 * gets the configuration of ONLY active packages
	 * and puts it in mPackagePluginsConfig
	 * @param $pForce forces a reload
	 */
	function getPackagePluginsConfig( $pForce = FALSE ){
		if( empty( $this->mPackagePluginsConfig ) || $pForce ){
			$query = "SELECT pp.`guid` as guid_key, pp.`guid`, pp.`package_guid`, pp.`version`, pp.`active`, pp.`required`, pp.`path_type`, pp.`handler_file`, pp.`name`, pp.`description`, pp.`pos` 
						FROM `".BIT_DB_PREFIX."package_plugins` pp 
						INNER JOIN `packages` p ON p.`guid` = pp.`package_guid`
						WHERE pp.`active` = ? AND p.`active` = ? ORDER BY pp.`pos` ASC";
			if( $result = $this->mDb->getAssoc( $query, array( 'y', 'y' ) ) ){
				$this->mPackagePluginsConfig = &$result;
			}
			// load up the handlers too
			$this->loadPackagePluginHandlers();
		}

		// vd( $this->mPackagePluginsHandlers );
		return $this->mPackagePluginsConfig;
	}

	function loadPackagePluginsConfig( $pForce = FALSE ){
		$this->getPackagePluginsConfig( $pForce );
	}

	// === getInstalledPackagePlugins
	/**
	 * this is slighly different than getPackagePluginsConfig
	 * it returns all installed packages, whether active or not
	 */
	function getInstalledPackagePlugins( $pForce = FALSE ){
		if( empty( $this->mPackagePluginsInstalled ) || $pForce ){
			$query = "SELECT pp.`guid` as guid_key, pp.`guid`, pp.`package_guid`, pp.`version`, pp.`active`, pp.`required`, pp.`path_type`, pp.`handler_file`, pp.`name`, pp.`description` 
						FROM `".BIT_DB_PREFIX."package_plugins` pp"; 
			if( $ret = $this->mDb->getAssoc( $query ) ){
				$this->mPackagePluginsInstalled = $ret;
			}
		}
		return $this->mPackagePluginsInstalled;
	}

	function getInstalledPackagePluginConfigValue( $pPluginGuid, $pProperty ){
		if( empty( $this->mPackagesPluginsConfig[$pPluginGuid] ) ){
			$this->getInstalledPackagePlugins();
		}
		return !empty( $this->mPackagePluginsInstalled[$pPluginGuid][$pProperty] )?$this->mPackagePluginsInstalled[$pPluginGuid][$pProperty]:NULL;
	}

	// === isPackagePluginActive
	/**
	 * check's if a package plugin is active.
	 * @param $pPackagePluginGuid the guid of the package to test
	 * @return boolean
	 * @access public
	 */
	function isPackagePluginActive( $pPluginGuid ) {
		return( $this->getPackagePluginConfigValue( $pPluginGuid, 'active' ) == 'y' );
	}

	function getPackagePluginConfigValue( $pPluginGuid, $pProperty ){
		if( empty( $this->mPackagesConfig[$pPluginGuid] ) ){
			$this->getPackageConfig( $pPluginGuid, TRUE );
		}
		return !empty( $this->mPackagePluginsConfig[$pPluginGuid][$pProperty] )?$this->mPackagePluginsConfig[$pPluginGuid][$pProperty]:NULL;
	}

	// }}}
	
	// {{{=========================== Plugin API Methods ==============================

	function storePluginAPI( $pAPIType, $pAPIGuid ){
		$table = BIT_DB_PREFIX.'package_plugins_api_hooks';
		// expunge before insert in case two packages try to declare the same hook
		$this->expungePluginAPI( $pAPIType, $pAPIGuid );
		$this->mDb->associateInsert( $table, array( 'api_type' => $pAPIType, 'api_hook' => $pAPIGuid ));
	}

	function expungePluginAPI( $pAPIType, $pAPIGuid ){
		$ret = FALSE;
		$query = "DELETE FROM `".BIT_DB_PREFIX."package_plugins_api_hooks` WHERE `api_type` = ? AND `api_hook` = ?";
		if( $this->mDb->query( $query, array( $pAPIType, $pAPIGuid) ) ){
			$ret = TRUE;
		}
		return $ret;
	}

	function storePluginAPIHandler( &$pParamHash ){ 
		if( $this->verifyPluginAPIHandler( $pParamHash ) ){
			$table = BIT_DB_PREFIX.'package_plugins_api_map';
			// delete replace
			$this->expungePluginAPIHandler( $pParamHash['plugin_guid'], $pParamHash['api_hook'], $pParamHash['api_type'] );
			$rslt = $this->mDb->associateInsert( $table, $pParamHash['store_api_handler'] );
		}
	}

	function verifyPluginAPIHandler( &$pParamHash ){
		if( empty( $pParamHash['plugin_guid'] ) ){
			$this->mErrors['plugin_api_callback']['plugin_guid'] = tra('A value for plugin_guid is required.');
		}else{
			$pParamHash['store_api_handler']['plugin_guid'] = $pParamHash['plugin_guid'];
		}
		if( empty( $pParamHash['api_hook'] ) ){
			$this->mErrors['plugin_api_callback']['api_hook'] = tra('A value for api_hook is required.');
		}else{
			$pParamHash['store_api_handler']['api_hook'] = $pParamHash['api_hook'];
		}
		if( empty( $pParamHash['api_type'] ) ){
			$this->mErrors['plugin_api_callback']['api_type'] = tra('A value for api_type is required.');
		}else{
			$pParamHash['store_api_handler']['api_type'] = $pParamHash['api_type'];
		}
		if( empty( $pParamHash['plugin_handler'] ) ){
			$this->mErrors['plugin_api_callback']['plugin_handler'] = tra('A value for plugin_handler is required.');
		}else{
			$pParamHash['store_api_handler']['plugin_handler'] = $pParamHash['plugin_handler'];
		}
		return( count( $this->mErrors )== 0 );
	}

	function expungePluginAPIHandler( $pPluginGuid, $pAPIGuid, $pAPIType ){
		$ret = FALSE;
		$query = "DELETE FROM `".BIT_DB_PREFIX."package_plugins_api_map` WHERE `plugin_guid` = ? AND `api_hook` = ? AND `api_type` = ?";
		if( $this->mDb->query( $query, array( $pPluginGuid, $pAPIGuid, $pAPIType ) ) ){
			$ret = TRUE;
		}
		return $ret;
	}

	function getPluginAPIHandler( $pAPIType, $pAPIGuid, $pPluginGuid ){
		$handlers = $this->getPackagePluginHandlers( $pAPIType, $pAPIGuid );
		foreach( $handlers as $handler ){
			if( $handler['plugin_guid'] == $pPluginGuid ){
				return $handler;
			}
		}
		return NULL;
	}

	/// }}}

	/**
	 * getDefaultPage 
	 * @TODO DEPRECATED SLATED FOR DELETE - THIS IS SERIOUSLY STUPID - THE DEFAULT PAGE IS ALWAYS INDEX BECAUSE INDEX USES bit_index TO LOAD WHATEVER PACKAGE 
	 * 
	 * @access public
	 * @return URL of site homepage 
	 */
	function getDefaultPage() {
		return BIT_ROOT_URL;
		// return $this->getIndexPage( $this->getConfig( "bit_index" ) );
	}

	/**
	 * getIndexPage
	 *
	 * Returns the page for the given type
	 * defaults to the site homepage
	 *
	 * @access public
	 * @return URL of page by index type
	 */
	function getIndexPage( $pIndexType = NULL ){
		global $userlib, $gBitUser, $gBitSystem;
		$pIndexType = !is_null( $pIndexType )? $pIndexType : $this->getConfig( "bit_index" );
		$url = '';
		if( $pIndexType == 'group_home') {
			// See if we have first a user assigned default group id, and second a group default system preference
			if( !$gBitUser->isRegistered() && ( $group_home = $gBitUser->getGroupHome( ANONYMOUS_GROUP_ID ))) {
			} elseif( @$this->verifyId( $gBitUser->mInfo['default_group_id'] ) && ( $group_home = $gBitUser->getGroupHome( $gBitUser->mInfo['default_group_id'] ))) {
			} elseif( $this->getConfig( 'default_home_group' ) && ( $group_home = $gBitUser->getGroupHome( $this->getConfig( 'default_home_group' )))) {
			}

			if( !empty( $group_home )) {
				if( $this->verifyId( $group_home ) ) {
					$url = BIT_ROOT_URL."index.php".( !empty( $group_home ) ? "?content_id=".$group_home : "" );
				// wiki dependence - NO bad idea
				// } elseif( strpos( $group_home, '/' ) === FALSE ) {
				// 	$url = BitPage::getDisplayUrl( $group_home );
				} elseif(  strpos( $group_home, 'http://' ) === FALSE ){
					$url = BIT_ROOT_URL.$group_home;
				} else {
					$url = $group_home;
				}
			}
		} elseif( $pIndexType == 'my_page' || $pIndexType == 'my_home' || $pIndexType == 'user_home'  ) {
			// TODO: my_home is deprecated, but was the default for BWR1. remove in DILLINGER - spiderr
			if( $gBitUser->isRegistered() ) {
				if( !$gBitUser->isRegistered() ) {
					$url = USERS_PKG_URL.'login.php';
				} else {
					if( $pIndexType == 'my_page' ) {
						$url = $gBitSystem->getConfig( 'users_login_homepage', USERS_PKG_URL.'my.php' );
						if( $url != USERS_PKG_URL.'my.php' && strpos( $url, 'http://' ) === FALSE ){
							// the safe assumption is that a custom path is a subpath of the site 
							// append the root url unless we have a fully qualified uri
							$url = BIT_ROOT_URL.$url;
						}
					} elseif( $pIndexType == 'user_home' ) {
						$url = $gBitUser->getDisplayUrl();
					} else {
						$users_homepage = $gBitUser->getPreference( 'users_homepage' );
						if( isset( $users_homepage ) && !empty( $users_homepage )) {
							if( strpos($users_homepage, '/') === FALSE ) {
								$url = BitPage::getDisplayUrl( $users_homepage );
							} else {
								$url = $users_homepage;
							}
						}
					}
				}
			} else {
				$url = USERS_PKG_URL . 'login.php';
			}
		} elseif( in_array( $pIndexType, array_keys( $gBitSystem->mPackages ) ) ) {
			$work = strtoupper( $pIndexType ).'_PKG_URL';
			if (defined("$work")) {
				$url = constant( $work );
			}

			/* this was commented out with the note that this can send requests to inactive packages - 
			 * that should only happen if the admin chooses to point to an inactive pacakge.
			 * commenting this out however completely breaks the custom uri home page feature, so its
			 * turned back on and caviate admin - if the problem is more severe than it seems then 
			 * get in touch on irc and we'll work out a better solution than commenting things on and off -wjames5
			 */
		} elseif( !empty( $pIndexType ) ) {
			$url = BIT_ROOT_URL.$pIndexType;
		}

		// if no special case was matched above, default to users' my page
		if( empty( $url ) ) {
			if( $this->isPackageActive( 'wiki' ) ) {
				$url = WIKI_PKG_URL;
			} elseif( !$gBitUser->isRegistered() ) {
				$url = USERS_PKG_URL . 'login.php';
			} else {
				$url = USERS_PKG_URL . 'my.php';
			}
		}

		if( strpos( $url, 'http://' ) === FALSE ) {
			$url = preg_replace( "#//#", "/", $url );
		}

		return $url;
	}
	// === setOnloadScript
	/**
	 * add javascript to the <body onload> attribute 
	 *
	 * @param string $pJavascript javascript to be added
	 * @return none
	 * @access public
	 */
	function setOnloadScript( $pJavscript ) {
		array_push( $this->mOnload, $pJavscript );
	}
	// === setOnunloadScript
	/**
	 * add javascript to the <body onunload> attribute 
	 *
	 * @param string $pJavascript javascript to be added
	 * @return none
	 * @access public
	 */
	function setOnunloadScript( $pJavscript ) {
		array_push( $this->mOnunload, $pJavscript );
	}
	// === getBrowserTitle
	/**
	 * get the title of the browser
	 *
	 * @return title string
	 * @access public
	 */
	function getBrowserTitle() {
		global $gPageTitle;
		return( $gPageTitle );
	}
	// === setBrowserTitle
	/**
	 * set the title of the browser
	 *
	 * @param string $ pTitle title to be used
	 * @return none
	 * @access public
	 */
	function setBrowserTitle( $pTitle ) {
		global $gBitSmarty, $gPageTitle;
		$gPageTitle = $pTitle;
		$gBitSmarty->assign( 'browserTitle', $pTitle );
		$gBitSmarty->assign( 'gPageTitle', $pTitle );
	}

	/*static*/
	function genPass() {
		$vocales = "aeiou";
		$consonantes = "bcdfghjklmnpqrstvwxyz123456789";
		$r = '';
		for( $i = 0; $i < 8; $i++ ) {
			if( $i % 2 ) {
				$r .= $vocales{rand( 0, strlen( $vocales ) - 1 )};
			} else {
				$r .= $consonantes{rand( 0, strlen( $consonantes ) - 1 )};
			}
		}
		return $r;
	}

	// === lookupMimeType
	/**
	 * given an extension, return the mime type
	 *
	 * @param string $pExtension is the extension of the file or the complete file name
	 * @return mime type of entry and populates $this->mMimeTypes with existing mime types
	 * @access public
	 */
	function lookupMimeType( $pExtension ) {

		$this->loadMimeTypes();
		if( preg_match( "#\.[0-9a-z]+$#i", $pExtension )) {
			$pExtension = substr( $pExtension, ( strrpos( $pExtension, '.' ) + 1 ));
		}
		// rfc1341 - mime types are case insensitive.
		$pExtension = strtolower( $pExtension );

		return( !empty( $this->mMimeTypes[$pExtension] ) ? $this->mMimeTypes[$pExtension] : 'application/binary' );
	}

	// === loadMimeTypes
	/**
	 * given an extension, return the mime type
	 *
	 * @param string $pExtension is the extension of the file or the complete file name
	 * @return mime type of entry and populates $this->mMimeTypes with existing mime types
	 * @access public
	 */
	function loadMimeTypes() {
		if( empty( $this->mMimeTypes )) {
			// use bitweavers mime.types file to ensure everyone has our set unless user forces his own.
			if( defined( 'MIME_TYPES' ) && is_file( MIME_TYPES )) {
				$mimeFile = MIME_TYPES;
			} else {
				$mimeFile = KERNEL_PKG_PATH.'admin/mime.types';
			}

			$this->mMimeTypes = array();
			if( $fp = fopen( $mimeFile,"r" ) ) {
				while( FALSE != ( $line = fgets( $fp, 4096 ) ) ) {
					if( !preg_match( "/^\s*(?!#)\s*(\S+)\s+(?=\S)(.+)/", $line, $match ) ) {
						continue;
					}
					$tmp = preg_split( "/\s/",trim( $match[2] ) );
					foreach( $tmp as $type ) {
						$this->mMimeTypes[strtolower( $type )] = $match[1];
					}
				}
				fclose( $fp );
			}
		}
	}

	// === verifyFileExtension
	/**
	 * given a file and optionally desired name, return the correctly extensioned file and mime type
	 *
	 * @param string $pFile is the actual file to inspect for magic numbers to determine type
	 * @param string $pFileName is the desired name the file. This is optional in the even the pFile is non-extensioned, as is the case with file uploads
	 * @return corrected file name and mime type
	 * @access public
	 */
	function verifyFileExtension( $pFile, $pFileName=NULL ) {
		$this->loadMimeTypes();
		if( empty( $pFileName ) ) {
			$pFileName = basename( $pFile );
			$ret = $pFile;
		} else {
			$ret = $pFileName;
		}
		$verifyMime = $this->verifyMimeType( $pFile );
		if( strrpos( $pFileName, '.' ) ) {
			$extension = substr( $pFileName, strrpos( $pFileName, '.' ) + 1 );
			$lookupMime = $this->lookupMimeType( $extension );
		} else {
			// extensionless file uploaded, get ready to add an extension
			$lookupMime = '';
			$pFileName .= '.';
		}

		// if $verifyMime turns out to be 'octet-stream' and the lookupMimeType is a video file, we'll allow the video filetype and extenstion
		// if we don't do this, most uploaded videos are changed to have a '.bin' extenstion which is very annoying.
		if( $verifyMime == 'application/octet-stream' && preg_match( "/^video/", $lookupMime )) {
			$verifyMime = $lookupMime;
		} elseif( $lookupMime != $verifyMime ) {
			if( $mimeExt = array_search( $verifyMime, $this->mMimeTypes ) ) {
				$ret = substr( $pFileName, 0, strrpos( $pFileName, '.' ) + 1 ).$mimeExt;
			}
		}

		// if we still don't have an extension, we'll simply append a 'bin'
		if( preg_match( "/\.$/", $pFileName )) {
			$pFileName .= "bin";
		}

		return array( $ret, $verifyMime );
	}


	// === verifyMimeType
	/**
	 * given a file, return the mime type
	 *
	 * @param string $pExtension is the extension of the file or the complete file name
	 * @return mime type of entry and populates $this->mMimeTypes with existing mime types
	 * @access public
	 */
	function verifyMimeType( $pFile ) {
		$mime = NULL;
		if( file_exists( $pFile ) ) {
			if( function_exists( 'finfo_open' ) ) {
				if( is_windows() && defined( 'PHP_MAGIC_PATH' ) && is_readable( PHP_MAGIC_PATH )) {
					$finfo = finfo_open( FILEINFO_MIME, PHP_MAGIC_PATH );
				} else {
					$finfo = finfo_open( FILEINFO_MIME );
				}
				$mime = finfo_file( $finfo, $pFile );
				finfo_close( $finfo );
			} else {
				if( function_enabled( "escapeshellarg" ) && function_enabled( "exec" )) {
					$mime = exec( trim( 'file -bi ' . escapeshellarg( $pFile )));
				}
			}
			if( empty( $mime ) ) {
				$mime = $this->lookupMimeType( substr( $pFile, strrpos( $pFile, '.' ) + 1 ) );
			}
			if( $len = strpos( $mime, ';' )) {
				$mime = substr( $mime, 0, $len );
			}
		}
		return $mime;
	}


	/**
	 * * Prepend $pPath to the include path
	 * \static
	 */
	function prependIncludePath( $pPath ) {
		if( !function_exists( "get_include_path" ) ) {
			include_once( UTIL_PKG_PATH . "PHP_Compat/Compat/Function/get_include_path.php" );
		}
		if( !defined( "PATH_SEPARATOR" ) ) {
			include_once( UTIL_PKG_PATH . "PHP_Compat/Compat/Constant/PATH_SEPARATOR.php" );
		}
		if( !function_exists( "set_include_path" ) ) {
			include_once( UTIL_PKG_PATH . "PHP_Compat/Compat/Function/set_include_path.php" );
		}

		$include_path = get_include_path();
		if( $include_path ) {
			$include_path = $pPath . PATH_SEPARATOR . $include_path;
		} else {
			$include_path = $pPath;
		}
		return set_include_path( $include_path );
	}

	/**
	 * * Append $pPath to the include path
	 * \static
	 */
	function appendIncludePath( $pPath ) {
		if( !function_exists( "get_include_path" ) ) {
			include_once(UTIL_PKG_PATH . "PHP_Compat/Compat/Function/get_include_path.php");
		}
		if( !defined("PATH_SEPARATOR" ) ) {
			include_once(UTIL_PKG_PATH . "PHP_Compat/Compat/Constant/PATH_SEPARATOR.php");
		}
		if( !function_exists( "set_include_path" ) ) {
			include_once(UTIL_PKG_PATH . "PHP_Compat/Compat/Function/set_include_path.php");
		}

		$include_path = get_include_path();
		if( $include_path ) {
			$include_path .= PATH_SEPARATOR . $pPath;
		} else {
			$include_path = $pPath;
		}
		return set_include_path( $include_path );
	}

	/* Check that everything is set up properly
	 * \static
	 */
	function checkEnvironment() {
		static $checked, $gTempDirs;

		if( $checked ) {
			return;
		}

		$errors = '';

		$docroot = BIT_ROOT_PATH;

        /*	this seems to prevent bw from running on servers where sessions work perfectly, 
        	yet /var/lib/php/ is writeable only by php, not by bw (which is better)
        	it seems to be enough to set temp in config/config_inc.php for a writable dir
        	if session *actually* don't work - other problem
        	the installer has similar code which is also not used anymore
        	
		if( ini_get( 'session.save_handler' ) == 'files' ) {
			$save_path = ini_get( 'session.save_path' );

			if( empty( $save_path ) ) {
				$errors .= "The session.save_path variable is not setup correctly (its empty).\n";
			} else {
				if( strpos( $save_path, ";" ) !== FALSE ) {
					$save_path = substr( $save_path, strpos( $save_path, ";" )+1 );
				}
				$open = ini_get( 'open_basedir' );
				if( !@is_dir( $save_path ) && empty( $open ) ) {
					$errors .= "The directory '$save_path' does not exist or PHP is not allowed to access it (check open_basedir entry in php.ini).\n";
				} elseif( !bw_is_writeable( $save_path ) ) {
					$errors .= "The directory '$save_path' is not writeable.\n";
				}
			}

			if( $errors ) {
				$save_path = get_temp_dir();

				if( is_dir( $save_path ) && bw_is_writeable( $save_path ) ) {
					ini_set( 'session.save_path', $save_path );

					$errors = '';
				}
			}
		}
        */

		$wwwuser = '';
		$wwwgroup = '';

		if( is_windows() ) {
			if( strpos( $_SERVER["SERVER_SOFTWARE"],"IIS" ) && isset( $_SERVER['COMPUTERNAME'] ) ) {
				$wwwuser = 'IUSR_'.$_SERVER['COMPUTERNAME'];
				$wwwgroup = 'IUSR_'.$_SERVER['COMPUTERNAME'];
			} else {
				$wwwuser = 'SYSTEM';
				$wwwgroup = 'SYSTEM';
			}
		}

		if( function_exists( 'posix_getuid' ) ) {
			$userhash = @posix_getpwuid( @posix_getuid() );
			$group = @posix_getpwuid( @posix_getgid() );
			$wwwuser = $userhash ? $userhash['name'] : FALSE;
			$wwwgroup = $group ? $group['name'] : FALSE;
		}

		if( !$wwwuser ) {
			$wwwuser = 'nobody (or the user account the web server is running under)';
		}

		if( !$wwwgroup ) {
			$wwwgroup = 'nobody (or the group account the web server is running under)';
		}

		$permFiles[] = $this->getConfig( 'site_temp_dir', BIT_ROOT_PATH.'temp/' );
		$permFiles[] = STORAGE_PKG_PATH;

		foreach( $permFiles as $file ) {
			$present = FALSE;
			// Create directories as needed
			$target = $file;
			if( preg_match( '/.*\/$/', $target ) ) {
				// we have a directory
				if( !is_dir( $target ) ) {
					mkdir_p( $target, 02775 );
				}
				// Check again and report problems
				if( !is_dir( $target ) ) {
					if( !is_windows() ) {
						$errors .= "
							<p>The directory <strong style='color:red;'>$target</strong> does not exist. To create the directory, execute a command such as:</p>
							<pre>\$ mkdir -m 777 $target</pre>
							";
					} else {
						$errors .= "<p>The directory <strong style='color:red;'>$target</strong> does not exist. Create the directory $target before proceeding</p>";
					}
				} else {
					$present = TRUE;
				}
			} elseif( !file_exists( $target ) ) {
				if( !is_windows() ) {
					$errors .= "<p>The file <b style='color:red;'>$target</b> does not exist. To create the file, execute a command such as:</p>
						<pre>
						\$ touch $target
						\$ chmod 777 $target
						</pre>
						";
				} else {
					$errors .= "<p>The file <b style='color:red;'>$target</b> does not exist. Create a blank file $target before proceeding</p>";
				}
			} else {
				$present = TRUE;
			}

			// chmod( $target, 02775 );
			if( $present && ( !bw_is_writeable( $target ))) {
				if( !is_windows() ) {
					$errors .= "<p><strong style='color:red;'>$target</strong> is not writeable by $wwwuser. To give $wwwuser write permission, execute a command such as:</p>
					<pre>\$ chmod 777 $target</pre>";
				} else {
					$errors .= "<p><b style='color:red;'>$target</b> is not writeable by $wwwuser. Check the security of the file $target before proceeding</p>";
				}
			}
			//if (!is_dir("$docroot/$dir")) {
			//	$errors .= "The directory '$docroot$dir' does not exist.\n";
			//} else if (!bw_is_writeable("$docroot/$dir")) {
			//	$errors .= "The directory '$docroot$dir' is not writeable by $wwwuser.\n";
			//}
		}

		if( $errors ) {
			$PHP_CONFIG_FILE_PATH = PHP_CONFIG_FILE_PATH;

			ob_start();
			phpinfo (INFO_MODULES);
			$httpd_conf = 'httpd.conf';

			if( preg_match( '/Server Root<\/b><\/td><td\s+align="left">([^<]*)</', ob_get_contents(), $m )) {
				$httpd_conf = $m[1] . '/' . $httpd_conf;
			}

			ob_end_clean();

			print "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"DTD/xhtml1-strict.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">
	<head>
		<title>bitweaver setup problems</title>
		<meta http-equiv=\"Pragma\" content=\"no-cache\" />
		<meta http-equiv=\"Expires\" content=\"-1\" />
	</head>
	<body>
		<h1 style=\"color:red;\">bitweaver is not properly set up:</h1>
		<blockquote>
			$errors
		</blockquote>";
			if( !$this->isLive() ) {
				if( !is_windows() ) {
					print "<p>Proceed to the installer <strong>at <a href=\"".BIT_ROOT_URL."install/install.php\">".BIT_ROOT_URL."install/install.php</a></strong> after you run the command.";
				} else {
					print "<p>Proceed to the installer <strong>at <a href=\"".BIT_ROOT_URL."install/install.php\">".BIT_ROOT_URL."install/install.php</a></strong> after you have corrected the identified problems.";
				}
				print "<br />Consult the bitweaver <a href='http://www.bitweaver.org/wiki/index.php?page=Technical_Documentation'>Technical Documentation</a> if you need more help.</p></body></html>";
			}

			exit;
		}

		$checked = TRUE;
	}

	/**
	 * isLive returns status of the IS_LIVE constant from config/config_inc.php
	 * 
	 * @access public
	 * @return TRUE if IS_LIVE is defined and set to a non empty value, else FALSE
	 */
	function isLive() {
		return( defined( 'IS_LIVE' ) && IS_LIVE );
	}

	/**
	 * storeVersion will store the version number of a given package
	 * 
	 * @param array $pPackage Name of package - if not given, bitweaver_version will be stored
	 * @param array $pVersion Version number
	 * @access public
	 * @return TRUE on success, FALSE on failure
	 */
	function storeVersion( $pPackage = NULL, $pVersion ) {
		global $gBitSystem;
		$ret = FALSE;
		if( !empty( $pVersion ) && $this->validateVersion( $pVersion ) && $this->isPackageInstalled( $pPackage )) {
			$config = $this->getInstalledPackageConfig( $pPackage );
			$config['version'] = $pVersion; 
			$this->storePackage( $config );
		}
		return( count( $this->mErrors ) == 0 );
	}
	
	/**
	 * storePluginVersion will store the version number of a given package
	 * 
	 * @param array $pPlugin Name of plugin - if not given, bitweaver_version will be stored
	 * @param array $pVersion Version number
	 * @access public
	 * @return TRUE on success, FALSE on failure
	 */
	function storePluginVersion( $pPlugin, $pVersion ) {
		global $gBitSystem;
		$ret = FALSE;
		if( !empty( $pVersion ) && $this->validateVersion( $pVersion ) && $gBitSystem->isPluginInstalled($pPlugin) ) {
			$config = $this->getPluginConfig( $pPlugin );
			$config['version'] = $pVersion; 
			$this->storePlugin( $config );
			$ret = TRUE;
		}
		return $ret;
	}

	/**
	 * getVersion will fetch the version number of a given package
	 * 
	 * @param array $pPackage Name of package - if not given, bitweaver_version will be stored
	 * @param array $pVersion Version number
	 * @access public
	 * @return version number on success
	 */
	function getVersion( $pPackage = NULL, $pDefault = '0.0.0' ) {
		global $gBitSystem;
		if( empty( $pPackage )) {
			$config = 'bitweaver_version';
			return $gBitSystem->getConfig( 'bitweaver_version', $pDefault );
		} elseif( !empty( $this->mPackagesConfig[$pPackage] ) ){
			return $this->mPackagesConfig[$pPackage]['version'];
		}
		return NULL;
	}

	/**
	 * getLatestUpgradeVersion will fetch the greatest upgrade number for a given package
	 * 
	 * @param array $pPackage package we want to fetch the latest version number for
	 * @access public
	 * @return string greatest upgrade number for a given package
	 */
	function getLatestUpgradeVersion( $pPackage ) {
		$ret = '0.0.0';
		if( !empty( $pPackage )) {
			$dir = constant( strtoupper( $pPackage )."_PKG_PATH" )."admin/upgrades/";
			if( is_dir( $dir ) && $upDir = opendir( $dir )) {
				while( FALSE !== ( $file = readdir( $upDir ))) {
					if( is_file( $dir.$file )) {
						$upVersion = str_replace( ".php", "", $file );
						// we only want to update $ret if the version of the file is greater than the previous one
						if( $this->validateVersion( $upVersion ) && version_compare( $ret, $upVersion, "<" )) {
							$ret = $upVersion;
						}
					}
				}
			}
		}
		return(( $ret == '0.0.0' ) ? FALSE : $ret );
	}
	
	function getUpgradablePackages(){
		$ret = array();
		$config = $this->getInstalledPackages();
		$schemas = $this->getPackagesSchemas();
		foreach( $config as $guid => $pkg ) {
			if( !empty( $schemas[$guid] ) ){
				if( version_compare( $pkg['version'], $schemas[$guid]['version'], "<" )) {
					$ret[$guid] = $pkg;
					$ret[$guid]['info'] = array(
						'version' => $pkg['version'],
						'upgrade' => $schemas[$guid]['version']
					);
				}
			}
			// @TODO
			// its possible there is a package in the table 
			// but the pkg code as been removed a schema can not be found
			// do something about cleaning this up?
		}
		return $ret;
	}
	
	/**
	 * getPluginVersion will fetch the version number of a given plugin
	 * 
	 * @param array $pPlugin Name of plugin - if not given, bitweaver_version will be stored
	 * @param array $pVersion Version number
	 * @access public
	 * @return version number on success
	 */
	function getPluginVersion( $pPlugin, $pDefault = '0.0.0' ) {
		global $gBitSystem;
		if( !($ret = $this->getInstalledPackagePluginConfigValue( $pPlugin, 'version' )) ){
			$ret = $pDefault;
		}
		return $ret;
	}
	
	function getUpgradablePlugins(){
		$ret = array();
		$config = $this->getInstalledPackagePlugins();
		$schemas = $this->getPackagesSchemas();
		foreach( $schemas as $pkgGuid => $pkg ){
			if( !empty( $pkg['plugins'] ) ){
				foreach( $pkg['plugins'] as $guid => $plugin ) {
					if( $this->isPackageInstalled( $guid ) ){
						// gracefully deal with plugins which have failed to specify a version
						$plugin['version'] = empty( $plugin['version'] ) || is_null($plugin['version'])?'0.0.0':$plugin['version'];

						if( version_compare( $config[$guid]['version'], $plugin['version'], "<" )) {
							$ret[$guid] = $config[$guid];
							$ret[$guid]['info'] = array(
								'version' => $config[$guid]['version'],
								'upgrade' => $plugin['version']
							);
						}
					}
				}
			}
		}
		return $ret;
	}

	/**
	 * validateVersion 
	 * 
	 * @param array $pVersion 
	 * @access public
	 * @return TRUE on success, FALSE on failure
	 */
	function validateVersion( $pVersion ) {
		return( preg_match( "/^(\d+\.\d+\.\d+)(-dev|-alpha|-beta|-pl|-RC\d+)?$/", $pVersion ));
	}

	/**
	 * verifyRequirements 
	 * 
	 * @param array $pReqHash 
	 * @access public
	 * @return TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function verifyRequirements( &$pReqHash ) {
		if( !empty( $pReqHash ) && is_array( $pReqHash )) {
			foreach( $pReqHash as $pkg => $versions ) {
				if( empty( $versions['min'] )) {
					$this->mErrors['version_min'] = "You have to provide a minimum version number for the $pkg requirement. If you just want the required package to be present, please use 0.0.0 as minimum version.";
				} elseif( !$this->validateVersion( $versions['min'] )) {
					$this->mErrors['version_min'] = "Please make sure you use a valid minimum version number for the $pkg requirement.";
				} elseif( !empty( $versions['max'] )) {
					if( !$this->validateVersion( $versions['max'] )) {
						$this->mErrors['version_max'] = "Please make sure you use a valid maximum version number for the $pkg requirement.";
					} elseif( version_compare( $versions['min'], $versions['max'], '>=' )) {
						$this->mErrors['version_max'] = "Please make sure the maximum version is greater than the minimum version for the $pkg requirement.";
					}
				}
			}
		} else {
			$this->mErrors['deps'] = "If you want to register requirements, please do so with a valid requirement hash.";
		}

		// since this should only show up when devs are working, we'll simply display the output:
		if( !empty( $this->mErrors )) {
			vd( $this->mErrors );
			bt();
		}

		return( count( $this->mErrors ) == 0 );
	}

	/**
	 * getRequirements 
	 * 
	 * @param array $pPackage 
	 * @access public
	 * @return array of package requirements
	 */
	function getRequirements( $pPackage ) {
		$ret = array();
		if( !empty( $pPackage )) {
			if( $config = $this->getPackageSchema($pPackage) ){
				return !empty( $config['requirements'] )?$config['requirements']:NULL;
			}
		}
		return $ret;
	}

	function getNewRequiredPackages(){
		$ret = array();
		$schemas = $this->getPackagesSchemas();
		foreach( $schemas as $guid=>$package ){
			if( $this->isPackageRequired($guid) && !$this->isPackageInstalled($guid) ){
				$ret[$guid] = $package;
			}
		}
		return $ret;
	}

	/**
	 * calculateRequirements will calculate all requirements and return a hash of the results
	 * 
	 * @param boolean $pInstallVersion Use the actual installed version instead of the version that will be in bitweaver after the upgrade
	 * @access public
	 * @return boolean TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function calculateRequirements( $pInstallVersion = FALSE ) {
		$ret = array();
		// first we gather all version information.
		foreach( array_keys( $this->getPackagesConfig() ) as $package ) {
			if( $this->isPackageInstalled( $package )) {

				// get the latest upgrade version, since this is the version the package will be at after install
				if( $pInstallVersion || !$version = $this->getLatestUpgradeVersion( $package )) {
					$version = $this->getVersion( $package );
				}
				$installed[$package] = $version;

				if( $this->isPackageActive( $package )) {
					if( $deps = $this->getRequirements( $package )) {
						$requirements[$package] = $deps;
					}
					$inactive[$package] = FALSE;
				} else {
					$inactive[$package] = TRUE;
				}
			}
		}

		if( !empty( $requirements )) {
			foreach( $requirements as $package => $deps ) {
				foreach( $deps as $depPackage => $depVersion ) {
					$hash = array(
						'package'          => $package,
						'package_version'  => $installed[$package],
						'requires'         => $depPackage,
						'required_version' => $depVersion,
					);

					if( !empty( $installed[$depPackage] )) {
						$hash['version']['available'] = $installed[$depPackage];
					}

					if( empty( $installed[$depPackage] )) {
						$hash['result'] = 'missing';
					} elseif( version_compare( $depVersion['min'], $installed[$depPackage], '>' )) {
						$hash['result'] = 'min_dep';
					} elseif( !empty( $depVersion['max'] ) && version_compare( $depVersion['max'], $installed[$depPackage], '<' )) {
						$hash['result'] = 'max_dep';
					} elseif( isset( $inactive[$depPackage] ) && $inactive[$depPackage] ) {
						$hash['result'] = 'inactive';
					} else {
						$hash['result'] = 'ok';
					}

					$ret[] = $hash;
				}
			}
		}

		return $ret;
	}

	/**
	 * drawRequirementsGraph Will draw a requirement graph if PEAR::Image_GraphViz is installed
	 * 
	 * @param boolean $pInstallVersion Use the actual installed version instead of the version that will be in bitweaver after the upgrade
	 * @param string $pFormat dot output format
	 * @param string $pCommand dot or neato
	 * @access public
	 * @return boolean TRUE on success, FALSE on failure - mErrors will contain reason for failure
	 */
	function drawRequirementsGraph( $pInstallVersion = FALSE, $pFormat = 'png', $pCommand = 'dot' ) {
		global $gBitSmarty, $gBitThemes;

		// only do this if we can load PEAR GraphViz interface
		if( @include_once( 'Image/GraphViz.php' )) {
			ksort( $this->mPackages );
			$deps = $this->calculateRequirements( $pInstallVersion );
			$delKeys = $matches = array();

			// crazy manipulation of hash to remove duplicate version matches.
			// we do this that we can use double headed arrows in the graph below.
			foreach( $deps as $key => $req ) {
				foreach( $deps as $k => $d ) {
					if( $req['requires'] == $d['package'] && $req['package'] == $d['requires'] && $req['result'] == 'ok' && $d['result'] == 'ok' ) {
						$deps[$key]['dir'] = 'both';
						$matches[$key] = $k;
					}
				}
			}

			// get duplicates
			foreach( $matches as $key => $match ) {
				unset( $delKeys[$match] );
				$delKeys[$key] = $match;
			}

			// remove dupes from hash
			foreach( $delKeys as $key ) {
				unset( $deps[$key] );
			}

			// start drawing stuff
			$graph = new Image_GraphViz( TRUE, $gBitThemes->getGraphvizGraphAttributes(), 'Requirements', TRUE );

			$fromattributes = $toattributes = $gBitThemes->getGraphvizNodeAttributes();

			foreach( $deps as $node ) {
				//$fromNode = ucfirst( $node['package'] )."\n".$node['package_version'];
				//$toNode   = ucfirst( $node['requires'] )."\n".$node['required_version']['min'];

				$fromNode = ucfirst( $node['package'] );
				$toNode   = ucfirst( $node['requires'] );

				switch( $node['result'] ) {
				case 'max_dep':
					$edgecolor = 'chocolate3';
					$label     = 'Maximum version\nexceeded';
					$toNode   .= "\n".$node['required_version']['min']." - ".$node['required_version']['max'];
					$toattributes['fillcolor'] = 'khaki';
					break;
				case 'min_dep':
					$edgecolor = 'crimson';
					$label     = 'Minimum version\nnot met';
					$toNode   .= "\n".$node['required_version']['min'];
					if( !empty( $node['required_version']['max'] )) {
						$toNode .= " - ".$node['required_version']['max'];
					}
					$toattributes['fillcolor'] = 'pink';
					break;
				case 'missing':
					$edgecolor = 'crimson';
					$label     = 'Not installed\nor activated';
					$toNode   .= "\n".$node['required_version']['min'];
					if( !empty( $node['required_version']['max'] )) {
						$toNode .= " - ".$node['required_version']['max'];
					}
					$toattributes['fillcolor'] = 'pink';
					break;
				default:
					$edgecolor = '';
					$label     = '';
					$toattributes['fillcolor'] = 'white';
					break;
				}

				$fromattributes['URL'] = "http://www.bitweaver.org/wiki/".ucfirst( $node['package'] )."Package";
				$graph->addNode( $fromNode, $fromattributes );

				$toattributes['URL'] = "http://www.bitweaver.org/wiki/".ucfirst( $node['requires'] )."Package";
				$graph->addNode( $toNode, $toattributes );

				$graph->addEdge(
					array( $fromNode => $toNode ),
					$gBitThemes->getGraphvizEdgeAttributes( array(
						'dir'       => ( !empty( $node['dir'] ) ? $node['dir'] : '' ),
						'color'     => $edgecolor,
						'fontcolor' => $edgecolor,
						'label'     => $label,
					))
				);
			}

			if( preg_match( "#(png|gif|jpe?g|bmp|svg|tif)#i", $pFormat )) {
				$graph->image( $pFormat, $pCommand );
			} else {
				return $graph->fetch( $pFormat, $pCommand );
			}
		} else {
			return FALSE;
		}
	}

	/**
	 * verifyInstalledPackages scan all available packages
	 *
	 * @param string $ pScanFile file to be looked for
	 * @return none
	 * @access public
	 */
	function verifyInstalledPackages( $pSelect='installed' ) {
		global $gBitDbType;
		#load in any admin/schema.yaml files that exist for each package
		$this->loadPackagesSchemas();
		$ret = array();

		if( $this->isDatabaseValid() ) {
			if( strlen( BIT_DB_PREFIX ) > 0 ) {
				$lastQuote = strrpos( BIT_DB_PREFIX, '`' );
				if( $lastQuote != FALSE ) {
					$lastQuote++;
				}
				$prefix = substr( BIT_DB_PREFIX, $lastQuote );
			} else {
				$prefix = '';
			}

			$showTables = ( $prefix ? $prefix.'%' : NULL );
			$unusedTables = array();
			if( $dbTables = $this->mDb->MetaTables( 'TABLES', FALSE, $showTables ) ) {
				// make a copy that we can keep track of what tables have been used
				$unusedTables = $dbTables;
				// make sure packages are loaded - this method is generally used in an install process
				$this->loadPackagesConfig( TRUE );
				foreach( $this->mPackagesConfig as $packageData ) {
					$package = $packageData['guid'];
					if( !empty( $this->mPackagesSchemas[$package]['tables'] ) ) {
						foreach( array_keys( $this->mPackagesSchemas[$package]['tables'] ) as $table ) {
							// painful hardcoded exception for bitcommerce
							if( $package == 'bitcommerce' ) {
								$fullTable = $table;
							} else {
								$fullTable = $prefix.$table;
							}
							$tablePresent = in_array( $fullTable, $dbTables );
							if( $tablePresent ) {
								$ret['present'][$package][] = $table;
							} else {
								$ret['missing'][$package][] = $table;
								// This is a crude but highly effective means of blurting out a very bad situation when an installed package is missing a table
								// if( !$this->isLive() ) {
								// 	vd( "Table Missing => $package : $table" );
								// }
							}

							// lets also return the tables that are not in use by bitweaver
							// this is useful when we want to remove old tables or upgrade tables
							if(( $key = array_search( $fullTable, $dbTables )) !== FALSE ) {
								unset( $unusedTables[$key] );
							}
						}
					}
				}
			}
			$ret['unused'] = $unusedTables;
		}
		return $ret;
	}
	// }}}

	// {{{=========================== Date and time methods ==============================
	/**
	 * Retrieve a current UTC timestamp
	 * Simple map to BitDate object allowing tidy display elsewhere
	 */
	function getUTCTime() {
		return	$this->mServerTimestamp->getUTCTime();
	}

	/**
	 * Retrieve a current UTC ISO timestamp
	 * Simple map to BitDate object allowing tidy display elsewhere
	 */
	function getUTCTimestamp() {
		return	$this->mServerTimestamp->getUTCTimestamp();
	}

	/**
	 * Retrieves the user's preferred offset for displaying dates.
	 */
	function get_display_offset( $pUser = FALSE ) {
		return $this->mServerTimestamp->get_display_offset( $pUser );
	}

	/**
	 * Retrieves the user's preferred long date format for displaying dates.
	 */
	function get_long_date_format() {
		static $site_long_date_format = FALSE;

		if( !$site_long_date_format ) {
			$site_long_date_format = $this->getConfig( 'site_long_date_format', '%A %d of %B, %Y' );
		}

		return $site_long_date_format;
	}

	/**
	 * Retrieves the user's preferred short date format for displaying dates.
	 */
	function get_short_date_format() {
		static $site_short_date_format = FALSE;

		if( !$site_short_date_format ) {
			$site_short_date_format = $this->getConfig( 'site_short_date_format', '%d %b %Y' );
		}

		return $site_short_date_format;
	}

	/**
	 * Retrieves the user's preferred long time format for displaying dates.
	 */
	function get_long_time_format() {
		static $site_long_time_format = FALSE;

		if( !$site_long_time_format ) {
			$site_long_time_format = $this->getConfig( 'site_long_time_format', '%H:%M:%S %Z' );
		}

		return $site_long_time_format;
	}

	/**
	 * Retrieves the user's preferred short time format for displaying dates.
	 */
	function get_short_time_format() {
		static $site_short_time_format = FALSE;

		if( !$site_short_time_format ) {
			$site_short_time_format = $this->getConfig( 'site_short_time_format', '%H:%M %Z' );
		}

		return $site_short_time_format;
	}

	/**
	 * Retrieves the user's preferred long date/time format for displaying dates.
	 */
	function get_long_datetime_format() {
		static $long_datetime_format = FALSE;

		if( !$long_datetime_format ) {
			$long_datetime_format = $this->getConfig( 'site_long_datetime_format', '%A %d of %B, %Y (%H:%M:%S %Z)' );
		}

		return $long_datetime_format;
	}

	/**
	 * Retrieves the user's preferred short date/time format for displaying dates.
	 */
	function get_short_datetime_format() {
		static $short_datetime_format = FALSE;

		if( !$short_datetime_format ) {
			$short_datetime_format = $this->getConfig( 'site_short_datetime_format', '%a %d of %b, %Y (%H:%M %Z)' );
		}

		return $short_datetime_format;
	}

	/*
	 * Only used in rang_lib.php which needs tidying up to use smarty templates
	 */
	function get_long_datetime( $pTimestamp, $pUser = FALSE ) {
		return $this->mServerTimestamp->strftime( $this->get_long_datetime_format(), $pTimestamp, $pUser );
	}
	// }}}
	/**
	 * getBitVersion will fetch the version of bitweaver as set in kernel/config_defaults_inc.php
	 * 
	 * @param boolean $pIncludeLevel Return bitweaver version including BIT_LEVEL
	 * @access public
	 * @return string bitweaver version set in kernel/config_defaults_inc.php
	 */
	function getBitVersion( $pIncludeLevel = TRUE ) {
		$ret = BIT_MAJOR_VERSION.".".BIT_MINOR_VERSION.".".BIT_SUB_VERSION;
		if( $pIncludeLevel && defined( BIT_LEVEL ) && BIT_LEVEL != '' ) {
			$ret .= '-'.BIT_LEVEL;
		}
		return $ret;
	}

	/**
	 * checkBitVersion Check for new version of bitweaver
	 * 
	 * @access public
	 * @return returns an array with information on bitweaver version
	 */
	function checkBitVersion() {
		$local = $this->getBitVersion( FALSE );
		$ret['local'] = $local;

		$error['number'] = 0;
		$error['string'] = $data = '';

		// cache the bitversion.txt file locally and update only once a day
		// if you don't have a connection to bitweaver.org, you can set a cronjob to 'touch' this file once a day to avoid waiting for a timeout.
		if( !is_file( TEMP_PKG_PATH.'bitversion.txt' ) || ( time() - filemtime( TEMP_PKG_PATH.'bitversion.txt' )) > 86400 ) {
			if( $h = fopen( TEMP_PKG_PATH.'bitversion.txt', 'w' )) {
				$data = bit_http_request( 'http://www.bitweaver.org/bitversion.txt' );
				if( !preg_match( "/not found/i", $data )) {
					fwrite( $h, $data );
					fclose( $h );
				}
			}
		}

		if( is_readable( TEMP_PKG_PATH.'bitversion.txt' ) ) {
			$h = fopen( TEMP_PKG_PATH.'bitversion.txt', 'r' );
			if( isset( $h ) ) {
				$data = fread( $h, 1024 );
				fclose( $h );
			}

			// nuke all lines that don't just contain a version number
			$lines = explode( "\n", $data );
			foreach( $lines as $line ) {
				if( preg_match( "/^\d+\.\d+\.\d+$/", $line ) ) {
					$versions[] = $line;
				}
			}

			if( !empty( $data ) && !empty( $versions ) && preg_match( "/\d+\.\d+\.\d+/", $versions[0] ) ) {
				sort( $versions );
				foreach( $versions as $version ) {
					if( preg_match( "/^".BIT_MAJOR_VERSION."\./", $version ) ) {
						$ret['compare'] = version_compare( $local, $version );
						$ret['upgrade'] = $version;
						$ret['page'] = preg_replace( "/\.\d+$/", "", $version );
					}
				}
				// check if there have been any major releases
				$release = explode( '.', array_pop( $versions ) );
				if( $release[0] > BIT_MAJOR_VERSION ) {
					$ret['release'] = implode( '.', $release );
					$ret['page'] = $release[0].'.'.$release[1];
				} elseif( $release[0] < BIT_MAJOR_VERSION ) {
					$ret['compare'] = version_compare( $local, $version );
					$ret['upgrade'] = $version;
				}
			} else {
				$error['number'] = 1;
				$error['string'] = tra( 'No version information available. Check your connection to bitweaver.org' );
			}
		}
		// append any release level
		$ret['local'] .= ' '.BIT_LEVEL;
		$ret['error'] = $error;
		return $ret;
	}

	// should be moved somewhere else. unbreaking things for now - 25-JUN-2005 - spiderr
	// \TODO remove html hardcoded in diff2
	function diff2( $page1, $page2 ) {
		$page1 = split( "\n", $page1 );
		$page2 = split( "\n", $page2 );
		$z = new WikiDiff( $page1, $page2 );
		if( $z->isEmpty() ) {
			$html = '<hr /><br />['.tra("Versions are identical").']<br /><br />';
		} else {
			//$fmt = new WikiDiffFormatter;
			$fmt = new WikiUnifiedDiffFormatter;
			$html = $fmt->format( $z, $page1 );
		}
		return $html;
	}

	/**
	 * getIncludeFiles will get a set of available files with a given filename
	 * 
	 * @param array $pPhpFile name of php file
	 * @param array $pTplFile name of tpl file
	 * @access public
	 * @return array of includable files
	 */
	function getIncludeFiles( $pPhpFile = NULL, $pTplFile = NULL ) {
		$ret = array();
		global $gBitSystem;
		foreach( $gBitSystem->mPackages as $package ) {
			if( $gBitSystem->isPackageActive( $package['name'] )) {
				if( !empty( $pPhpFile )) {
					$php_file = $package['path'].$pPhpFile;
					if( is_readable( $php_file ))  {
						$ret[$package['name']]['php'] = $php_file;
					}
				}

				if( !empty( $pTplFile )) {
					$tpl_file = $package['path'].'templates/'.$pTplFile;
					if( is_readable( $tpl_file )) {
						$ret[$package['name']]['tpl'] = 'bitpackage:'.$package['name'].'/'.$pTplFile;
					}
				}
			}
		}
		return $ret;
	}


	/**
	 * upgradeKernel
	 *
	 * when kernel has update requirements it often needs to be forced
	 * this method processes those requirements
	 */
	function upgradeKernel(){
		if( is_file( INSTALL_PKG_PATH.'BitInstaller.php' ) && is_readable( INSTALL_PKG_PATH.'BitInstaller.php' ) ){
			define( 'AUTO_UPDATE_KERNEL', TRUE );
			include_once( INSTALL_PKG_PATH.'BitInstaller.php' );
			global $gBitInstaller;
			$gBitInstaller = new BitInstaller();

			$dir = KERNEL_PKG_PATH.'admin/upgrades/';
			$upDir = opendir( $dir );
			while( FALSE !== ( $file = readdir( $upDir ))) {
				if( is_file( $dir.$file )) {
					$upVersion = str_replace( ".php", "", $file );
					// we only want to load files of versions that are greater than is installed
					// special switch for pre 2.1.0 system
					if( $this->getConfig( 'package_kernel_version' ) ){
						$currVersion = $gBitInstaller->getVersion( 'kernel' );
					}
					else{
						$currVersion = $this->getPackageConfigValue( 'kernel', 'version' ); 
					}
					if( $gBitInstaller->validateVersion( $upVersion ) && version_compare( $currVersion, $upVersion, '<' )) {
						include_once( $dir.$file );
					}
				}
			}

			if( $errors = $gBitInstaller->upgradePackageVersions('kernel') ){
				// upgrade successful - continue
				error_log( 'Auto Kernel Upgrade: '.implode( $errors ) );
			}else{
				// yay!
				return;
			}
		}

		// if something went wrong fatal
		// fatal if installer is not available
		$_REQUEST['error'] = tra('The website is closed while critical updates are installed' );
		include( KERNEL_PKG_PATH . 'error_simple.php' );
		exit;
	}
}

/* Function for sorting AppMenu by menu_position */
function bit_system_menu_sort( $a, $b ) {
	$pa = empty( $a['menu_position'] ) ? 0 : $a['menu_position'];
	$pb = empty( $b['menu_position'] ) ? 0 : $b['menu_position'];

	if( $pa == 0 && $pb == 0 ) {
		return( strcmp( $pb['menu_title'], $pa['menu_title'] ));
	}
	return $pb - $pa;
}

/* vim: :set fdm=marker : */
