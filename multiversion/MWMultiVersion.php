<?php
require_once( __DIR__ . '/defines.php' );
require_once( __DIR__ . '/MWRealm.php' );
require_once( __DIR__ . '/vendor/autoload.php' );

/**
 * Class to handle basic information related to what
 * version of MediaWiki is running on a wiki installation.
 *
 * Avoid setting environmental or globals variables here for OOP.
 */
class MWMultiVersion {
	/**
	 * @var MWMultiVersion
	 */
	private static $instance;

	/**
	 * @var string
	 */
	private $db;
	/**
	 * @var string
	 */
	private $version;
	/**
	 * @var bool
	 */
	private $versionLoaded = false;

	/**
	 * To get an instance of this class, use the static helper methods.
	 * @see getInstanceForWiki
	 * @see getInstanceForUploadWiki
	 */
	private function __construct() {}
	private function __clone() {}

	/**
	 * Create a multiversion object based on a dbname
	 * @param string $dbName
	 * @return MWMultiVersion object for this wiki
	 */
	public static function newFromDBName( $dbName ) {
		$m = new self();
		$m->db = $dbName;
		return $m;
	}

	/**
	 * Create the singleton version instance
	 * @return MWMultiVersion object for this wiki
	 */
	private static function createInstance() {
		if ( isset( self::$instance ) ) {
			self::error( "MWMultiVersion instance already set!\n" );
		}
		self::$instance = new self;
		return self::$instance;
	}

	/**
	 * Initialize and get the singleton instance of MWMultiVersion.
	 * Use this for all web hits except to /w/thumb.php on upload.wikimedia.org.
	 * @param string $serverName the ServerName for this wiki -- $_SERVER['SERVER_NAME']
	 * @return MWMultiVersion object for this wiki
	 */
	public static function initializeForWiki( $serverName ) {
		$instance = self::createInstance();
		$instance->setSiteInfoForWiki( $serverName );
		return $instance;
	}

	/**
	 * Initialize and get the singleton instance of MWMultiVersion.
	 * Use this for web hits to /w/thumb.php on upload.wikimedia.org.
	 * @param string $pathInfo the PathInfo -- $_SERVER['PATH_INFO']
	 * @return MWMultiVersion object for the wiki derived from the pathinfo
	 */
	public static function initializeForUploadWiki( $pathInfo ) {
		$instance = self::createInstance();
		$instance->setSiteInfoForUploadWiki( $pathInfo );
		return $instance;
	}

	/**
	 * Initialize and get the singleton instance of MWMultiVersion.
	 * Use this for PHP CLI hits to maintenance scripts.
	 * @return MWMultiVersion object for the wiki derived from --wiki CLI parameter
	 */
	public static function initializeForMaintenance() {
		$instance = self::createInstance();
		$instance->setSiteInfoForMaintenance();
		return $instance;
	}

	/**
	 * Initialize and get the singleton instance of MWMultiVersion.
	 * Use this for all other special web entry points.
	 * @param string $dbName DB name
	 * @return MWMultiVersion object for this wiki
	 */
	public static function initializeFromDBName( $dbName ) {
		$instance = self::createInstance();
		$instance->db = $dbName;
		return $instance;
	}

	/**
	 * Get the singleton instance of MWMultiVersion that was previously initialized
	 * @return MWMultiVersion|null version object for the wiki
	 */
	public static function getInstance() {
		return self::$instance;
	}

	/**
	 * Destroy the singleton instance to let a subsequent call create a new
	 * one. This should NEVER be used on non CLI interface, that will throw an
	 * internal error.
	 */
	public static function destroySingleton() {
		if( PHP_SAPI !== 'cli' ) {
			self::error('Can not destroy singleton instance when used ' .
				'with non-CLI interface' );
		}
		self::$instance = null;
	}

	/**
	 * Derives site and lang from the parameters and sets $site and $lang on the instance
	 * @param string $serverName the ServerName for this wiki -- $_SERVER['SERVER_NAME']
	 */
	private function setSiteInfoForWiki( $serverName ) {
		$matches = array();

		$staticMappings = array(
			// Production
			'wikimediafoundation.org' => 'foundation',
			'test.wikidata.org' => 'testwikidata',
			'www.mediawiki.org' => 'mediawiki',
			'www.wikidata.org' => 'wikidata',
			'wikisource.org' => 'sources',
			'wikitech.wikimedia.org' => 'labs',
			'labtestwikitech.wikimedia.org' => 'labtest',
			'affcom.wikimedia.org' => 'chapcom',
			'be-tarask.wikipedia.org' => 'be_x_old',
			'ee.wikimedia.org' => 'et',

			// Labs
			'beta.wmflabs.org' => 'deployment',
			'wikidata.beta.wmflabs.org' => 'wikidata',
		);

		$site = "wikipedia";
		if ( getenv( 'MW_LANG' ) ) {
			# Language forced from some hacky script like extract2.php
			$lang = getenv( 'MW_LANG' );
		} elseif ( isset( $staticMappings[$serverName] ) ) {
			$lang = $staticMappings[$serverName];
			if ( $serverName === 'ee.wikimedia.org' ) {
				$site = "wikimedia";
			}
		} elseif ( strpos( $serverName, 'wmflabs' ) !== false ) {
			if ( preg_match( '/^([^.]+)\.([^.]+)\.beta\.wmflabs\.org$/', $serverName, $matches ) ) {
				// http://en.wikipedia.beta.wmflabs.org/
				$lang = $matches[1];
				if ( $matches[2] === 'wikimedia' ) {
					# Beta uses 'wiki' as a DB suffix for WikiMedia databases
					# Eg 'login.wikimedia.beta.wmflabs.org' => 'loginwiki'
					$site = 'wikipedia';
				} else {
					$site = $matches[2];
				}
			} else {
				self::error( "Invalid host name ($serverName).\n" );
			}
		} elseif ( preg_match( '/^(.*)\.([a-z]+)\.org$/', $serverName, $matches ) ) {
			$lang = $matches[1];
			if ( $matches[2] !== 'wikimedia'
				|| ( $matches[2] === 'wikimedia' && in_array(
					$lang,
					array(
						'ar', 'bd', 'be', 'br', 'ca', 'cn', 'co', 'dk', 'et', 'fi', 'il', 'mk', 'mx', 'nl',
						'noboard-chapters', 'no', 'nyc', 'nz', 'pa-us', 'pl', 'rs', 'ru', 'se', 'tr', 'ua', 'uk', 've'
					)
			) ) ) {
				// wikimedia (non chapters) sites stay as wiki
				$site = $matches[2];
			}
		} else {
			$ip = @$_SERVER['REQUEST_ADDR'];
			$xff = @$_SERVER['HTTP_X_FORWARDED_FOR'];
			$request = @$_SERVER['REQUEST_URI'];
			self::error( "Invalid host name (server: $serverName, request: $request, ip: $ip, xff: $xff).\n" );
		}
		$this->loadDBFromSite( $site, $lang );
	}

	/**
	 * Derives site and lang from the parameter and sets $site and $lang on the instance
	 * @param string $pathInfo the PathInfo -- $_SERVER['PATH_INFO']
	 */
	private function setSiteInfoForUploadWiki( $pathInfo ) {
		$pathBits = explode( '/', $pathInfo );
		if ( count( $pathBits ) < 3 ) {
			self::error( "Invalid file path info (pathinfo=" . $pathInfo . "), can't determine language.\n" );
		}
		$site = $pathBits[1];
		$lang = $pathBits[2];
		$this->loadDBFromSite( $site, $lang );
	}

	/**
	 * Gets the site and lang from the --wiki argument.
	 * This code reflects how Maintenance.php reads arguments.
	 */
	private function setSiteInfoForMaintenance() {
		global $argv;

		$dbname = '';
		# The --wiki param must the second argument to to avoid
		# any "options with args" ambiguity (see Maintenance.php).
		if ( isset( $argv[1] ) && $argv[1] === '--wiki' ) {
			$dbname = isset( $argv[2] ) ? $argv[2] : ''; // "script.php --wiki dbname"
		} elseif ( isset( $argv[1] ) && substr( $argv[1], 0, 7 ) === '--wiki=' ) {
			$dbname = substr( $argv[1], 7 ); // "script.php --wiki=dbname"
		} elseif ( isset( $argv[1] ) && substr( $argv[1], 0, 2 ) !== '--' ) {
			$dbname = $argv[1]; // "script.php dbname"
			$argv[1] = '--wiki=' . $dbname;
		}

		if ( $dbname === '' ) {
			self::error( "Usage: mwscript scriptName.php --wiki=dbname\n" );
		}

		$this->db = $dbname;
	}

	/**
	 * Load the DB from the site and lang for this wiki
	 * @param $site string
	 * @param $lang string
	 */
	private function loadDBFromSite( $site, $lang ) {
		if ( $site === "wikipedia" ) {
			$dbSuffix = "wiki";
		} else {
			$dbSuffix = $site;
		}
		$this->db = str_replace( "-", "_", $lang . $dbSuffix );
	}

	/**
	 * Get the DB name for this wiki
	 * @return String the database name
	 */
	public function getDatabase() {
		return $this->db;
	}

	/**
	 * Handler for the wfShellMaintenanceCmd hook.
	 * This converts shell commands like "php $IP/maintenance/foo.php" into
	 * commands that use the "MWScript.php" wrapper, for example:
	 * "php /srv/mediawiki-staging/multiversion/MWScript.php maintenance/foo.php"
	 *
	 * @param &$script string
	 * @param &$params Array
	 * @param &$options Array
	 * @return boolean
	 */
	public static function onWfShellMaintenanceCmd( &$script, array &$params, array &$options ) {
		global $IP;
		if ( strpos( $script, "{$IP}/" ) === 0 ) {
			$script = substr( $script, strlen( "{$IP}/" ) );
			$options['wrapper'] = __DIR__ . '/MWScript.php';
		}
		return true;
	}

	/**
	 * Get the space-seperated list of version params for this wiki.
	 * The first item is the MW version
	 */
	private function loadVersionInfo() {
		global $wmfRealm;

		if ( $this->versionLoaded ) {
			return;
		}
		$this->versionLoaded = true;

		if ( $wmfRealm === 'labs' ) {
			$phpFilename = MEDIAWIKI_DEPLOYMENT_DIR . '/wikiversions-labs.php';
		} else {
			$phpFilename = MEDIAWIKI_DEPLOYMENT_DIR . '/wikiversions.php';
		}

		$wikiversions = include( $phpFilename );

		if ( !is_array( $wikiversions ) ) {
			self::error( "Unable to open $phpFilename.\n" );
		}

		$version = isset( $wikiversions[$this->db] ) ? $wikiversions[$this->db] : false;

		if ( $version && strpos( $version, 'php-' ) !== 0 ) {
			self::error( "$phpFilename version entry does not start with `php-` (got `$version`).\n" );
		}

		$this->version = $version;
	}

	/**
	 * Check if this wiki is *not* specified in a php file
	 * located at /srv/mediawiki/wikiversions.php.
	 * @return bool
	 */
	public function isMissing() {
		$this->loadVersionInfo();
		return ( $this->version === false );
	}

	/**
	 * Get the version as specified in a php file located
	 * at /srv/mediawiki/wikiversions.php.
	 * Result is of the form "php-X.XX" or "php-trunk".
	 * @return String the version directory for this wiki
	 */
	public function getVersion() {
		$this->loadVersionInfo();
		if ( $this->version === false ) {
			self::error( "no version entry for `{$this->db}`.\n" );
		}
		return $this->version;
	}

	/**
	 * Get the version number as specified in a php file located
	 * at /srv/mediawiki/wikiversions.php. Do not use this
	 * to determine the path to cache or binary files, only the core MW code.
	 * @return String the version number for this wiki (e.g. "x.xx" or "trunk")
	 */
	public function getVersionNumber() {
		$this->loadVersionInfo();
		if ( $this->version === false ) {
			self::error( "no version entry for `{$this->db}`.\n" );
		}
		return substr( $this->version, 4 ); // remove "php-"
	}

	/**
	 * Error out and exit(1);
	 * @param $msg String
	 * @return void
	 */
	private static function error( $msg ) {
		$msg = (string)$msg;
		if ( PHP_SAPI !== 'cli' ) {
			$msg = htmlspecialchars( $msg );
			header( 'HTTP/1.1 500 Internal server error' );
		}
		echo $msg;
		trigger_error( $msg, E_USER_ERROR );
		exit( 1 ); // sanity
	}
}
