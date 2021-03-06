<?php
/**
 * Custom ADODB Error Handler. This will be called with the following params
 *
 * @package kernel
 * @subpackage functions
 * @version V3.94  13 Oct 2003  (c) 2000-2003 John Lim (jlim@natsoft.com.my). All rights reserved.
 * Released under both BSD license and Lesser GPL library license.
 * Whenever there is any discrepancy between the two licenses,
 * the BSD license will take precedence.
 *
 * Set tabs to 4 for best viewing.
 *
 * Latest version is available at http://php.weblogs.com
 *
 */

/**
 * set error handling
 */
if( !defined( 'BIT_INSTALL' ) &&  !defined( 'ADODB_ERROR_HANDLER' )  ) {
	define( 'ADODB_ERROR_HANDLER', 'bit_error_handler' );
}

function bit_log_error( $pLogMessage ) {
	if( !empty( $_SERVER['SCRIPT_URI'] )) {
		error_log( "$pLogMessage in {$_SERVER['SCRIPT_URI']}" );
	} else {
		error_log( "$pLogMessage" );
	}
}

function bit_display_error( $pLogMessage, $pSubject, $pFatal = TRUE ) {
	global $gBitSystem, $gBitThemes;

	// You can prevent sending of error emails by adding define('ERROR_EMAIL', ''); in your config/config_inc.php
	if( is_object( $gBitSystem ) ){
		$errorEmail = $gBitSystem->getErrorEmail();
	}

	error_log( $pLogMessage );

	if( ( !defined( 'IS_LIVE' ) || !IS_LIVE ) ) {
		print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">
			<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
			<head>
				<title>bitweaver - White Screen of Death</title>
			</head>
			<body style="background:#fff; font-family:monospace;">';
		print "<h1 style=\"color:#900; font-weight:bold;\">You are running bitweaver in TEST mode</h1>\n";
		print "
			<ul>
				<li><a href='http://sourceforge.net/tracker/?func=add&amp;group_id=141358&amp;atid=749176'>Click here to log a bug</a>, if this appears to be an error with the application.</li>
				<li><a href='".BIT_ROOT_URL."install/install.php'>Go here to begin the installation process</a>, if you haven't done so already.</li>
				<li>To hide this message, please <strong>set the IS_LIVE constant to TRUE</strong> in your config/config_inc.php file.</li>
			</ul>
			<hr />
		";
		print "<pre>".$pLogMessage."</pre>";
		print "<hr />";
		print "</body></html>";
	} elseif( $errorEmail ) {
		global $gBitSmarty, $gSwitchboardSystem;

		// send error email
		// use switchboard to send mail if available
		$siteName = $gBitSystem->getConfig('site_title', $_SERVER['HTTP_HOST'] );
		$subject = $siteName.' - '.$pSubject;
		if( is_object( $gSwitchboardSystem ) ){
			$recipients = array( array( 'email' => $errorEmail ), );
			$msg['recipients'] = $recipients;
			$msg['subject'] = $subject;
			$msg['alt_message'] = $pLogMessage;
			$gSwitchboardSystem->sendEmail( $msg );
		// fall back to php mail
		}else{
			mail( $errorEmail,  "$subject", $pLogMessage );
		}

		// send email to bitweaver development team - opt in by setting AUTO_BUG_SUBMIT
		if( defined( 'AUTO_BUG_SUBMIT' ) && AUTO_BUG_SUBMIT && !empty( $gBitSystem ) && $gBitSystem->isDatabaseValid() ) {
			mail( 'bugs@bitweaver.org',"$pSubject",$pLogMessage );
		}

		$gBitSmarty->assign( 'showmsg', 'n' ); // showmsg shows up in users/templates/register.tpl not clear why its set here

		$gBitSmarty->assign('dbError', ERROR);

		if( $pFatal ){
			$gBitThemes->loadLayout( array( 'layout' => 'kernel' ), TRUE );
			$gBitSystem->display( 'bitpackage:kernel/db_error.tpl', tra('System Error' ), array( 'display_mode' => 'error' ) );
		}
	}

	if( $pFatal ) {
		die();
	}
}

function bit_error_string( $iDBParms ) {
	global $gBitDb;
	global $gBitUser;
	global $_SERVER;

	$separator = "\n";
	$indent = "  ";

	$date = date("D M d H:i:s Y"); // [Tue Sep 24 12:19:20 2002] [error]

	if( isset( $gBitUser->mInfo ) ) {
		$acctStr = "ID: ".$gBitUser->mInfo['user_id']." - Login: ".$gBitUser->mInfo['login']." - e-mail: ".$gBitUser->mInfo['email'];
	} else {
		$acctStr = "User unknown";
	}

	$info  = $indent."[ - ".BIT_MAJOR_VERSION.".".BIT_MINOR_VERSION.".".BIT_SUB_VERSION." ".BIT_LEVEL." - ] [ $date ]".$separator;
	$info .= $indent."-----------------------------------------------------------------------------------------------".$separator;
	$info .= $indent."#### USER AGENT: ".$_SERVER['HTTP_USER_AGENT'].$separator;
	$info .= $indent."#### ACCT: ".$acctStr.$separator;
	$info .= $indent."#### URL: http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$separator;
	if( isset($_SERVER['HTTP_REFERER'] ) ) {
		$info .= $indent."#### REFERRER: $_SERVER[HTTP_REFERER]".$separator;
	}
	$info .= $indent."#### HOST: $_SERVER[HTTP_HOST]".$separator;
	$info .= $indent."#### IP: $_SERVER[REMOTE_ADDR]".$separator;

	if( $gBitDb && isset( $php_errormsg ) ) {
		$info .= $indent."#### PHP: ".$php_errormsg.$separator;
	}

	if ( $iDBParms['sql'] ) {
		$badSpace = array("\n", "\t");
		$info .= $indent."#### SQL: ".str_replace($badSpace, ' ', $iDBParms['sql']).$separator;
		if( is_array( $iDBParms['p2'] ) ) {
			$info .= $indent.'['.implode( ', ', $iDBParms['p2'] ).']'.$separator;
		}
	}

	$errno = ((int)$iDBParms['errno'] ? 'Errno: '.$iDBParms['errno'] : '');

	$info .= $indent."#### ERROR CODE: ".$errno."  Message: ".$iDBParms['db_msg'];

	$stackTrace = bt( 9999, FALSE );

	//multiline expressions matched
	if( preg_match_all( "/.*adodb_error_handler\([^\}]*\)(.+\}.+)/ms", $stackTrace, $match )) {
		$stackTrace = $match[1][0];
	}

	$globals = array(
		'$_POST'   => $_POST,
		'$_GET'    => $_GET,
		'$_COOKIE' => $_COOKIE,
		'$_FILES'  => $_FILES,
	);

	$parameters = '';
	foreach( $globals as $global => $hash ) {
		if( !empty( $hash )) {
			$parameters .= $separator.$separator.$global.': '.$separator.var_export( $hash, TRUE );
		}
	}
	$parameters = preg_replace( "/\n/", $separator.$indent, $parameters );

	$ret = $info.$separator.$separator.$stackTrace.$parameters;

	return $ret;
}

if (!function_exists('bt')) {	// Make sure another backtrace function does not exist
function bt( $levels=9999, $iPrint=TRUE ) {
	$s = '';
	if (PHPVERSION() >= 4.3) {

		$MAXSTRLEN = 64;

		$traceArr = debug_backtrace();
		array_shift($traceArr);
		$tabs = sizeof($traceArr)-1;
		$indent = '';
		$sClass = '';

		foreach ($traceArr as $arr) {
			$levels -= 1;
			if ($levels < 0) break;

			$args = array();
			for ($i=0; $i <= $tabs; $i++) {
				$indent .= '}';
			}
			$tabs -= 1;
			if ( isset($arr['class']) ) {
				$sClass .= $arr['class'].'::';
			}
			if ( isset($arr['args']) ) {
				foreach( $arr['args'] as $v ) {
					if (is_null($v) ) {
						$args[] = 'null';
					} elseif (is_array($v)) { $args[] = 'Array['.sizeof($v).']';
					} elseif (is_object($v)) { $args[] = 'Object:'.get_class($v);
					} elseif (is_bool($v)) { $args[] = $v ? 'true' : 'false';
					} else {
						$v = (string) @$v;
						$str = htmlspecialchars(substr($v,0,$MAXSTRLEN));
						if (strlen($v) > $MAXSTRLEN) $str .= '...';
						$args[] = $str;
					}
				}
			}
			$s .= "\n    ".$indent;
			$s .= @sprintf(" LINE: %4d, %s", $arr['line'],$arr['file']);
			if( !preg_match( "*include*", $arr['function'] ) && !preg_match( "*silentlog*", strtolower( $arr['function'] ) ) ) {
				$s .= "\n    ".$indent.'    -> ';
				$s .= $sClass.$arr['function'].'('.implode(', ',$args).')';
			}
			$indent = '';
		}
		$s .= "\n";
		if( $iPrint ) {
			print '<pre>'.$s."</pre>\n";
		}
	}
	return $s;
}
}	// End if function_exists('bt')

// var dump variable in something nicely readable in web browser
function vd( $pVar, $pGlobals=FALSE, $pDelay=FALSE ) {
	global $gBitSystem;

	ob_start();
	if( $pGlobals ) {
		print '<h2>$pVar</h2>';
	}
	print vc( $pVar );
	if( $pGlobals ) {
		if( !empty( $_GET )) {
			print '<h2>$_GET</h2>';
			print vc( $_GET );
		}
		if( !empty( $_POST )) {
			print '<h2>$_POST</h2>';
			print vc( $_POST );
		}
		if( !empty( $_FILES )) {
			print '<h2>$_FILES</h2>';
			print vc( $_FILES );
		}
		if( !empty( $_COOKIE )) {
			print '<h2>$_COOKIE</h2>';
			print vc( $_COOKIE );
		}
	}
	if($pDelay) {
		$gBitSystem->mDebugHtml .= ob_get_contents();
		ob_end_clean();
	} else {
		ob_end_flush();
	}
}

// var capture variable in something nicely readable in web browser
function vc( $iVar, $pHtml=TRUE ) {
	ob_start();
	if( is_object( $iVar ) ) {
		if( isset( $iVar->mDb ) ) {
			unset( $iVar->mDb );
		}
	}

	// xdebug rocks!
	if( extension_loaded( 'xdebug' ) ) {
		var_dump( $iVar );
	} elseif( $pHtml && !empty( $_SERVER['HTTP_USER_AGENT'] ) && $_SERVER['HTTP_USER_AGENT'] != 'cron' && ((is_object( $iVar ) && !empty( $iVar )) || is_array( $iVar )) ) {
		include_once( UTIL_PKG_PATH.'dBug/dBug.php' );
		new dBug( $iVar, "", FALSE );
	} else {
		print '<pre>';
		if( is_object( $iVar ) ) {
			var_dump( $iVar );
		} elseif( is_string( $iVar ) && !empty( $_SERVER['HTTP_USER_AGENT'] ) && $_SERVER['HTTP_USER_AGENT'] != 'cron' ) {
			var_dump( htmlentities( $iVar ) );
		} else {
			var_dump( $iVar );
		}
		print "</pre>\n";
	}
	$ret = ob_get_contents();
	ob_end_clean();
	return $ret;
}


function va( $iVar ) {
	$dbg = var_export($iVar, 1);
	$dbg = highlight_string("<?\n". $dbg."\n?>", 1);
	echo "<div><span style='background-color:black;color:white;padding:.5ex;font-weight:bold;'>Var Anatomy</div>";
	echo "<div style='border:1px solid black;padding:2ex;background-color:#efe6d6;'>$dbg</div>";
}


?>
