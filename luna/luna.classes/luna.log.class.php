<?php
/**
 * lunar Exception class
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

require_once 'Log.php';
// {{{
class lunaException extends Exception {}
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
	public function log($e, $code = PEAR_LOG_ERR) { 
		//lunaTools::debug($e);
		if (defined('DEBUG') && DEBUG && defined('IS_ADMIN') && IS_ADMIN) { lunaTools::debug($e); }
		$db = lunaDB::get();
		if (luna::get_ini('DBtables', 'LOGS') && is_object($db)) {
			$logger = &Log::singleton('mdb2', luna::get_ini('DBtables', 'LOGS'), 'ident', array('db' => $db)); 
			if (is_object($e) && get_class($e) == 'lunaException') {
				$e->session = luna::$session;
				$e->server = self::server_whitelist();
				$logger->log(serialize($e), $e->getCode());
			} else if (is_string($e)) {
				$message = $e;
				$e = new stdClass();
				$e->message = $message;
				$e->session = luna::$session;
				$e->server = self::server_whitelist();
				$logger->log(serialize($e), $code);
			}
			return true;
		}
		return false;
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
?>