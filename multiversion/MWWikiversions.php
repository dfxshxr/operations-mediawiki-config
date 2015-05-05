<?php
require_once( __DIR__ . '/defines.php' );

/**
 * Helper class for reading the wikiversions.json file
 */
class MWWikiversions {
	/**
	 * @param $srcPath string Path to wikiversions.json
	 * @return Array List of wiki version rows
	 */
	public static function readWikiVersionsFile( $srcPath ) {
		$data = file_get_contents( $srcPath );
		if ( $data === false ) {
			throw new Exception( "Unable to read $srcPath.\n" );
		}
		// Read the lines of the json file into an array...
		$verList = json_decode( $data, true );
		if ( !is_array( $verList ) || array_values( $verList ) === $verList ) {
			throw new Exception( "$srcPath did not decode to an associative array.\n" );
		}
		asort( $verList );
		return $verList;
	}

	/**
	 * @param string $path Path to wikiversions.json
	 * @param array $wikis Array of wikis array( dbname => version )
	 */
	public static function writeWikiVersionsFile( $path, $wikis ) {
		// 448 == JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
		// but doesn't break on PHP 5.3, which does not have these defined.
		$json = json_encode( $wikis, 448 );
		if ( !file_put_contents( $path, $json, LOCK_EX ) ) {
			print "Unable to write to $path.\n";
			exit( 1 );
		}
	}

	/**
	 * Evaluate a dblist expression.
	 *
	 * A dblist expression contains one or more dblist file names separated by '+' and '-'.
	 *
	 * @par Example:
	 * @code
	 *  %% all.dblist - wikipedia.dblist
	 * @endcode
	 *
	 * @param $expr string
	 * @return Array
	 */
	public static function evalDbListExpression( $expr ) {
		$expr = trim( strtok( $expr, "#\n" ), "% " );
		$tokens = preg_split( '/ *([-+]) */m', $expr, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$result = self::readDbListFile( $tokens[0] );
		while ( ( $op = next( $tokens ) ) && ( $term = next( $tokens ) ) ) {
			$dbs = self::readDbListFile( $term );
			if ( $op === '+' ) {
				$result = array_unique( array_merge( $result, $dbs ) );
			} else if ( $op === '-' ) {
				$result = array_diff( $result, $dbs );
			}
		}
		sort( $result );
		return $result;
	}

	/**
	 * Get an array of DB names from a .dblist file.
	 *
	 * @param $srcPath string
	 * @return Array
	 */
	public static function readDbListFile( $srcPath ) {
		$lines = @file( $srcPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( !$lines ) {
			throw new Exception( "Unable to read $srcPath.\n" );
		}

		$dbs = array();
		foreach ( $lines as $line ) {
			// Strip comments ('//' or '#' to end-of-line) and trim whitespace.
			$line = trim( preg_replace( '/(#|\/\/).*/', '', $line ) );
			if ( substr( $line, 0, 2 ) === '%%' ) {
				if ( !empty( $dbs ) ) {
					throw new Exception( "{$srcPath}: Encountered dblist expression inside a dblist list file.\n" );
				}
				$dir = getcwd();
				chdir( dirname( $srcPath ) );
				$dbs = self::evalDbListExpression( $line );
				chdir( $dir );
				break;
			} else if ( $line !== '' ) {
				$dbs[] = $line;
			}
		}
		return $dbs;
	}
}
