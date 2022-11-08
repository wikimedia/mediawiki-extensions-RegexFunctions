<?php
/**
 * RegexFunctions extension -- Regular Expression parser functions
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Schmidt
 * @license https://en.wikipedia.org/wiki/Public_domain Public domain
 * @link https://www.mediawiki.org/wiki/Extension:RegexFunctions Documentation
 */

class ExtRegexFunctions {
	/**
	 * Registers the regex functions with the parser
	 *
	 * @param Parser $parser
	 * @throws MWException
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'rmatch', [ __CLASS__, 'rmatch' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'rsplit', [ __CLASS__, 'rsplit' ], Parser::SFH_OBJECT_ARGS );
		$parser->setFunctionHook( 'rreplace', [ __CLASS__, 'rreplace' ], Parser::SFH_OBJECT_ARGS );
	}

	/**
	 * Get a named argument passed into the parser function, possibly localized
	 *
	 * @param PPFrame $frame
	 * @param string $key
	 * @param int|null $positionalFallback
	 * @return bool|string The specified argument or false if the argument is not present
	 */
	private static function getLocalizedArgument( PPFrame $frame, $key, $positionalFallback = null ) {
		$localizedKey = wfMessage( "regexfunctions-arg-$key" )->inContentLanguage()->plain();

		$value = $frame->getArgument( $localizedKey );
		if ( $value === false ) {
			$value = $frame->getArgument( $key );
			if ( $value === false && is_int( $positionalFallback ) ) {
				$value = $frame->getArgument( $positionalFallback );
			}
		}

		return $value;
	}

	/**
	 * Processes the frame to extract the pattern and options
	 *
	 * Pattern can be the first numbered parameter or a named parameter.
	 * Named parameters:
	 * pattern: the regex pattern, optionally delimited by forward slashes with regex options afterwards
	 * multiline (m): if this parameter is not empty, ^ and $ match individual lines instead of the entire string
	 * caseless (i): if this parameter is not empty, the pattern is case-insensitive
	 * ungreedy (U): if this parameter is not empty, * and + are treated as ungreedy and *? and +? are greedy
	 * extended (x): if this parameter is not empty, the regex can include inline comments and spacing for readability
	 * dotall (s): if this paramter is not empty, . matches newline characters as well
	 *
	 * @param PPFrame $frame
	 * @return string The delimited pattern with options at the end
	 */
	private static function getPatternAndOptions( PPFrame $frame ) {
		$pattern = self::getLocalizedArgument( $frame, 'pattern', 1 );
		if ( $pattern === false ) {
			// no pattern specified
			return '';
		}

		// test if pattern is delimited by slashes and extract the actual regex if so
		$options = [];
		if ( preg_match( '#^/(.*?(\\\\*))/([a-zA-Z]*)$#u', $pattern, $matches ) ) {
			// if the pattern ends with an odd number of backslashes then this wasn't a delimited pattern,
			// just unfortunately looks very close to one (as the ending delimiter was escaped)
			if ( strlen( $matches[2] ) % 2 === 0 ) {
				$pattern = $matches[1];
				$options = str_split( $matches[3] );
			}
		}

		// discard unsupported options
		$options = array_intersect( $options, [ 'm', 'i', 'U', 'x', 's' ] );

		// test for named options
		if ( self::getLocalizedArgument( $frame, 'multiline' ) ) {
			$options[] = 'm';
		}

		if ( self::getLocalizedArgument( $frame, 'caseless' ) ) {
			$options[] = 'i';
		}

		if ( self::getLocalizedArgument( $frame, 'ungreedy' ) ) {
			$options[] = 'U';
		}

		if ( self::getLocalizedArgument( $frame, 'extended' ) ) {
			$options[] = 'x';
		}

		if ( self::getLocalizedArgument( $frame, 'dotall' ) ) {
			$options[] = 's';
		}

		// add hardcoded options (currently only to treat subject and pattern as utf-8)
		$options[] = 'u';

		// remove duplicates
		$options = array_unique( $options );

		// Escape any unescaped slashes, as we use slash for our delimiter
		// Using \xfe as a placeholder because it never appears in utf-8 strings, which is what wikitext is
		$replacements = [
			'\\\\'  => "\xfe1", // replace all escaped backslashes with a placeholder
			'\\/'   => "\xfe2", // replace all escaped slashes with a placeholder
			'/'     => '\\/',   // replace all unescaped slashes with escaped slashes
			"\xfe2" => '\\/',   // undo placeholder
			"\xfe1" => '\\\\'   // undo placeholder
		];

		$pattern = str_replace( array_keys( $replacements ), array_values( $replacements ), $pattern );
		return '/' . $pattern . '/' . implode( $options );
	}

	/**
	 * Regex match function
	 *
	 * Supports a legacy invocation with numbered parameters:
	 * {{#rmatch:string|pattern|then|else}}
	 *
	 * Supports named parameters:
	 * All named parameters mentioned in getPatternAndOptions
	 * then: text to display if the match is successful
	 * else: text to display if the match is not successful, blank if not defined
	 *
	 * @param Parser &$parser
	 * @param PPFrame $frame
	 * @param PPNode[] $args
	 * @return string Expanded wikitext result of parser function
	 */
	public static function rmatch( Parser &$parser, PPFrame $frame, $args ) {
		$string = $frame->expand( array_shift( $args ) );
		// make a PPFrame containing the remainder of parser function args for ease of use
		$matchFrame = $frame->newChild( $args, $frame->getTitle() );
		$pattern = self::getPatternAndOptions( $matchFrame );

		$num = preg_match( $pattern, $string, $matches );
		if ( $num === false ) {
			return '';
		}

		if ( $num === 0 ) {
			// not a match
			$else = self::getLocalizedArgument( $matchFrame, 'else', 3 );
			if ( $else === false ) {
				return '';
			}

			return $else;
		}

		$then = self::getLocalizedArgument( $matchFrame, 'then', 2 );
		if ( $then === false ) {
			return '';
		}

		// expand back-references in $then
		return preg_replace_callback(
			'/[$\\\\]([0-9]+)|\${([0-9]+)}/',
			static function ( $backRefs ) use ( $matches ) {
				if ( ( $backRefs[1] ?? '' ) !== '' && array_key_exists( $backRefs[1], $matches ) ) {
					return $matches[$backRefs[1]];
				}

				if ( ( $backRefs[2] ?? '' ) !== '' && array_key_exists( $backRefs[2], $matches ) ) {
					return $matches[$backRefs[2]];
				}

				return '';
			},
			$then
		);
	}

	/**
	 * Split a string and return one piece of it
	 *
	 * {{#rsplit:string|pattern|piece}}
	 *
	 * Supports named arguments:
	 * All named arguments in getPatternAndOptions
	 * piece: Piece to return, starting at 0. Negative returns from the end.
	 *
	 * @param Parser &$parser
	 * @param PPFrame $frame
	 * @param PPNode[] $args
	 * @return string
	 */
	public static function rsplit( Parser &$parser, PPFrame $frame, $args ) {
		$string = $frame->expand( array_shift( $args ) );
		$splitFrame = $frame->newChild( $args, $frame->getTitle() );
		$pattern = self::getPatternAndOptions( $splitFrame );

		$piece = self::getLocalizedArgument( $splitFrame, 'piece', 2 );
		$piece = intval( $piece );
		$res = preg_split( $pattern, $string );

		// allow negative pieces to work from the end of the array
		if ( $piece < 0 ) {
			$piece = $piece + count( $res );
		}

		// sanitation for pieces that don't exist
		if ( $piece < 0 ) {
			$piece = 0;
		}

		if ( $piece >= count( $res ) ) {
			$piece = count( $res ) - 1;
		}

		return $res[$piece];
	}

	/**
	 * Perform a replacement on the string
	 *
	 * {{#rreplace:string|pattern|replacement}}
	 *
	 * @param Parser &$parser
	 * @param PPFrame $frame
	 * @param PPNode[] $args
	 * @return string
	 */
	public static function rreplace( Parser &$parser, PPFrame $frame, $args ) {
		$string = $frame->expand( array_shift( $args ) );
		$replaceFrame = $frame->newChild( $args, $frame->getTitle() );
		$pattern = self::getPatternAndOptions( $replaceFrame );

		$replacement = self::getLocalizedArgument( $replaceFrame, 'replacement', 2 );
		if ( $replacement === false ) {
			$replacement = '';
		}

		return preg_replace( $pattern, $replacement, $string );
	}
}
