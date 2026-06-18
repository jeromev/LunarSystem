<?php
/**
 * lunar mod_log module
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
class mod_log {
	/**
	 * instance
	 * @var object
	 * @access	private
	 */
	private static $instance;
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
	public function __clone() { trigger_error('Lunar clones are not allowed.', E_USER_ERROR); }
	// }}}
	// {{{ constructor
	/**
	 * Constructor.
	 * @access	private
	 * @return boolean
	 */
	private function __construct() { 
		lunaTools::add_vocabulary(array(
			'Connection form',
			'Email',
			'Password',
			'last_url',
			'You are now disconnected.'
		));
		return true;
	}
	// }}}
	// {{{ submit()
	/**
	 * submit()
	 * @access public
	 * @return boolean
	 */
	public function submit() {
		switch(luna::$data['lid']) {
			case 'logout': $this->logout(); break;
			case 'login': default: $this->login();
		}
		return true;
	}
	// }}}
	// {{{ load()
	/**
	 * load()
	 * @access public
	 * @return boolean
	 */
	public function load() { 
		switch(luna::$data['lid']) { 
			case 'logout': $this->logout(); 
				break; 
		} 
		return true;
	}
	// }}}
	// {{{ logout()
	/**
	 * logout()
	 *
	 * @access public
	 * @return boolean
	 */
	public function logout() {
		luna::$model->purge_index();
		$_SESSION = array();
		lunaTools::set_cookie(session_name(), '', NOW - 42000);
		session_destroy();
		return true;
	}
	// }}}
	// {{{ login()
	/**
	 * login()
	 *
	 * @access public
	 * @return boolean
	 */
	public function login() {
		$inerror = 0;
		if (!lunaTools::check_emptyness('email')) { $inerror++; }
		if (!lunaTools::check_emptyness('password')) { $inerror++; }
		if ($inerror) { return false; }

		if (!$inerror) {
			$user = array();
			$res = lunaDB::query('
				SELECT
					nu.nid,
					nu.is_active,
					nu.lid as email,
					u.password,
					u.firstname,
					u.lastname,
					u.login_attempts
				FROM
					'.luna::get_ini('DBtables', 'NODES').' nu,
					'.luna::get_ini('DBtables', 'USERS').' u
				WHERE
					nu.lid = '.lunaDB::quote($_POST['email']).'
					AND u.nid = nu.nid
			');
			$user = $res->fetchRow();
			$res->free();
			// Throttle: back off on accounts with prior failed attempts. Capped, and a
			// correct password resets login_attempts below, so this never locks an account.
			if (!empty($user) && !empty($user->login_attempts)) { sleep(min(intval($user->login_attempts), 5)); }
			if (empty($user)) { 
				$inerror++; 
				$message = sprintf(_("Unknown user %1\$s."), $_POST['email']);
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_NOTICE); 
			} else if (!$user->is_active) { 
				$inerror++; 
				$message = sprintf(_("User “%1\$s” is deactivated."), $user->firstname.' '.$user->lastname); 
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_WARNING);
			} else if (md5($_POST['password']) != $user->password) { 
				$inerror++; 
				$message = sprintf(_("Wrong password for user “%1\$s”."), $user->email); 
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_WARNING);

				$res = lunaDB::query('
					UPDATE 
						'.luna::get_ini('DBtables', 'USERS').' 
					SET
						login_attempts = login_attempts + 1
					WHERE
						nid = '.lunaDB::quote($user->nid).'
				');
			}
		}
		if ($inerror) { return false; }
		if (!$inerror) {
			$res = lunaDB::query('
				UPDATE 
					'.luna::get_ini('DBtables', 'SESSIONS').' 
				SET
					session_user_nid = '.lunaDB::quote($user->nid).', 
					session_logged_in = '.lunaDB::quote(true).'  
				WHERE
					session_id = '.lunaDB::quote(luna::$session->user->session_id).'
			');
			$res = lunaDB::query('
				DELETE FROM 
					'.luna::get_ini('DBtables', 'SESSIONS').' 
				WHERE 
					session_user_nid = '.lunaDB::quote($user->nid).'
					AND session_id <> '.lunaDB::quote(luna::$session->user->session_id).'
			');
			$res = lunaDB::query('
				UPDATE 
					'.luna::get_ini('DBtables', 'USERS').' 
				SET
					login_attempts =  0
				WHERE
					nid = '.lunaDB::quote($user->nid).'
			');
			luna::$session->user = lunaSession::get_user_data(luna::$session->user->session_id);
			luna::$model->purge_index();
			$message = sprintf(_("You are now connected as %1\$s."), luna::$session->user->firstname.' '.luna::$session->user->lastname);
			luna::$messages['okay'][] = $message;
			lunaLog::log($message, PEAR_LOG_INFO);
		}
		return true;
	}
	// }}}
	// {{{ go_guest()
	/**
	 * go_guest()
	 *
	 * @access public
	 * @return boolean
	 */
	public function go_guest() {
		$res = lunaDB::query('
			UPDATE 
				'.luna::get_ini('DBtables', 'SESSIONS').' 
			SET
				session_user_nid = '.lunaDB::quote(ANONYMOUS).', 
				session_logged_in = '.lunaDB::quote(false).'  
			WHERE
				session_id = '.lunaDB::quote(luna::$session->user->session_id).'
		');
		   
		luna::$session->user = lunaSession::get_user_data(luna::$session->user->session_id);
		//lunaTools::debug(luna::$session->user);
		return true;
	}
	// }}}
}
// }}}
?>