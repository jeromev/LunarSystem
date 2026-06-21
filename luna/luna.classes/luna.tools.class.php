<?php
/**
 * luna Tools class
 *
 * PHP versions 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 * For more details, see <http://www.gnu.org/copyleft/gpl.html>
 *
 * @author		Odradek <odradek@lunarsystem.org>
 * @license		http://www.gnu.org/copyleft/gpl.html  GPL
 * @link		http://lunarsystem.org
 * @package		lunarSystem
 */
// {{{
class lunaTools {
	// {{{ send_security_headers()
	/**
	 * Send a baseline set of HTTP security response headers, once, before output.
	 * CSP still allows 'unsafe-inline' because the admin UI uses inline
	 * onclick/onchange handlers; tighten to 'self' once those are delegated.
	 *
	 * @access public
	 * @return void
	 */
	public static function send_security_headers() {
		if (headers_sent()) { return; }
		header_remove('X-Powered-By');
		header('X-Content-Type-Options: nosniff');
		header('X-Frame-Options: DENY');
		header('Referrer-Policy: strict-origin-when-cross-origin');
		header('Cross-Origin-Opener-Policy: same-origin');
		header("Content-Security-Policy: default-src 'self'; "
			."script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
			."img-src 'self' data:; font-src 'self'; connect-src 'self'; "
			."object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
	}
	// }}}
	// {{{ password helpers (bcrypt, with legacy-md5 upgrade-on-login)
	/** Valid bcrypt hash used only to spend ~constant time on the unknown-user login
	 *  path, so a failed login can't be distinguished from a wrong password by timing. */
	const DUMMY_PASSWORD_HASH = '$2y$10$1AhGbI193T6v1gLbjdB/eOZ21C02WkBbepjqV0y6hPvQLZjjWCLxa';
	public static function hash_password($plain) {
		return password_hash((string) $plain, PASSWORD_DEFAULT);
	}
	public static function password_is_legacy($hash) {
		return (strlen((string) $hash) === 32 && ctype_xdigit((string) $hash));
	}
	public static function verify_password($plain, $hash) {
		if (self::password_is_legacy($hash)) {
			$ok = hash_equals((string) $hash, md5((string) $plain));
			password_verify((string) $plain, self::DUMMY_PASSWORD_HASH); // spend ~bcrypt time so legacy accounts aren't faster (no enumeration)
			return $ok;
		}
		return password_verify((string) $plain, (string) $hash);
	}
	// }}}
	// {{{ set_cookie()
	/**
	 * @param mixed data
	 * @access public
	 * @return boolean
	 */
	public static function set_cookie($label = false, $data = false, $time = false) {
		if (empty($label)) { return false; }
		if (empty($time)) { $time = NOW + lunaSession::$time_out; }
		if (!setcookie((string) $label, json_encode($data), array(
			'expires'  => $time,
			'path'     => luna::$site_relative_url ?: '/',
			'httponly' => true,
			'samesite' => 'Lax',
			'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
		))) { return false; }
		return true;
	}
	// }}}
	// {{{ request()
	/**
	 * look for a given user variable (get, post, cookie or request)
	 * @access public
	 * @param string $var
	 * @param array $in
	 * @param string $default
	 * @return mixed
	 */
	public static function request($var = false, $in = false, $default = false) {
		if (empty($var) || !is_string($var)) { return false; }
		if (empty($in)) {
			if (isset($_GET["$var"]) && !empty($_GET["$var"])) { return $_GET["$var"]; }
			if (isset($_POST["$var"]) && !empty($_POST["$var"])) { return $_POST["$var"]; }
			if (isset($_SESSION["$var"]) && !empty($_SESSION["$var"])) { return $_SESSION["$var"]; }
			if (isset($_COOKIE["$var"]) && !empty($_COOKIE["$var"])) { return $_COOKIE["$var"]; }
			if (isset($_REQUEST["$var"]) && !empty($_REQUEST["$var"])) { return $_REQUEST["$var"]; }
		} else if (is_array($in)) {
			foreach ($in as $array) { if (is_array($array) && isset($array["$var"]) && !empty($array["$var"])) { return $array["$var"]; } }
		}
		return $default;
	}
	// }}}
	// {{{ unrequest()
	/**
	 * destroy a user variable (get, post, cookie or request)
	 * @access public
	 * @param mixed $var the requested var name
	 * @return mixed
	 */
	public static function unrequest($var=false, $here=false) {
		if (empty($var)) { return false; }
		if (empty($here)) {
			if (!is_array($var) && is_string($var)) {
				if (isset($_GET[$var])) { unset($_GET[$var]); }
				if (isset($_POST[$var])) { unset($_POST[$var]); }
				if (isset($_SESSION[$var])) { unset($_SESSION[$var]); }
				if (isset($_COOKIE[$var])) { unset($_COOKIE[$var]); }
				if (isset($_REQUEST[$var])) { unset($_REQUEST[$var]); }
			} else if (is_array($var)) {
				foreach ($var as $v) {
					if (is_string($v) && !empty($v)) {
						if (isset($_GET[$v]))		{ unset($_GET[$v]); }
						if (isset($_POST[$v]))		{ unset($_POST[$v]); }
						if (isset($_SESSION[$v]))	{ unset($_SESSION[$v]); }
						if (isset($_COOKIE[$v]))	{ unset($_COOKIE[$v]); }
						if (isset($_REQUEST[$v]))	{ unset($_REQUEST[$v]); }
					}
				}
			}
		} else if (is_array($here)) {
			foreach ($here as $array) { if (is_array($array) && isset($array[$var])) { unset($array[$var]); } }
		}
		return true;
	}
	// }}}
	// {{{ convert_to_unicode()
	/**
	 * convert string to unicode
	 * @access public
	 * @param string $str
	 * @return string
	 */
	public static function convert_to_unicode($str = false) {
		if (empty($str) || !is_string($str)) { return false; }
		$input_charset = mb_detect_encoding($str, 'UTF-8, ISO-8859-1');
		if ($input_charset != 'UTF-8') {
			$str = str_replace(chr(146), "'", $str);
			$str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
		}
		return $str;
	}
	// }}}
	// {{{ prepare_lid()
	/**
	 * prepare a string to be a good lid: no cap, no accent, just [a-z8-9]
	 * @access public
	 * @param string $str
	 * @return string
	 */
	public static function prepare_lid($str = false) { 
		if (!defined('INPUT_SANITIZED') || INPUT_SANITIZED != true) { self::sanitize_inputs(); }
		if (empty($str) || !is_string($str)) { return false; }
		$str = self::remove_accents($str);
		$str = strtolower(trim($str)); 
		$search = array(
			'@\s+@', 
			'@\?|\.|\,|\:+@', 
			"@\'@"
		);
		$replace = array(
			'_', 
			'', 
			'-'
		);
		return preg_replace($search, $replace, $str);
	}
	// }}}
	// {{{ add_vocabulary()
	/**
	 * @access public
	 * @param array $lids
	 * @return boolean
	 */
	public static function add_vocabulary($lids = false, $bindtextdomain = false) { 
		if (empty($lids) || !is_array($lids)) { return false; }
		foreach ($lids as $lid) { 
			if (!empty($bindtextdomain) && is_string($bindtextdomain)) {
				luna::$vocabulary["$lid"] = dgettext("$bindtextdomain", "$lid");
			} else {
				luna::$vocabulary["$lid"] = _("$lid");
			}
		}
		return true;
	}
	// }}}
	// {{{ set_output_format()
	/**
	 * @access public
	 * @return string
	 */
	public static function set_output_format() { 
		$output = false; 
		if (empty(luna::$path)) { $output = luna::$output_formats[0]; } 
		if (in_array(luna::$path, luna::$output_formats)) { 
			$output = luna::$path;
			luna::$path = '';
		} else if (strpos(luna::$path, '/') === false) { 
			$output = false; 
		} else { 
			$patharray = explode('/', luna::$path); 
			foreach ($patharray as $k => $v) { if (empty($v)) { unset($patharray[$k]); } }
			$subdir = array_pop($patharray);
			if (in_array($subdir, luna::$output_formats)) { 
				luna::$path = implode('/', $patharray);
				$output = "$subdir"; 
			}
		}
		$request = self::request('output');
		if (!empty($request) && in_array("$request", luna::$output_formats)) { $output = "$request"; } 
		if (empty($output)) { $output = $output = luna::$output_formats[0]; }
		// die($output);
		return $output;
	}
	// }}}
	// {{{ format_date()
	/**
	 * @access public 
	 * @param integer $time
	 * @param string $format
	 * @return string
	 */
	public static function format_date($time = NOW, $format = 'Y-m-d H:i') {
		$time = intval($time);
		if (empty($time)) { return ''; }
		if (empty($format) || !is_string($format)) { $format = 'Y-m-d H:i'; }
		return date($format, $time);
	}
	// }}}
	// {{{ display_string()
	/**
	 * @access public 
	 * @param string $string
	 * @return $string
	 */
	public static function display_string($string = false) {
		if (empty($string) || !is_string($string)) { return false; }
		$string = str_replace("&", '&amp;', stripslashes($string));
		$string = str_replace("'", '’', stripslashes($string));
		return $string;
	}
	// }}}
	// {{{ insert_alphabet_nav()
	/**
	 * @access public 
	 * @param array $letters
	 * @param string $theletter
	 * @param string $tagname
	 * @return void
	 */
	public static function insert_alphabet_nav($letters, $theletter = 'A', $tagname = 'alphabeticlist') {
		if (is_object($xml) && is_array($letters) && is_string($theletter) && is_string($tagname)) {
			$parentnode = $xml->appendChild($xml->getItem($xml->query('/node')), $xml->createElement($tagname));
			foreach ($letters as $letter) { 
				$childnode = $xml->appendChild($parentnode, $xml->createElement('letter'));
				$xml->setAttributes($childnode, array('value' => $letter, 'current' => (($letter == $theletter)? '1' : '0')));
			}
		}
	}
	// }}}
	// {{{ raise_error_page()
	/**
	 * @access public
	 * @param integer $error
	 * @return void
	 */
	public static function raise_error_page($error = 404, $path = false) {
		static $http = array (
			100 => "HTTP/1.1 100 Continue",
			101 => "HTTP/1.1 101 Switching Protocols",
			200 => "HTTP/1.1 200 OK",
			201 => "HTTP/1.1 201 Created",
			202 => "HTTP/1.1 202 Accepted",
			203 => "HTTP/1.1 203 Non-Authoritative Information",
			204 => "HTTP/1.1 204 No Content",
			205 => "HTTP/1.1 205 Reset Content",
			206 => "HTTP/1.1 206 Partial Content",
			300 => "HTTP/1.1 300 Multiple Choices",
			301 => "HTTP/1.1 301 Moved Permanently",
			302 => "HTTP/1.1 302 Found",
			303 => "HTTP/1.1 303 See Other",
			304 => "HTTP/1.1 304 Not Modified",
			305 => "HTTP/1.1 305 Use Proxy",
			307 => "HTTP/1.1 307 Temporary Redirect",
			400 => "HTTP/1.1 400 Bad Request",
			401 => "HTTP/1.1 401 Unauthorized",
			402 => "HTTP/1.1 402 Payment Required",
			403 => "HTTP/1.1 403 Forbidden",
			404 => "HTTP/1.1 404 Not Found",
			405 => "HTTP/1.1 405 Method Not Allowed",
			406 => "HTTP/1.1 406 Not Acceptable",
			407 => "HTTP/1.1 407 Proxy Authentication Required",
			408 => "HTTP/1.1 408 Request Time-out",
			409 => "HTTP/1.1 409 Conflict",
			410 => "HTTP/1.1 410 Gone",
			411 => "HTTP/1.1 411 Length Required",
			412 => "HTTP/1.1 412 Precondition Failed",
			413 => "HTTP/1.1 413 Request Entity Too Large",
			414 => "HTTP/1.1 414 Request-URI Too Large",
			415 => "HTTP/1.1 415 Unsupported Media Type",
			416 => "HTTP/1.1 416 Requested range not satisfiable",
			417 => "HTTP/1.1 417 Expectation Failed",
			500 => "HTTP/1.1 500 Internal Server Error",
			501 => "HTTP/1.1 501 Not Implemented",
			502 => "HTTP/1.1 502 Bad Gateway",
			503 => "HTTP/1.1 503 Service Unavailable",
			504 => "HTTP/1.1 504 Gateway Time-out"		 
		);
		$errormsg = $http[$error].' "'.$path.'"';
		// lunaLog::log($errormsg, PEAR_LOG_NOTICE);
		$message = sprintf(_($http[$error].": %1\$s."), htmlspecialchars($path, ENT_QUOTES));
		luna::$messages['warning'][] = $message;
		lunaLog::log($message, PEAR_LOG_NOTICE);
		header($http[$error]); 
		die($message);
	}
	// }}}
	// {{{ go()
	/**
	 * @param string $where
	 * @param string $is_alias
	 * @access public
	 * @return void
	 */
	public static function go($where = 'root', $is_alias = false) {
		$url = self::link("$where");
		header("location: ".$url);
		exit;
	}
	// }}}
	// {{{ link()
	/**
	 * @param string $alias
	 * @param boolean $absolute
	 * @access public
	 * @return string
	 */
	public static function link($alias = 'root', $absolute = false) {
		$alias = preg_replace('@^\/*(.*?)\/*$@', '$1', "$alias");
		$url = $absolute? luna::$site_uri.'/' : luna::$site_relative_url;
		if (defined('CLEAN_URLS') && CLEAN_URLS) {
			return $url.$alias;
		} else {
			return $url."?path=$alias";
		}
	}
	// }}}
	// {{{ append_to_link()
	/**
	 * @param string $name
	 * @param mixed $value
	 * @param string $url
	 * @param boolean $non_html_amp
	 * @access public
	 * @return string
	 */
	public static function append_to_link($name = 'var', $value = '', $url = false, $html_amp = true) {
		$url = empty($url)? self::link(luna::$path) : $url;
		$url = str_replace('&amp;', '&', $url);
		if (!preg_match("/".$name."=/", $url)) {
			$anchor = '';
			if (strpos($url,'#') !== false) {
				if (preg_match("/\#([^\W]+)$/", $url, $regs)) {
					$anchor = $regs[1];
					$url = str_replace('#'.$anchor, '', $url);
				}
			}
			if (!preg_match("/\/$/", $url) && (strpos($url,'=') === false)) { $url .= '/'; }
			$url .= ((strpos($url, '=') !== false) ? '&' : '?').$name.'='.$value;
			if (!empty($anchor)) { $url .= '#'.$anchor; }
		} else {
			$url = preg_replace("/".$name."=[^&]+/", $name.'='.$value, $url);
		}
		// if ($html_amp) { $url = str_replace('&', '&amp;', $url); }
		return $url;
	}
	// }}}
	// {{{ sanitize_inputs()
	/**
	 * clean $_GET, $_POST, $_COOKIE & $_REQUEST
	 *
	 * @access public
	 * @return boolean
	 */
	public static function sanitize_inputs() {
		$_GET = isset($_GET) && !empty($_GET)? self::sanitize($_GET) : array();
		$_POST = isset($_POST) && !empty($_POST)? self::sanitize($_POST) : array();
		$_COOKIE = isset($_COOKIE) && !empty($_COOKIE)? self::sanitize($_COOKIE) : array();
		$_SESSION = isset($_SESSION) && !empty($_SESSION)? self::sanitize($_SESSION) : array();
		$_REQUEST = isset($_REQUEST)  && !empty($_REQUEST)? self::sanitize($_REQUEST) : array();
		unset(
			$GLOBALS['HTTP_POST_VARS'],
			$GLOBALS['HTTP_GET_VARS'],
			$GLOBALS['HTTP_COOKIE_VARS'],
			$GLOBALS['HTTP_SESSION_VARS'],
			$GLOBALS['HTTP_SERVER_VARS'],
			$GLOBALS['HTTP_ENV_VARS'],
			$GLOBALS['HTTP_POST_FILES']
		);
		if (isset($_GET['path']) && strpos($_GET['path'], '?') !== false) {
			preg_match_all('@(\?|\&)(.*?)\=(.+)\&*@s', $_GET['path'], $matches);
			if (isset($matches[2][0]) && isset($matches[3][0])) { $_GET[$matches[2][0]] = $matches[3][0]; }
			$_GET['path'] = preg_replace('@(\?|\&)(.*?)\=(.+)\&*@s', '', $_GET['path']);
		}
		define('INPUT_SANITIZED', true);
		return true;
	}
	// }}}
	// {{{ sanitize()
	/**
	 * @access public
	 * @param mixed $stuff
	 * @return mixed
	 */
	public static function sanitize($stuff = false) {
		// require PEAR HTML_Safe Class
		if (!require_once 'HTML/Safe.php') { throw new lunaException(_('Error: cannot find lib: ').'PEAR HTML_Safe', PEAR_LOG_CRIT); }
		if (empty($stuff)) { return false; }
		if (is_array($stuff)) {
			foreach ($stuff as $k => $v) { $stuff[$k] = self::sanitize($v); }
		} else if (is_object($stuff)) {
			$array = get_object_vars($stuff);
			$array = self::sanitize($array);
			$stuff = self::array_to_object($array);
		} else {
			$safehtml = new HTML_Safe();
			if (!$stuff = $safehtml->parse($stuff)) { throw new lunaException(_('Error: cannot sanitize input.'), PEAR_LOG_CRIT); }
		}
		return $stuff;
	}
	// }}}
	// {{{ check_email()
	/**
	 * check_email
	 *
	 * @access public
	 * @param string $email
	 * @return mixed
	 */
	public static function check_email($email = '') { return (preg_match('/^[A-z0-9][\w.-]*@[A-z0-9][\w\-\.]+\.[A-z0-9]{2,6}$/', $email)); }
	// }}}
	// {{{
	/** 
	 * echo_screen
	 *
	 * @access public
	 * @param string $string
	 * @return object
	 */
	public static function echo_screen($string = '. ') { echo("$string"); ob_flush(); flush(); }
	// }}}
	// {{{ load_config()
	/**
	 * load config from database
	 *
	 * @access public
	 * @return mixed
	 */
	public static function load_config() {
		if (luna::$cache) { $cache_obj = new Cache_Lite(array('cacheDir' => CACHE_PATH, 'lifetime' => luna::$cache_timeout)); }
		if (luna::$cache && ($cache_str = $cache_obj->get('Config'))) {
			return unserialize($cache_str, array('allowed_classes' => false));
		} else {
			// load config
			$config = array();
			$res = lunaDB::query('SELECT * FROM '.luna::get_ini('DBtables', 'CONFIG'));
			while ($row = $res->fetchRow()) { $config[$row->name] = $row->value; }
			$res->free();
			// parse langs
			$langs = explode(',', str_replace(' ', '', $config['langs'])); 
			foreach($langs as $v) { $config['site_langs'][] = self::format_language($v); }
			// unset($config['langs']);
			if (luna::$cache) { $cache_obj->save(serialize($config)); }
			return $config;
		}
		return false;
	}
	// }}}
	// {{{ format_language()
	/**
	 * @param string str
	 * @access public
	 * @return string
	 */
	public static function format_language($str = false, $separator = '-') {
		if (empty($separator)) { $separator = '-'; }
		$search = ($separator == '_')? '-' : '_';
		$replace = ($search == '-')? '_' : '-';
		if (empty($str)) { return ''; }
		$str = str_replace($search, $replace, $str);
		if (strpos($str, $replace) === false) {
			// Region defaults to the upper-cased language code, but some languages
			// have a canonical region that differs (English -> US, not the bogus "EN").
			$lc = substr($str, 0, 2);
			$regions = array('en' => 'US');
			$str .= $replace.(isset($regions[$lc])? $regions[$lc] : strtoupper($lc));
		} else {
			$str_array = explode($replace, $str); 
			$str = substr($str_array[0], 0, 2).$replace.strtoupper(substr($str_array[1], 0, 2)); 
		}
		return $str;
	}
	// }}}
	// {{{ set_language()
	/**
	 * Set the language using Gettext
	 * @access public
	 * @return string
	 */
	public static function set_language() {
		$lang = false;
		$lang_requested = self::format_language(self::request('lang')); 
		$site_langs = luna::get_ini('config', 'site_langs'); 
		luna::$session->user->session_lang = self::format_language(luna::$session->user->session_lang);
		$httplangs = array();
		if (empty($lang_requested) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$http_accept_languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']); 
			$qcandidat = 0;
			$nblang = count($http_accept_languages);
			for ($i = 0; $i < $nblang; $i++) {
				for ($j = 0; $j < count($http_accept_languages); $j++) {
					$http_accept_language = trim($http_accept_languages[$j]);
					if (!strstr($http_accept_language, ';') && $qcandidat != 1) {
						$candidat = $http_accept_language;
						$qcandidat = 1;
						$indicecandidat = $j;
					} else {
						$q = preg_replace('/.*;q=(.*)/', '$1', $http_accept_language);
						if ($q > $qcandidat) {
							$candidat = preg_replace('/(.*);.*/', '$1', $http_accept_language);
							$qcandidat = $q;
							$indicecandidat = $j;     
						}
					}
				}
				$httplangs[$i] = self::format_language($candidat);
				$qcandidat=0;
				unset($http_accept_languages[$indicecandidat]);   
				$http_accept_languages = array_values($http_accept_languages);
			}
		}
		if (!empty($lang_requested) && in_array($lang_requested, $site_langs)) {
			$lang = $lang_requested;
		} else if (!empty($httplangs) && in_array($httplangs[0], $site_langs)) {
			$lang = $httplangs[0];
		} else if (isset(luna::$session->user->session_lang) && in_array(luna::$session->user->session_lang, $site_langs)) {
			$lang = luna::$session->user->session_lang;
		}
		if (!isset($lang) || empty($lang)) { $lang = $site_langs[0]; }
		$lang_ = self::format_language($lang, '_'); 
		putenv('LANGUAGE='.$lang_.'.UTF-8');
		putenv('LANG='.$lang_.'.UTF-8');
		setlocale(LC_ALL, $lang_.'.UTF-8');
		$domain = 'luna';
		bindtextdomain($domain, LOCALE_PATH);
		textdomain($domain);
		bind_textdomain_codeset($domain, 'UTF-8');
		// Site-specific page labels live in a separate, git-ignored 'local' domain,
		// layered over the engine 'luna' catalog by lunaTools::label().
		bindtextdomain('local', LOCALE_PATH);
		bind_textdomain_codeset('local', 'UTF-8');
		$_SESSION['lang'] = $lang;
		lunaTools::set_cookie('lang', $lang);
		define('LANG', $lang); 
		if (function_exists('locale_set_default')) { locale_set_default($lang_); }
		return $lang;
	}
	// }}}
	// {{{ label()
	/**
	 * Localise a node label/slug. Tries the site-specific, git-ignored 'local'
	 * gettext domain first (per-site page labels), then falls back to the engine
	 * 'luna' catalog, then the raw lid.
	 *
	 * @access public
	 * @param string $lid
	 * @return string
	 */
	public static function label($lid) {
		$t = dgettext('local', "$lid");
		return ($t !== "$lid") ? $t : _("$lid");
	}
	// }}}
	// {{{ check_cache()
	/**
	 * check cache
	 *
	 * @access public
	 * @return boolean
	 */
	public static function check_cache() {
		// require PEAR Cache_lite Class
		if (!require_once 'Cache/Lite.php') { return false; }
		luna::$cache = CACHE? true : false; 
		if (file_exists(SITEPATH.'cache') && is_dir(SITEPATH.'cache')) {
			define('CACHE_PATH', SITEPATH.'cache/');
		} else {
			if (!$dir = mkdir(SITEPATH.'cache', 0700)) { return false; }
			define('CACHE_PATH', SITEPATH.'cache/');
		}
		// (Request-driven ?purge removed: it ran pre-session so no CSRF token was available, and every mutating mod already calls purge_cache() internally after its gated action.)
		return true;
	}
	// }}}
	// {{{ purge_cache()
	/**
	 * purge cache
	 *
	 * @access public
	 * @return boolean
	 */
	public static function purge_cache() {
		$Cache_Lite = new Cache_Lite(array('cacheDir' => CACHE_PATH));
		$Cache_Lite->clean();
		luna::$cache = false;
		return true;
	}
	// }}}
	// {{{ format_url()
	/**
	 * @access public
	 * @param string url
	 * @return string
	 */
	public static function format_url($url) { return $url; }
	// }}}
	// {{{ encode_ip()
	/**
	 * This function is copied from phpBB (2.0.5), under the terms of the GPL
	 * file: functions.php
	 * begin: Saturday, Feb 13, 2001
	 * copyright: (C) 2001 The phpBB Group
	 * email: support@phpbb.com
	 *
	 * @access public
	 * @return string
	 */
	public static function encode_ip() {
		// Use REMOTE_ADDR only. HTTP_X_FORWARDED_FOR is fully client-controlled,
		// and trusting it here let an attacker spoof the IP the session is bound
		// to (see get_user_data()'s session_ip check). If this CMS is ever put
		// behind a trusted reverse proxy, resolve the real client IP there.
		$ip = (!empty($_SERVER['REMOTE_ADDR']))? $_SERVER['REMOTE_ADDR'] : getenv('REMOTE_ADDR');
		$ip_sep = explode('.', $ip);
		if (count($ip_sep) != 4) { return '00000000'; }
		return sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
	}
	// }}}
	// {{{ decode_ip()
	/**
	 * This function is copied from phpBB (2.0.5), under the terms of the GPL
	 * file: functions.php
	 * begin: Saturday, Feb 13, 2001
	 * copyright: (C) 2001 The phpBB Group
	 * email: support@phpbb.com
	 *
	 * @access public
	 * @param string $ip
	 * @return string
	 */
	public static function decode_ip($ip) {
		$hexipbang = explode('.', chunk_split($ip, 2, '.'));
		return hexdec($hexipbang[0]).'.'.hexdec($hexipbang[1]).'.'.hexdec($hexipbang[2]).'.'.hexdec($hexipbang[3]);
	}
	// }}}
	// {{{ get_time_since()
	/**
	 * @access public
	 * @param integer $time
	 * @param boolean $abrv
	 * @return string
	 */
	public static function get_time_since($time = false, $abrv = false) {
		$time = intval($time);
		if (empty($time)) { return ''; }
		$secondes = NOW - $time;
		$minutes = floor($secondes / 60);
		$secondes -= $minutes * 60;
		$hours = floor($minutes / 60);
		$minutes -= $hours * 60;
		$days = floor($hours / 24);
		$hours -= $days * 24;
		$years = floor($days / 365.25); 
		$days -= floor($years * 365.25);
		if ($years)		{ return ($years.' '.($abrv? substr(($years > 1 ? _('years') : _('year')), 0, 1).'.' : ($years > 1 ? _('years') : _('year'))).' '); }
		if ($days)		{ return ($days.' '.			($abrv? substr(($days > 1 ? _('days') : _('day')), 0, 1).'.' : ($days > 1 ? _('days') : _('day'))).' '); }
		if ($hours)		{ return ($hours.' '.		($abrv? substr(($hours > 1 ? _('hours') : _('hour')), 0, 1).'.' : ($hours > 1 ? _('hours') : _('hour'))).' '); }
		if ($minutes)	{ return ($minutes.' '.	($abrv? substr(($minutes > 1 ? _('minutes') : _('minute')), 0, 1).'.' : ($minutes > 1 ? _('minutes') : _('minute'))).' '); }
		if ($secondes)	{ return ($secondes.' '.	($abrv? substr(($secondes > 1 ? _('secondes') : _('seconde')), 0, 1).'.' : ($secondes > 1 ? _('secondes') : _('seconde'))).' '); }
	}
	// }}}
	// {{{ check_privileges()
	/**
	 * @access public
	 * @param integer $level_nid
	 * @return boolean
	 */
	public static function check_privileges($level_nid = false) { 
		$level_nid = intval($level_nid);
		if (empty($level_nid)) {
			if (!$level_nid = luna::$model->get_nid(luna::$model->get_level_node(luna::$page_node))) { return false; }
		}
		$res = false;
		if (isset(luna::$session->user->levels[$level_nid])) { $res = true; }
		if (luna::get_ini('config', 'disable')) {
			// admins can access the website, even if it is down
			if (!self::user_can_access_level(luna::$session->user, 'level_admin')) { die(luna::get_ini('config', 'disable_txt')? _(luna::get_ini('config', 'disable_txt')) : _('This website is temporarily down.')); }
		}
		return $res;
	}
	// }}}
	// {{{ user_can_access_level()
	/**
	 * @access public
	 * @param object $user
	 * @param mixed $level
	 * @return boolean
	 */
	public static function user_can_access_level($user = false, $level = false) { 
		if (!is_object($user) || empty($user)) { return false; }
		if (empty($level)) { return false; }
		if (is_string($level)) { $level = luna::$model->get_nid_from_lid($level); }
		if (isset($user->levels[$level])) { return true; }
		return false;
	}
	// }}}
	// {{{ user_can_access_page()
	/**
	 * True when the current user may act on $page_node — i.e. can access the level
	 * the page is bound to. Fail-closed: a page with no resolvable level is denied.
	 * @access public
	 */
	public static function user_can_access_page($page_node = false) {
		if (empty($page_node) || !is_array($page_node)) { return false; }
		$level_node = luna::$model->get_level_node($page_node);
		if (!$level_node) { return false; }
		$level_nid = intval(luna::$model->get_nid($level_node, 'level'));
		return self::user_can_access_level(luna::$session->user, $level_nid);
	}
	// }}}
	// {{{ user_can_access_group()
	/**
	 * True when $user holds EVERY level the group grants, so assigning this group
	 * hands out no level the actor lacks — stops a delegated admin escalating via
	 * group assignment. (An unknown/level-less group grants nothing, so true.)
	 * @access public
	 */
	public static function user_can_access_group($user = false, $group_nid = false) {
		if (!is_object($user) || empty($user)) { return false; }
		$group_nid = intval($group_nid);
		if (empty($group_nid)) { return false; }
		$nodes = luna::get_ini('DBtables', 'NODES'); $map = luna::get_ini('DBtables', 'NODES_MAP'); $types = luna::get_ini('DBtables', 'CLASSES');
		$res = lunaDB::query('
			SELECT l.nid AS level_nid
			FROM '.$map.' gl
			JOIN '.$nodes.' l ON l.nid = gl.nid2 AND l.tid = (SELECT id FROM '.$types.' WHERE lid = '.lunaDB::quote('level').')
			WHERE gl.nid1 = '.lunaDB::quote($group_nid).'
		');
		while ($row = $res->fetchRow()) {
			if (!self::user_can_access_level($user, intval($row->level_nid))) { $res->free(); return false; }
		}
		$res->free();
		return true;
	}
	// }}}
	// {{{ user_can_act_on_text()
	/**
	 * True when the current user may modify/delete $text_nid — i.e. can access the
	 * level of EVERY page the text is linked to (so a text living on a higher-level
	 * page cannot be edited from below). A text with no pages is allowed.
	 * @access public
	 */
	public static function user_can_act_on_text($text_nid) {
		$text_nid = intval($text_nid);
		if (empty($text_nid)) { return false; }
		$nodes = luna::get_ini('DBtables', 'NODES'); $map = luna::get_ini('DBtables', 'NODES_MAP'); $types = luna::get_ini('DBtables', 'CLASSES');
		$levels = implode(',', array_map('intval', (array) luna::$session->user->levels)) ?: '0';
		// Fail closed: deny unless EVERY distinct page the text links to has a level the
		// user holds (a page with no resolvable level counts as inaccessible).
		$res = lunaDB::query('
			SELECT COUNT(DISTINCT p.nid) AS total,
			       COUNT(DISTINCT CASE WHEN l.nid IN ('.$levels.') THEN p.nid END) AS allowed
			FROM '.$map.' tp
			JOIN '.$nodes.' p ON p.nid = tp.nid2 AND p.tid = (SELECT id FROM '.$types.' WHERE lid = '.lunaDB::quote('page').')
			LEFT JOIN '.$map.' pl ON pl.nid1 = p.nid
			LEFT JOIN '.$nodes.' l ON l.nid = pl.nid2 AND l.tid = (SELECT id FROM '.$types.' WHERE lid = '.lunaDB::quote('level').')
			WHERE tp.nid1 = '.lunaDB::quote($text_nid).'
		');
		$row = $res->fetchRow(); $res->free();
		return ($row && intval($row->total) === intval($row->allowed));
	}
	// }}}
	// {{{ remove_accents()
	/**
	 * This function is based on the function 'remove_accents', by WordPress 2.0.4 (see file 'functions-formatting.php', line 143)
	 * Licence GNU GPL
	 * copyright 2006 Wordpress
	 *
	 * @access public
	 * @return boolean
	 */
	public static function remove_accents($string) {
		$chars = array(
		// Decompositions for Latin-1 Supplement
		chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
		chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
		chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
		chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
		chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',chr(195).chr(159) => 's', chr(195).chr(160) => 'a',chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
		chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
		chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
		chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
		chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
		chr(195).chr(191) => 'y',
		// Decompositions for Latin Extended-A
		chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
		chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
		chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
		chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
		chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
		chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
		chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
		chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
		chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',chr(196).chr(178) =>'IJ', chr(196).chr(179) =>'ij',chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
		chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
		chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
		chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
		chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
		chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
		chr(197).chr(148) => 'R', chr(197).chr(149) => 'r',chr(197).chr(150) => 'R', chr(197).chr(151) => 'r',chr(197).chr(152) => 'R', chr(197).chr(153) => 'r',
		chr(197).chr(154) => 'S', chr(197).chr(155) => 's',chr(197).chr(156) => 'S', chr(197).chr(157) => 's',chr(197).chr(158) => 'S', chr(197).chr(159) => 's',
		chr(197).chr(160) => 'S', chr(197).chr(161) => 's',chr(197).chr(162) => 'T', chr(197).chr(163) => 't',chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
		chr(197).chr(166) => 'T', chr(197).chr(167) => 't',chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
		chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
		chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
		chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
		chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
		// Euro Sign
		chr(226).chr(130).chr(172) => 'E');
		return strtr($string, $chars);
	}
	// }}}
	// {{{ parse_sort_cookie()
	/**
	 * @access public
	 * @param string $lid
	 * @return boolean
	 */
	public static function parse_sort_cookie($lid = '') {
		if (isset($_COOKIE["$lid".'_sort'])) {
			// json_decode (not unserialize) so a crafted cookie cannot inject a PHP object.
			$cookie = json_decode($_COOKIE["$lid".'_sort'], true);
			if (!is_array($cookie)) { return false; }
			$cookie = self::sanitize($cookie);
			if (!is_array($cookie)) { return false; }
			foreach ($cookie as $k => $v) { $_COOKIE[$k] = $v; }
			return true;
		}
		return false;
	}
	// }}}
	// {{{ array_to_object()
	/**
	 * @access public
	 * @param array $array
	 * @return array
	 * @source http://www.lost-in-code.com/39/php-array-to-object/ Posted on May 6th, 2008 by lost-in-code 
	 */
	public static function array_to_object($array = array()) {
		if (!empty($array)) {
			$data = new stdClass();
			foreach ($array as $k => $v) { $data -> {$k} = $v; }
			return $data;
		}
		return false;
	}
	// }}}
	// {{{ check_emptyness()
	/**
	 * @access public
	 * @param string $var
	 * @param string $field
	 * @param string $level
	 * @return boolean
	 */
	public static function check_emptyness($var = 'var', $field = false, $level = 'warning') {
		if (empty($field) || !is_string($field)) { $field = "$var"; }
		if (!isset($_POST[$var]) || empty($_POST[$var])) {
			$message = sprintf(_("Field “%1\$s” cannot be empty."), _($field));
			luna::$messages[$level][] = $message;
			lunaLog::log($message, PEAR_LOG_NOTICE);
			return false;
		}
		return true;
	}
	// }}}
}
// }}}
?>