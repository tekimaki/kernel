<?php
/**
 * @version $Header$
 */
global $gBitInstaller;

$infoHash = array(
	'package'      => KERNEL_PKG_NAME,
	'version'      => str_replace( '.php', '', basename( __FILE__ )),
	'description'  => "This upgrade refactors package installation and registration.",
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
		  name C(64),
		  description C(250)
		",
	)),
)),
      
array( 'PHP' => '
	include_once( KERNEL_PKG_PATH."admin/upgrades/migrate_packages.php" );
' )
));

