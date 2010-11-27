<?php
/**
 * @version $Header$
 */
global $gBitInstaller;

$infoHash = array(
	'package'      => KERNEL_PKG_NAME,
	'version'      => str_replace( '.php', '', basename( __FILE__ )),
	'description'  => "Increase the size of the plugin_hanlder column.",
);

$gBitInstaller->registerPackageUpgrade( $infoHash, array(
array( 'DATADICT' => array(
	// insert new column
	array( 'ALTER' => array(
		'package_plugins_api_map' => array(
			'plugin_handler' => array( '`plugin_handler`', 'TYPE VARCHAR(250)' ),
		),
		'package_plugins' => array(
			'pos' => array( '`pos`', 'INTEGER DEFAULT \'1\'' ),
		),
	)),
)),
array( 'QUERY' => array(
	'UPDATE `package_plugins` SET `pos` = 1',
)),
array( 'DATADICT' => array(
	// insert new column
	array( 'ALTER' => array(
		'package_plugins' => array(
			'pos' => array( '`pos`', 'SET NOT NULL' ),
		),
	)),
	array( 'ALTER' => array(
		'package_plugins' => array(
			'pos' => array( '`pos`', 'SET DEFAULT \'1\'' ),
		),
	)),
)),
));
