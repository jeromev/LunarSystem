<?php
/**
 * luna DB class
 *
 * PHP versions 5.6 – 8.x
 *
 * LICENSE: This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 * For more details, see <http://www.gnu.org/copyleft/gpl.html>
 *
 * @author		Odradek
 * @license		http://www.gnu.org/copyleft/gpl.html  GPL
 * @link		https://github.com/jeromev/LunarSystem
 * @package		lunarSystem
 *
 * PDO/pdo_mysql rewrite (0.5.0-alpha): the original used the unmaintained PEAR
 * MDB2 + ext/mysql (removed in PHP 7). This keeps the exact public surface the
 * app depends on — lunaDB::query/quote/get/nextID/optimise + a result object
 * answering ->fetchRow() (a stdClass row, via PDO::FETCH_OBJ) and ->free().
 * quote() reproduces MDB2's auto-typed quoting (verified against the live MDB2
 * stack): null/'' -> NULL, bool -> 0/1, int -> bare, float/string -> quoted.
 */
// {{{
class lunaDB {
	/**
	 * queries — the per-request query log (for the admin debug panel)
	 * @var		array
	 * @access	private
	 */
	private static $queries = array();
	/**
	 * pdo — the singleton PDO connection
	 * @var		PDO
	 * @access	private
	 */
	private static $pdo = null;
	private static $user = '';
	private static $pass = '';
	// {{{ prepare()
	/**
	 * Read db.ini and build the PDO DSN. Defines DSN so the boot-order guards
	 * (`if (!defined('DSN'))`) elsewhere keep their contract.
	 * @access public
	 * @return boolean
	 */
	public static function prepare() {
		if (!file_exists(INI_PATH.'db.ini')) { throw new lunaException(_('Error: cannot find the database configuration file.'), PEAR_LOG_CRIT); }
		$c = parse_ini_file(INI_PATH.'db.ini');
		$host = isset($c['host']) ? $c['host'] : 'localhost';
		$name = isset($c['database']) ? $c['database'] : '';
		self::$user = isset($c['username']) ? $c['username'] : '';
		self::$pass = isset($c['password']) ? $c['password'] : '';
		if (!defined('DSN')) { define('DSN', 'mysql:host='.$host.';dbname='.$name.';charset=utf8mb4'); }
		return true;
	}
	// }}}
	// {{{ connect()
	/**
	 * Open the PDO connection (idempotent — get() builds it on demand).
	 * @access public
	 * @return boolean
	 */
	public static function connect() {
		if (!defined('DSN')) { return false; }
		self::get();
		return true;
	}
	// }}}
	// {{{ get()
	/**
	 * The singleton PDO handle. Throws lunaException on connect failure, matching
	 * the original throw-on-PEAR::isError contract.
	 * @access public
	 * @return mixed PDO|false
	 */
	public static function get() {
		if (!defined('DSN')) { return false; }
		if (self::$pdo instanceof PDO) { return self::$pdo; }
		try {
			self::$pdo = new PDO(DSN, self::$user, self::$pass, array(
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
				PDO::ATTR_EMULATE_PREPARES   => true,
				PDO::ATTR_STRINGIFY_FETCHES  => true,
			));
		} catch (PDOException $e) {
			throw new lunaException($e->getMessage(), PEAR_LOG_CRIT);
		}
		return self::$pdo;
	}
	// }}}
	// {{{ get_queries()
	/**
	 * @access public
	 * @return mixed
	 */
	public static function get_queries() {
		if (!defined('IS_ADMIN') || !IS_ADMIN) { return false; }
		return (self::$queries);
	}
	// }}}
	// {{{ query()
	/**
	 * Run a SQL statement. Returns a lunaResult (->fetchRow()/->free()) on success,
	 * false on no-DSN/empty-sql, and throws lunaException on a DB error.
	 * @param string $sql
	 * @access public
	 * @return mixed lunaResult|false
	 */
	public static function query($sql = false) {
		if (!defined('DSN')) { return false; }
		if (!empty($sql) && is_string($sql)) {
			self::save_query($sql);
			try {
				$stmt = self::get()->query($sql);
			} catch (PDOException $e) {
				throw new lunaException($e->getMessage(), PEAR_LOG_ERR);
			}
			return new lunaResult($stmt);
		}
		return false;
	}
	// }}}
	// {{{ save_query()
	/**
	 * @access	private
	 * @param	string $sql
	 * @return	boolean
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
	// {{{ optimise()
	/**
	 * @access public
	 * @param array $tables
	 * @return boolean
	 */
	public static function optimise($tables = false) {
		if (!defined('DSN')) { return false; }
		try {
			if (!is_array($tables) || empty($tables)) { $tables = luna::$ini['DBtables']; }
			$sql = '';
			foreach ($tables as $t) { $sql .= $t.','; }
			$sql = substr($sql, 0 ,-1);
			lunaDB::query('OPTIMIZE TABLE '.$sql);
		} catch (lunaException $e) {
			lunaLog::log($e);
			die();
		}
		return true;
	}
	// }}}
	// {{{ nextID()
	/**
	 * Allocate the next id from a sequence table, reproducing MDB2's on-demand
	 * sequence emulation exactly: INSERT a row into `<name>_seq`, read its
	 * AUTO_INCREMENT via lastInsertId(), and prune older rows. Returns an int.
	 * @access public
	 * @param string $seq the base table name (e.g. luna_nodes -> luna_nodes_seq)
	 * @return mixed int|false
	 */
	public static function nextID($seq = false) {
		if (!defined('DSN') || empty($seq) || !is_string($seq)) { return false; }
		$pdo = self::get();
		$table = $seq.'_seq';
		try {
			$pdo->exec('INSERT INTO `'.$table.'` (`sequence`) VALUES (NULL)');
			$id = (int) $pdo->lastInsertId();
			if ($id > 0) { $pdo->exec('DELETE FROM `'.$table.'` WHERE `sequence` < '.$id); }
		} catch (PDOException $e) {
			throw new lunaException($e->getMessage(), PEAR_LOG_ERR);
		}
		return $id;
	}
	// }}}
	// {{{ quote()
	/**
	 * Quote a value for SQL, reproducing MDB2's auto-typed quoting (verified
	 * against the live MDB2 stack): null/'' -> NULL (unquoted), bool -> 0/1,
	 * int -> bare integer, float/string -> driver-escaped quoted string. A raw
	 * PDO::quote() would mis-handle ints/bools/null, so the type dispatch matters.
	 * @access public
	 * @param mixed $str
	 * @return mixed string|false
	 */
	public static function quote($str = '') {
		if (!defined('DSN')) { return false; }
		if ($str === null || $str === '') { return 'NULL'; }
		if (is_bool($str)) { return $str ? '1' : '0'; }
		if (is_int($str)) { return (string) $str; }
		return self::get()->quote((string) $str);
	}
	// }}}
}
// }}}
// {{{
/**
 * Thin result wrapper so call sites keep using $res->fetchRow() (returns a
 * stdClass row, or false at end of set) and $res->free().
 */
class lunaResult {
	private $stmt;
	public function __construct($stmt) { $this->stmt = $stmt; }
	// {{{ fetchRow()
	/**
	 * @access public
	 * @return mixed stdClass|false
	 */
	public function fetchRow() {
		if (!($this->stmt instanceof PDOStatement)) { return false; }
		return $this->stmt->fetch(PDO::FETCH_OBJ);
	}
	// }}}
	// {{{ free()
	/**
	 * @access public
	 * @return boolean
	 */
	public function free() {
		if ($this->stmt instanceof PDOStatement) { $this->stmt->closeCursor(); }
		return true;
	}
	// }}}
}
// }}}
