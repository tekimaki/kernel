<?php
/**
 * @version $Header$
 */
global $gBitInstaller;

$infoHash = array(
	'package'      => KERNEL_PKG_NAME,
	'version'      => str_replace( '.php', '', basename( __FILE__ )),
	'description'  => "This upgrade refactors package and service installation and registration.",
	'post_upgrade' => NULL,
);
$gBitInstaller->registerPackageUpgrade( $infoHash, array(

array( 'DATADICT' => array(
	array( 'CREATE' => array(
		'packages' => "
		  guid C(64) PRIMARY,
		  version C(16) NOTNULL DEFAULT '0.0.0',
		  homeable C(1) NOTNULL DEFAULT 'y',
		  active C(1) DEFAULT NULL,
		  required C(1) DEFAULT NULL,
		  dir C(64) NOTNULL,
		  name C(64),
		  description C(250)
		",

		'package_plugins' => "
		  guid C(64) PRIMARY,
		  package_guid C(64) NOTNULL,
		  version C(16) NOTNULL DEFAULT '0.0.0',
		  active C(1) DEFAULT NULL,
		  required C(1) DEFAULT NULL,
		  path_type C(64) NOTNULL,
		  handler_file C(64) NOTNULL,
		  name C(64),
		  description C(250)
		  CONSTRAINT '
		  , CONSTRAINT `package_guid_ref` FOREIGN KEY (`package_guid`) REFERENCES `packages`( `guid` )'
		",

		'package_plugins_api_hooks' => "
		  api_hook C(64) PRIMARY,
		  api_type C(64) PRIMARY
		",

		'package_plugins_api_map' => "
		  plugin_guid C(64) PRIMARY,
		  api_hook C(64) PRIMARY,
		  api_type C(64) PRIMARY,
		  plugin_handler C(64) NOTNULL
		  CONSTRAINT '
		  , CONSTRAINT `plugin_guid_ref` FOREIGN KEY (`plugin_guid`) REFERENCES `package_plugins`( `guid` )
		  , CONSTRAINT `plugin_api_hook_ref` FOREIGN KEY (`api_hook`,`api_type`) REFERENCES `package_plugins_api_hooks`( `api_hook`,`api_type` )'
		",
	)),
)),
      
array( 'PHP' => '
	include_once( KERNEL_PKG_PATH."admin/upgrades/migrate_packages.php" );
' )
));

