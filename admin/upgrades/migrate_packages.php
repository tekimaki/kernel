<?php

// require_once( '../../setup_inc.php' );

global $gBitUser, $gBitSystem;

if( defined( 'AUTO_UPDATE_KERNEL' ) || is_object( $gBitUser ) && $gBitUser->isAdmin() ){

	$gBitSystem->loadPackagesSchemas();

	foreach( $gBitSystem->mPackagesSchemas as $guid=>$data ){
		if( $pkgStatus = $gBitSystem->getConfig( 'package_'.$guid ) ){
			// if the package is installed add it, we're dropping everything else
			if( $pkgStatus == 'y' || $pkgStatus = 'i' ){	
				// get version
				$version = $gBitSystem->getConfig( 'package_'.$guid.'_version' ); 
				$version = $gBitSystem->validateVersion( $version )?$version:'0.0.0';
				// insert the package into the new package table	
				$storeHash = $data;
				$storeHash['version'] = $guid != 'kernel'?$version:$storeHash['version']; // kernel is auto updated so we dont want to preserve any versioning 
				$storeHash['active'] = $gBitSystem->getConfig('package_'.$guid) == 'y'?'y':'n'; 
				$gBitSystem->storePackage( $storeHash );
			}else{
				// print the packages not being inserted to the new packages table
				// vd( $guid );
			}

			// delete package settings
			$query1 = "DELETE FROM `kernel_config` WHERE config_name LIKE 'package_%'";
			$gBitSystem->mDb->query( $query1 );
		}
	}

	// drop all the package version and package self describing values from kernel_config
	
}else{
	$gBitSystem->fatalError('Permission denied');
}
