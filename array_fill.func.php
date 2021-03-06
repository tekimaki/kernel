<?php
/**
 * @package kernel
 * @subpackage functions
 */

// For PHP version < 4.2.0 missing the array_fill function,
// I provide here an alternative. -Philippe
// taken from http://de3.php.net/manual/en/function.array-fill.php comments. thanks jausion at hotmail-dot-com

/**
 * array_fill
 */
if( !function_exists( 'array_fill' ) ) {
	function array_fill($iStart, $iLen, $vValue) {
		$aResult = array();
		for ($iCount = $iStart; $iCount < $iLen + $iStart; $iCount++) {
			$aResult[$iCount] = $vValue;
		}
		return $aResult;
	}
}

?>
