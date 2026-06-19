<?php
/**
 * luna DB class
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
class lunaDB {
	/**
	 * queries
	 * @var		array
	 * @access	private
	 */
	private static $queries = array();
	// {{{ prepare()
	/**
	 * @access public
	 * @return object
	 */
	public static function prepare() {
		// require PEAR MDB2 Class
		if (!require_once 'MDB2.php') { throw new lunaException(_('Error: cannot find lib: ').'PEAR MDB2', PEAR_LOG_CRIT); }
		if (!file_exists(INI_PATH.'db.ini')) { throw new lunaException(_('Error: cannot find the database configuration file.'), PEAR_LOG_CRIT); return false; }
		$dbstuff = parse_ini_file(INI_PATH.'db.ini');
		$dsn = lunaTools::sanitize($dbstuff['driver']).'://'.lunaTools::sanitize($dbstuff['username']).':'.lunaTools::sanitize($dbstuff['password']).'@'.lunaTools::sanitize($dbstuff['host']).'/'.lunaTools::sanitize($dbstuff['database']); 
		define('DSN', $dsn);
		return true;
	}
	// }}}
	// {{{ connect()
	/**
	 * Initialize a PEAR DB connection
	 * @access public
	 * @return object
	 */
	public static function connect() {
		if (!defined('DSN')) { return false; }
		$options = array('debug' => 0, 'portability' => MDB2_PORTABILITY_ALL); 
		$db =& MDB2::singleton(DSN, $options); 
		if (PEAR::isError($db)) { throw new lunaException($db->getUserInfo(), PEAR_LOG_CRIT); return false; }
		$db->setFetchMode(MDB2_FETCHMODE_OBJECT);
		return true;
	}
	// }}}
	// {{{ get_queries()
	/**
	 * @access public
	 * @return array
	 */
	public static function get_queries() {
		if (!defined('IS_ADMIN') || !IS_ADMIN) { return false; }
		return (self::$queries);
	}
	// }}}
	// {{{ query()
	/**
	 * @param string sql
	 * @access public
	 * @return object
	 */
	public static function query($sql = false) { 
		if (!defined('DSN')) { return false; }
		if (!empty($sql) && is_string($sql)) { 
			self::save_query($sql);
			$db = lunaDB::get();
			$res =& $db->query($sql);
			if (PEAR::isError($res)) { throw new lunaException($res->getUserInfo(), PEAR_LOG_ERR); return false; }
			return $res;
		}
		return false;
	}
	// }}}
	// {{{ save_query()
	/**
	 * @access	private
	 * @param	string $sql
	 * @return	bolean
	 */
	private static function save_query($sql = false) {
		if (empty($sql)) { return false; }
		self::$queries[] = array(
			'microtime' => luna::set_microtime(),
			'query' => $sql
		);
		return true;
	}
	// }}}
	// {{{ get()
	/**
	 * @access public
	 * @return mixed
	 */
	public function get() { 
		if (!defined('DSN')) { return false; }
		$db =& MDB2::singleton(DSN);
		if (PEAR::isError($db)) { throw new lunaException($db->getUserInfo(), PEAR_LOG_CRIT); return false; }
		return $db;
	}
	// }}}
	// {{{ optimise()
	/**
	 * @access public
	 * @param array $tables
	 * @return boolean
	 */
	public function optimise($tables = false) {
		if (!defined('DSN')) { return false; }
		try {
			if (!is_array($tables) || empty($tables)) { $tables = luna::$ini['DBtables']; }
			$sql = '';
			foreach ($tables as $t) { $sql .= $t.','; }
			$sql = substr($sql, 0 ,-1);
			$res = lunaDB::query('OPTIMIZE TABLE '.$sql);
			if (PEAR::isError($res)) { throw new lunaException($res->getUserInfo(), PEAR_LOG_CRIT); }
		} catch (lunaException $e) {
			lunaLog::log($e);
			die();
		}
		return true;
	}
	// }}}
	// {{{ quote()
	/**
	 * @access public
	 * @param string $str
	 * @return boolean
	 */
	public function quote($str = '') {
		if (!defined('DSN')) { return false; }
		$db = lunaDB::get();
		return $db->quote($str);
	}
	// }}}
}
// }}}