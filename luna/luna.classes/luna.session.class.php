<?php
/**
 * lunar Session class
 *
 * PHP versions 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 * For more details, see <http://www.gnu.org/copyleft/gpl.html>
 *
 * @author		Odradek <odradek@lunarsystem.org>
 * @license		http://www.gnu.org/copyleft/gpl.html	GPL
 * @link		http://lunarsystem.org
 * @package		lunarSystem
 */
// {{{
class lunaSession {
	/**
	 * instance
	 * @var object
	 * @access	private
	 */
	private static $instance;
	/**
	 * time_out
	 * @access	public
	 * @var		array
	 */
	public static $time_out = 604800; // 604800 = 7 days
	/**
	 * time_out
	 * @access	public
	 * @var		array
	 */
	public static $min_time_out = 300; // 300 = 5 minutes
	/**
	 * User object
	 * @access	public
	 * @var		array
	 */
	public $user = null;
	/**
	 * save_path
	 * @access	public
	 * @var		string
	 */
	public static $save_path = '';
	/**
	 * sess_name
	 * @access	public
	 * @var		array
	 */
	public static $sess_name = '';
	/**
	 * new
	 * @access	public
	 * @var boolean
	 */
	public static $new = true;
	// {{{ constructor
	/**
	 * Constructor.
	 * @access	public
	 * @return boolean
	 */
	private function __construct() {
		if (isset($_GET[session_name()]) || isset($_POST[session_name()])) {
			define('SID_IN', false);
		} else {
			define('SID_IN', true);
		}
		session_set_save_handler(
			array(&$this, 'sessionOpen'),
			array(&$this, 'sessionClose'),
			array(&$this, 'sessionRead'),
			array(&$this, 'sessionWrite'),
			array(&$this, 'sessionDestroy'),
			array(&$this, 'sessionGc')
		);
		register_shutdown_function('session_write_close');
		self::$time_out = intval(luna::get_ini('config', 'session_length'));
		session_set_cookie_params(array(
			'lifetime' => self::$time_out,
			'path'     => luna::$site_relative_url ?: '/',
			'httponly' => true,
			'samesite' => 'Lax',
			'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
		));
		ini_set('session.gc_maxlifetime', SID_IN? self::$time_out : self::$min_time_out);
		define('SESSION_READY_TO_START', true);
		return true;
	}
	// }}}
	// {{{ singleton()
	/**
	 * @access public
	 * @return object
	 */
	public static function singleton() {
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		return self::$instance;
	}
	// }}}
	// {{{ __clone()
	/**
	 * @access public
	 * @return void
	 */
	public function __clone() { trigger_error('Clone is not allowed.', E_USER_ERROR); }
	// }}}
	// {{{ start()
	/**
	 * Start session
	 *
	 * @access public
	 * @return boolean
	 */
	public function start() { 
		if (defined('SESSION_READY_TO_START') && SESSION_READY_TO_START == true) { 
			session_start(); 
			return true;
		}
		return false;
	}
	// }}}
	// {{{ sessionOpen()
	/**
	 * create an empty user object & return true
	 *
	 * @access public
	 * @return boolean
	 */
	public function sessionOpen($save_path, $session_name) { 
		$this->user = new stdclass();
		self::$save_path = $save_path;
		self::$sess_name = $session_name;
		self::$new = true; 
		return true;
	}
	// }}}
	// {{{ sessionClose()
	/**
	 * do nothing here, return true
	 *
	 * @access public
	 * @return boolean
	 */
	public function sessionClose() { return true; }
	// }}}
	// {{{ sessionRead()
	/**
	 * session read function
	 *
	 * @access public
	 * @param string $sid
	 * @return mixed
	 */
	public function sessionRead($sid) {
		$this->user = $this->get_user_data($sid);
		if (!isset($this->user->nid)) { 
			$this->sessionDestroy($sid);
			self::$new = 1;
			$res = lunaDB::query('
				INSERT INTO 
					'.luna::get_ini('DBtables', 'SESSIONS').' 
					(
						session_id,
						session_user_nid, 
						session_start,
						session_time, 
						session_ip, 
						session_url,
						session_logged_in, 
						session_lang,
						session_useragent
					)
				VALUES
					(
						'.lunaDB::quote($sid).', 
						(SELECT nid FROM '.luna::get_ini('DBtables', 'NODES').' WHERE lid = '.lunaDB::quote(ANONYMOUS).'),
						'.lunaDB::quote(NOW).',
						'.lunaDB::quote(NOW).',
						'.lunaDB::quote(lunaTools::encode_ip()).',
						'.lunaDB::quote(luna::$path).',
						'.lunaDB::quote(false).',
						'.lunaDB::quote(luna::$lang).',
						'.lunaDB::quote($_SERVER['HTTP_USER_AGENT']).'
					)
			');
			$this->user = $this->get_user_data($sid); 
		}
		if (!isset($this->user->nid)) {
			$this->sessionDestroy($sid);
			throw new lunaException(_('Error: cannot read session.'), PEAR_LOG_CRIT);
		}
		// Every session carries a CSRF synchronizer token; mint one on first read
		// (covers brand-new sessions and rows predating the csrf_token column).
		if (empty($this->user->csrf_token)) {
			$csrf = bin2hex(random_bytes(32));
			lunaDB::query('UPDATE '.luna::get_ini('DBtables', 'SESSIONS').' SET csrf_token = '.lunaDB::quote($csrf).' WHERE session_id = '.lunaDB::quote($sid).'');
			$this->user->csrf_token = $csrf;
		}
		// PHP's session handler requires read() to return a STRING (PHP 8 rejects a
		// non-string with "Failed to read session data"). luna keeps user state in
		// $this->user + the DB, so there is no PHP-serialised session payload.
		return '';
	}
	// }}}
	// {{{ sessionWrite()
	/**
	 * session function
	 *
	 * @access public
	 * @return boolean
	 */
	public function sessionWrite($sid, $data) {
		$res = lunaDB::query('
			UPDATE 
				'.luna::get_ini('DBtables', 'SESSIONS').' 
			SET
				session_user_nid = '.lunaDB::quote($this->user->nid).', 
				session_time  = '.lunaDB::quote(NOW).', 
				session_ip  = '.lunaDB::quote(lunaTools::encode_ip()).',  
				session_url  = '.lunaDB::quote(luna::$path).', 
				session_logged_in  = '.lunaDB::quote(($this->user->email == ANONYMOUS ? false : true)).', 
				session_lang  = '.lunaDB::quote(luna::$lang).', 
				session_useragent  = '.lunaDB::quote($_SERVER['HTTP_USER_AGENT']).' 
			WHERE
				session_id = '.lunaDB::quote($sid).'
		');
		if ($this->user->email != ANONYMOUS) {
			$res = lunaDB::query('
				UPDATE 
					'.luna::get_ini('DBtables', 'USERS').' 
				SET
					last_time  = '.lunaDB::quote(NOW).', 
					last_url  = '.lunaDB::quote(luna::$path).'
				WHERE
					nid = '.lunaDB::quote($this->user->nid).'
			');
		}
		return true;
	}
	// }}}
	// {{{ sessionDestroy()
	/**
	 * session function
	 *
	 * @access public
	 * @return boolean
	 */
	public function sessionDestroy($sid) {
		$res = lunaDB::query('
			DELETE FROM 
				'.luna::get_ini('DBtables', 'SESSIONS').' 
			WHERE
				session_id = '.lunaDB::quote($sid).'
		');
		$_SESSION = array();
		if (isset($_COOKIE[session_name()])) { lunaTools::set_cookie(session_name(), '', time() - 42000); }
		if (isset($_POST['PHPSESSID'])) { unset ($_POST['PHPSESSID']); }
		if (isset($_GET['PHPSESSID'])) { unset ($_GET['PHPSESSID']); }
		lunaDB::optimise(array(luna::get_ini('DBtables', 'SESSIONS')));
		return true;
	}
	// }}}
	// {{{ sessionGc()
	/**
	 * session function
	 *
	 * @access public
	 * @return boolean
	 */
	public function sessionGc($sLifeTime) {
		$res = lunaDB::query('
			DELETE FROM 
				'.luna::get_ini('DBtables', 'SESSIONS').' 
			WHERE
				session_time < '.lunaDB::quote(NOW - $sLifeTime).'
		');
		lunaDB::optimise(array(luna::get_ini('DBtables', 'SESSIONS')));
		return true;
	}
	// }}}
	// {{{ get_user_data()
	/**
	 * get_user_data
	 *
	 * @access public
	 * @param string $sid
	 * @return object
	 */
	public function get_user_data($sid = '') { 
		if (!is_string($sid) || empty($sid)) { return false; } 
		$sid = substr($sid, 0, 32);
		$sLifeTime = SID_IN? self::$time_out : self::$min_time_out;
		$user = new stdclass();
		$res = lunaDB::query('
			SELECT DISTINCT
				u.nid as user_nid,
				u.is_active,
				users.firstname,
				users.lastname,
				u.lid as email,
				users.regis_time,
				users.last_time,
				users.last_url,
				users.lang,
				s.session_id,
				s.session_start,
				s.session_time,
				s.session_url,
				s.session_lang,
				s.session_logged_in,
				s.session_ip,
				s.session_useragent,
				s.csrf_token,
				g.nid as group_nid,
				l.nid as level_nid
			FROM
				'.luna::get_ini('DBtables', 'USERS').' users,
				'.luna::get_ini('DBtables', 'SESSIONS').' s,
				'.luna::get_ini('DBtables', 'NODES').' u,
				'.luna::get_ini('DBtables', 'NODES').' l,
				'.luna::get_ini('DBtables', 'NODES').' g,
				'.luna::get_ini('DBtables', 'NODES_MAP').' gl,
				'.luna::get_ini('DBtables', 'NODES_MAP').' ug,
				'.luna::get_ini('DBtables', 'CLASSES').' tu,
				'.luna::get_ini('DBtables', 'CLASSES').' tg,
				'.luna::get_ini('DBtables', 'CLASSES').' tl
			WHERE
				u.nid = users.nid
				AND s.session_user_nid = u.nid
				AND s.session_id = '.lunaDB::quote($sid).'
				AND s.session_time > '.lunaDB::quote(NOW - $sLifeTime).'
				AND s.session_useragent = '.lunaDB::quote($_SERVER['HTTP_USER_AGENT']).'
				AND s.session_ip = '.lunaDB::quote(lunaTools::encode_ip()).'
				AND u.tid = tu.id AND tu.lid = '.lunaDB::quote('user').'
				AND g.tid = tg.id AND tg.lid = '.lunaDB::quote('group').'
				AND l.tid = tl.id AND tl.lid = '.lunaDB::quote('level').'
				AND ug.nid1 = u.nid AND ug.nid2 = g.nid
				AND gl.nid1 = g.nid AND gl.nid2 = l.nid
				AND g.is_active = 1
				AND l.is_active = 1
		');
		while ($row = $res->fetchRow()) { 
			$user->nid = $row->user_nid;
			$user->is_active = $row->is_active;
			$user->firstname = $row->firstname;
			$user->lastname = $row->lastname;
			$user->email = $row->email;
			$user->regis_time = $row->regis_time;
			$user->last_time = $row->last_time;
			$user->last_url = $row->last_url;
			$user->session_id = $row->session_id;
			$user->session_start = $row->session_start;
			$user->session_time = $row->session_time;
			$user->session_url = $row->session_url;
			$user->session_lang = $row->session_lang;
			$user->session_logged_in = $row->session_logged_in;
			$user->session_ip = lunaTools::decode_ip($row->session_ip);
			$user->lang = $row->lang;
			$user->session_useragent = $row->session_useragent;
			$user->csrf_token = $row->csrf_token;
			$user->groups[$row->group_nid] = $row->group_nid;
			$user->levels[$row->level_nid] = $row->level_nid;
			self::$new = 0;
		}
		$res->free(); 
		if (isset($user->last_time) && NOW - $user->last_time <= 1) { sleep(1); } 
		return $user;
	}
	// }}}
}
// }}}
?>