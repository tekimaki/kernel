<?php
/**
 * @version $Header: /cvsroot/bitweaver/_bit_kernel/menu_register_inc.php,v 1.8 2006/03/29 08:56:49 squareing Exp $
 * @package kernel
 * @subpackage functions
 *
 * This file only needs to be called once, and only when you plan on rendering the app menu, or something similar.
 */
    global $gBitUser, $gBitSystem, $gBitSmarty;

	// =========================== Global ===========================
//	$gBitSystem->registerAppMenu( 'global', NULL, NULL, 'bitpackage:kernel/menu_global.tpl' );


	// =========================== Smarty ===========================
	array_multisort( $gBitSystem->mAppMenu );
	$gBitSmarty->assign_by_ref('appMenu',$gBitSystem->mAppMenu );


	// =========================== Admin menu ===========================
	if( $gBitUser->isAdmin() ) {
		$adminMenu = array();
		foreach( array_keys( $gBitSystem->mPackages ) as $package ) {
			$package = strtolower( $package );
			$tpl = "bitpackage:$package/menu_".$package."_admin.tpl";
			if( ($gBitSystem->isPackageActive( $package ) || $package == 'kernel') && @$gBitSmarty->template_exists( $tpl ) ) {
				$adminMenu[$package]['tpl'] = $tpl;
				$adminMenu[$package]['display'] = (empty($package) || (isset($_COOKIE[$package . 'admenu']) && ($_COOKIE[$package . 'admenu'] == 'o')) ? 'block' : 'none');
			}
		}
		array_multisort( $adminMenu );
		$gBitSmarty->assign_by_ref( 'adminMenu', $adminMenu );
		//$layoutdisplay = ((isset($_COOKIE['layoutadmenu']) && ($_COOKIE['layoutadmenu'] == 'o')) ? 'block' : 'none');
		//$gBitSmarty->assign_by_ref( 'layoutdisplay', $layoutdisplay );
	}
?>
