<?php
/**
 * RegexFunctions extension -- Regular Expression parser functions
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Schmidt
 * @license http://en.wikipedia.org/wiki/Public_domain Public domain
 * @link https://www.mediawiki.org/wiki/Extension:RegexFunctions Documentation
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'RegexFunctions' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['RegexFunctions'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for RegexFunctions extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the RegexFunctions extension requires MediaWiki 1.29+' );
}
