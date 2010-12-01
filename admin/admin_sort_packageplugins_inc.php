<?php

// $Header$

// Copyright (c) 2010 Will James, bitweaver, Tekimaki LLC 
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See http://www.gnu.org/copyleft/lesser.html for details.

if( !empty( $_REQUEST['update_plugins'] ) ) {
	foreach( $_REQUEST['package_plugins'] as $guid => $pos ){
		$storeHash = $gBitSystem->getPluginConfig( $guid );	
		$storeHash['pos'] = $pos;
		$gBitSystem->storePlugin( $storeHash );
	}
	$gBitSystem->loadPackagePluginsConfig( TRUE );
}

// So packages will be listed in alphabetical order
ksort( $gBitSystem->mPackagesSchemas );
