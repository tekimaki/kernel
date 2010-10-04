<?php

// require_once( '../../setup_inc.php' );

global $gBitUser, $gBitSystem;

if( $gBitUser->isAdmin() ){

	$gBitSystem->scanPackages( 'bit_setup_inc.php', TRUE, 'all', TRUE, TRUE );
	$gBitSystem->verifyInstalledPackages('all' );
	$schemaData = $gBitSystem->getPackagesSchemas();

	foreach( $gBitSystem->mPackages as $guid=>$data ){
		// if the package is installed add it, we're dropping everything else
		if( !empty( $data['installed'] ) && $data['installed'] ){	
			// insert the package into the new package table	
			$storeHash = array(
				'package_guid' => $guid, 
				'version' => ( !empty( $data['info']['version'] )?$data['info']['version']:'0.0.0' ), 
				'homeable' => $data['homeable'], 
				'active' => ( !empty( $data['active_switch'] ) && $data['active_switch'] == 'y'?'y':NULL ), 
				'name' => ( !empty( $schemaData[$guid]['name'] )?$schemaData[$guid]['name']:ucfirst($guid) ), 
				'description' => ( !empty( $schemaData[$guid]['description'] )?$schemaData[$guid]['description']:NULL ), 
			);

			$gBitSystem->mDb->associateInsert( 'packages', $storeHash );

		}else{
			// print the packages not being inserted to the new packages table
			// vd( $guid );
		}

		// delete package settings
		$query1 = "DELETE FROM `kernel_config` WHERE config_name LIKE 'package_%'";
		$gBitSystem->mDb->query( $query1 );
	}

	// drop all the package version and package self describing values from kernel_config
	
}else{
	$gBitSystem->fatalError('Permission denied');
}
