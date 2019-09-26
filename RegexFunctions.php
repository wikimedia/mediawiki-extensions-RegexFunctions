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

if( !defined( 'MEDIAWIKI' ) ) {
	echo "This file is an extension of the MediaWiki software and cannot be used standalone\n";
	die( 1 );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'RegexFunctions',
	'author' => 'Ryan Schmidt',
	'version' => '1.5.0',
	'descriptionmsg' => 'regexfunctions-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:RegexFunctions',
);

$wgMessagesDirs['RegexFunctions'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['RegexFunctionsMagic'] = __DIR__ . '/RegexFunctions.i18n.magic.php';

$wgAutoloadClasses['ExtRegexFunctions'] = __DIR__ . '/ExtRegexFunctions.php';

$wgHooks['ParserFirstCallInit'][] = 'ExtRegexFunctions::onParserFirstCallInit';
$wgHooks['ParserClearState'][] = 'ExtRegexFunctions::onParserClearState';

// default globals
// how many functions are allowed in a single page? Keep this at least above 3 for usability
$wgRegexFunctionsPerPage = 10;
// should we allow modifiers in the functions, e.g. the /i modifier for case-insensitive?
$wgRegexFunctionsAllowModifiers = true;
// should we allow internal options to be set (e.g. (?opts) or (?opts:some regex))
$wgRegexFunctionsAllowOptions = true;
// limit for rsplit and rreplace functions. -1 is unlimited
$wgRegexFunctionsLimit = -1;
// array of functions to disable, aka these functions cannot be used :)
$wgRegexFunctionsDisable = array();
