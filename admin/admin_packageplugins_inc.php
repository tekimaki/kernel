<?php

// $Header$

// Copyright (c) 2010 Will James, bitweaver, Tekimaki LLC 
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.

if( !empty( $_REQUEST['update_plugins'] ) ) {
	foreach( $gBitSystem->getInstalledPackagePlugins() as $guid => $item ){
		// can only change already installed plugins that are not required
		if( $gBitSystem->isPluginInstalled( $guid ) && $item['required'] != 'y' ) {
			if( !empty( $_REQUEST['package_plugins'][$guid] )) {
				// activate
				$gBitSystem->activatePlugin( $guid );
			} else {
				// deactivate
				$gBitSystem->deactivatePlugin( $guid );
			}
		}
	}
	// after updates reload the config
	$gBitSystem->loadPackagesConfig( TRUE );
}

$gBitSystem->configAllPackages();

// @TODO stay or go, this doesnt exist for Plugins, should we bother?
// $gBitSmarty->assign( 'requirements', $gBitSystem->calculateRequirements( TRUE ) );
// $gBitSmarty->assign( 'requirementsMap', $gBitSystem->drawRequirementsGraph( TRUE, 'cmapx' ));

// Package updates
// $gBitSmarty->assign( 'upgradable', $gBitSystem->getUpgradablePlugins() );

// So packages will be listed in alphabetical order
ksort( $gBitSystem->mPackagePluginsConfig );
ksort( $gBitSystem->mPackagesSchemas );
