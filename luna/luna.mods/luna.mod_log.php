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
		// Logout is a state change: require a valid CSRF token (blocks forged GET /logout).
		if (!hash_equals((string) luna::$session->user->csrf_token, (string) lunaTools::request('csrf_token'))) { return false; }
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
			// Throttle by client IP, independent of whether the account exists, so the
			// back-off can't be used to tell a real account from an unknown one (the old
			// per-account sleep only fired for existing users -> account enumeration).
			// Capped at 5s; a successful login clears the IP's counter below.
			$throttle_ip = lunaTools::encode_ip();
			$throttle_tbl = luna::get_ini('DBtables', 'THROTTLE');
			$throttle_window = intval(time() - 900);
			$tr = lunaDB::query('SELECT attempts FROM '.$throttle_tbl.' WHERE ip = '.lunaDB::quote($throttle_ip).' AND last_time > '.$throttle_window.'');
			$trow = $tr->fetchRow(); $tr->free();
			if (!empty($trow) && !empty($trow->attempts)) { sleep(min(intval($trow->attempts), 5)); }
			// Generic client message for every failure (no account enumeration); the specific
			// reason is logged server-side only. A dummy verify on the non-password paths
			// flattens timing so a missing/inactive account isn't distinguishable.
			$generic = _("Invalid email or password.");
			if (empty($user)) {
				lunaTools::verify_password($_POST['password'], lunaTools::DUMMY_PASSWORD_HASH);
				$inerror++;
				luna::$messages['warning'][] = $generic;
				lunaLog::log('Login failed: unknown user '.$_POST['email'], PEAR_LOG_NOTICE);
			} else if (!$user->is_active) {
				lunaTools::verify_password($_POST['password'], lunaTools::DUMMY_PASSWORD_HASH);
				$inerror++;
				luna::$messages['warning'][] = $generic;
				lunaLog::log('Login failed: deactivated user '.$user->email, PEAR_LOG_WARNING);
			} else if (!lunaTools::verify_password($_POST['password'], $user->password)) {
				$inerror++;
				luna::$messages['warning'][] = $generic;
				lunaLog::log('Login failed: wrong password for '.$user->email, PEAR_LOG_WARNING);
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
		if ($inerror) {
			// Count this failure against the client IP (a stale window resets it to 1).
			lunaDB::query('
				INSERT INTO '.$throttle_tbl.' (ip, attempts, last_time)
				VALUES ('.lunaDB::quote($throttle_ip).', 1, '.intval(time()).')
				ON DUPLICATE KEY UPDATE
					attempts = IF(last_time > '.$throttle_window.', attempts + 1, 1),
					last_time = '.intval(time()).'
			');
			return false;
		}
		if (!$inerror) {
			// A successful login clears this IP's throttle counter.
			lunaDB::query('DELETE FROM '.$throttle_tbl.' WHERE ip = '.lunaDB::quote($throttle_ip).'');
			// Rotate the session id at the privilege boundary to defeat fixation.
			// regenerate_id(false) keeps the old row (the DB handler only UPDATEs, never
			// INSERTs), so re-key that row to the new id by hand (session_id is the PK).
			$old_sid = session_id();
			session_regenerate_id(false);
			$new_sid = session_id();
			lunaDB::query('DELETE FROM '.luna::get_ini('DBtables', 'SESSIONS').' WHERE session_id = '.lunaDB::quote($new_sid).' AND session_id <> '.lunaDB::quote($old_sid).'');
			lunaDB::query('UPDATE '.luna::get_ini('DBtables', 'SESSIONS').' SET session_id = '.lunaDB::quote($new_sid).' WHERE session_id = '.lunaDB::quote($old_sid).'');
			luna::$session->user->session_id = $new_sid;
			// rotate the CSRF token at the privilege boundary too
			$new_csrf = bin2hex(random_bytes(32));
			lunaDB::query('UPDATE '.luna::get_ini('DBtables', 'SESSIONS').' SET csrf_token = '.lunaDB::quote($new_csrf).' WHERE session_id = '.lunaDB::quote($new_sid).'');
			luna::$session->user->csrf_token = $new_csrf;
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
			// Upgrade a legacy MD5 / outdated hash to the current algorithm on login.
			if (lunaTools::password_is_legacy($user->password) || password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
				lunaDB::query('UPDATE '.luna::get_ini('DBtables', 'USERS').' SET password = '.lunaDB::quote(lunaTools::hash_password($_POST['password'])).' WHERE nid = '.lunaDB::quote($user->nid).'');
			}
			luna::$session->user = luna::$session->get_user_data(luna::$session->user->session_id);
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
		   
		luna::$session->user = luna::$session->get_user_data(luna::$session->user->session_id);
		return true;
	}
	// }}}
}
// }}}
?>