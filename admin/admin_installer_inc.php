<?php
if( is_file( INSTALL_PKG_PATH.'install.php' ) && is_readable( INSTALL_PKG_PATH.'install.php' ) ){
	bit_redirect( INSTALL_PKG_URL.'install.php' );
}else{
	echo "The installer is not readable by the webserver. Please check that the installer package is installed and that Apache has permission to read it.";
	die;
}
