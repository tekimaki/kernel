<?php
/**
 * @version $Header$
 * @package kernel
 * @subpackage functions
 */

/**
 * Setup
 */
require_once( '../kernel/setup_inc.php' );
$gBitSystem->verifyPermission( 'p_admin' );
$gBitSystem->verifyInstalledPackages();
$gBitSystem->drawRequirementsGraph( !empty( $_REQUEST['install_version'] ), ( !empty( $_REQUEST['format'] ) ? $_REQUEST['format'] : 'png' ), ( !empty( $_REQUEST['command'] ) ? $_REQUEST['command'] : 'dot' ));
?>
