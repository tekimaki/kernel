<?php

// $Header$

// Copyright (c) 2002-2003, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See below for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.

// Process Packages form
$fPackage = &$_REQUEST['fPackage'];   // emulate register_globals

if( !empty( $_REQUEST['features'] ) ) {
	$pkgArray = $gBitSystem->mPackagesConfig;
	foreach( $pkgArray as $pkgKey=>$pkg ) {
		if( !empty( $pkg['guid'] )) {
			$pkgName = strtolower( $pkg['guid'] );
			// can only change already installed packages that are not required
			if( $gBitSystem->isPackageInstalled( $pkgName ) && empty( $pkg['required'] )) {
				if( isset( $_REQUEST['fPackage'][$pkgName] )) {
					// mark installed and active
					$gBitSystem->activatePackage( $pkgName );
				} else {
					// mark installed but not active
					$gBitSystem->deactivatePackage( $pkgName );
				}
			}
		}
	}
	// after updates reload the config
	$gBitSystem->loadPackagesConfig( TRUE );
}

// $gBitSystem->verifyInstalledPackages();	// this causes some weird rendering issues - wjames	
$gBitSmarty->assign( 'requirements', $gBitSystem->calculateRequirements( TRUE ) );
$gBitSmarty->assign( 'requirementsMap', $gBitSystem->drawRequirementsGraph( TRUE, 'cmapx' ));

$gBitSystem->loadPackagesSchemas();

$upgradable = array();
foreach( $gBitSystem->mPackagesConfig as $pkgGuid => $pkg ) {
	if( $gBitSystem->isPackageInstalled( $pkgGuid ) && version_compare( $pkg['version'], $gBitSystem->mPackagesSchemas[$pkgGuid]['version'], "<" )) {
		// only display relevant information to keep things tight.
		$upgradable[$pkgGuid] = $pkg;
		$upgradable[$pkgGuid]['info'] = array(
			'version' => $pkg['version'],
			'upgrade' => $gBitSystem->mPackagesSchemas[$pkgGuid]['version']
			);
	}
}
$gBitSmarty->assign( 'upgradable', $upgradable );

// So packages will be listed in alphabetical order
ksort( $gBitSystem->mPackagesConfig );
ksort( $gBitSystem->mPackagesSchemas );
