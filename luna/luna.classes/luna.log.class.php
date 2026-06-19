<?php
/**
 * lunar Exception class
 *
 * PHP versions 5.6 – 8.x
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

// PEAR Log (base class) supplies the PEAR_LOG_* priority constants used across
// the app and Log::priorityToString() used by mod_journal. The MDB2-backed Log
// handler is gone (MDB2 removed in 0.5.0-alpha); lunaLog::log() now writes the
// log row directly via the PDO lunaDB layer below.
require_once 'Log.php';
// {{{
class lunaException extends Exception { public $session; public $server; }
// }}}
// {{{
class lunaLog {
	// {{{
	/**
	 * log
	 * @access public
	 * @param object|string $e
	 * @param string $code
	 * @return boolean
	 */
	public static function log($e, $code = PEAR_LOG_ERR) {
		if (defined('DEBUG') && DEBUG && defined('IS_ADMIN') && IS_ADMIN) { lunaTools::debug($e); }
		if (!luna::get_ini('DBtables', 'LOGS')) { return false; }
		if (is_object($e) && get_class($e) == 'lunaException') {
			$e->session = luna::$session;
			$e->server = self::server_whitelist();
			$message = serialize($e);
			$priority = $e->getCode() ? (int) $e->getCode() : PEAR_LOG_ERR;
		} else if (is_string($e)) {
			$o = new stdClass();
			$o->message = $e;
			$o->session = luna::$session;
			$o->server = self::server_whitelist();
			$message = serialize($o);
			$priority = (int) $code;
		} else {
			return false;
		}
		// Direct INSERT — replaces the old PEAR Log 'mdb2' handler. logtime
		// auto-fills (CURRENT_TIMESTAMP). Never let a logging failure throw (it
		// would recurse through the exception path), so swallow any DB error.
		try {
			lunaDB::query('INSERT INTO '.luna::get_ini('DBtables', 'LOGS').'
				(ident, priority, message) VALUES ('
				.lunaDB::quote('luna').', '
				.lunaDB::quote($priority).', '
				.lunaDB::quote($message).')');
		} catch (Exception $ex) {
			return false;
		}
		return true;
	}
	// }}}
	// {{{ server_whitelist()
	/**
	 * A trimmed copy of $_SERVER for logging. The full $_SERVER carries the
	 * cookie header (incl. the session id), auth headers and env, which should
	 * not be persisted into luna_logs on every error.
	 *
	 * @access private
	 * @return array
	 */
	private static function server_whitelist() {
		$keep = array('REMOTE_ADDR', 'REQUEST_METHOD', 'REQUEST_URI', 'SERVER_NAME', 'HTTP_HOST', 'HTTP_USER_AGENT', 'HTTP_REFERER', 'REQUEST_TIME');
		$server = array();
		foreach ($keep as $k) { if (isset($_SERVER[$k])) { $server[$k] = $_SERVER[$k]; } }
		return $server;
	}
	// }}}
}
// }}}
