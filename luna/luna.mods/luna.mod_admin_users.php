<?php
/**
 * lunar mod_admin_users module
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
class mod_admin_users {
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
	 * @access	private
	 * @return boolean
	 */
	private function __construct() {
		lunaTools::add_vocabulary(array(
			'firstname',
			'lastname',
			'email',
			'password',
			'active',
			'id',
			'last_time',
			'regis_time',
			'session_url',
			'session_ip',
			'session_length',
			'session_lang',
			'Yes',
			'No',
			'Add a user',
			'Modify the user',
			'Deactivate',
			'Add',
			'Modify',
			'Delete',
			'List of the users',
			'Are you sure you want to delete this user?',
			'groups',
			'Literal identifier',
			'Literal id',
			'Deactivate',
			'Users'
		));
		return true;
	}
	// }}}
	// {{{ submit_add()
	/**
	 * @access public
	 * @return void
	 */
	public function submit_add() {
		$inerror = 0;
		// check emptyness
		if (!lunaTools::check_emptyness('add_user_firstname', 'firstname')) { $inerror++; }
		if (!lunaTools::check_emptyness('add_user_email', 'email')) { $inerror++; }
		if (!lunaTools::check_emptyness('add_user_password', 'password')) { $inerror++; }
		if (!lunaTools::check_emptyness('add_user_groups', 'groups')) { $inerror++; }
		if ($inerror) { return false; }
		$_POST['add_user_password'] = md5($_POST['add_user_password']);
		$_POST['add_user_is_inactive'] = isset($_POST['add_user_is_inactive'])? ($_POST['add_user_is_inactive'] == 1? 1 : 0) : 0;
		// load stuff
		if (!luna::$model->merge_index(luna::$model->load_nodes('group', 'level'))) { throw new lunaException(_('Error: cannot load data'), PEAR_LOG_CRIT); }
		// check email
		if (!lunaTools::check_email($_POST['add_user_email'])) { 
			$inerror++; 
			$message = sprintf(_("The email address “%1\$s” is invalid."), $_POST['add_user_email']);
			luna::$messages['warning'][] = $message;
			lunaLog::log($message, PEAR_LOG_NOTICE); 
		} 
		if ($inerror) { return false; }
		// check if identifier is already used
		if (!$is_not_taken = luna::$model->check_if_lid_is_taken($_POST['add_user_email'])) { return false; }
		if (isset($_POST['add_user_groups']) && !empty($_POST['add_user_groups'])) { 
			foreach ($_POST['add_user_groups'] as $postgroup_nid) {
				if (!$postgroup_node = luna::$model->get_node($postgroup_nid, 'group')) {
					$inerror++; 
					$message = _('Unknown group '.intval($postgroup_nid)); 
					luna::$messages['warning'][] = $message; 
					lunaLog::log($message, PEAR_LOG_WARNING);
				}
			}
		} 
		if ($inerror) { return false; }
		if ($node = luna::$model->insert('user', $_POST['add_user_email'], ($_POST['add_user_is_inactive']? 0 : 1))) { 
			luna::$model->link($node, $_POST['add_user_groups']);
			$res = lunaDB::query('
				INSERT INTO
					'.luna::get_ini('DBtables', 'USERS').'
					(nid, firstname, lastname, password, regis_time, last_time)
				VALUES
					(
						'.lunaDB::quote($node).',
						'.lunaDB::quote($_POST['add_user_firstname']).',
						'.lunaDB::quote($_POST['add_user_lastname']).',
						'.lunaDB::quote($_POST['add_user_password']).',
						'.lunaDB::quote(NOW).',
						'.lunaDB::quote(NOW).'
					)
			');
			lunaTools::purge_cache(false);
			luna::$model->purge_index();
			$message = sprintf(_("User “%1\$s” has been created."), ($_POST['add_user_firstname'].' '.$_POST['add_user_lastname'])); 
			luna::$messages['okay'][] = $message;
			lunaLog::log($message, PEAR_LOG_INFO);
			lunaTools::unrequest(array('add_user_email', 'add_user_groups', 'add_user_firstname', 'add_user_lastname', 'add_item_nid', 'modify_item_nid', 'user_nid'));
		} else {
			$message = sprintf(_("The modification of the item “%1\$s” has failed."), $_POST['add_mod_lid']);
			luna::$messages['warning'][] = $message;
			lunaLog::log($message, PEAR_LOG_WARNING);
		}
		return true;
	}
	// }}}
	// {{{ submit_modify()
	/**
	 * @access public
	 * @return void
	 */
	public function submit_modify() {
		$inerror = 0;
		// check emptyness
		if (!lunaTools::check_emptyness('modify_user_firstname', 'firstname')) { $inerror++; }
		if (!lunaTools::check_emptyness('modify_user_email', 'email')) { $inerror++; }
		if (!lunaTools::check_emptyness('modify_user_groups', 'groups')) { $inerror++; }
		if (isset($_POST['user_nid']) && !empty($_POST['user_nid'])) {
			if ($inerror) { return false; }
			$_POST['modify_user_is_inactive'] = isset($_POST['modify_user_is_inactive'])? ($_POST['modify_user_is_inactive'] == 1? 1 : 0) : 0;
			$_POST['user_nid'] = intval($_POST['user_nid']);
			if (!luna::$model->merge_index(luna::$model->load_nodes('group', 'level'))) { throw new lunaException(_('Error: cannot load data'), PEAR_LOG_CRIT); }
			// check if the user exists
			luna::$model->merge_index(luna::$model->load_users($_POST['user_nid']));
			$user_nid = luna::$model->check_requested_node('user_nid', 'Person', 'foaf');
			if (empty($user_nid)) { 
				$inerror++; 
				$message = sprintf(_("User #%1\$s does not exist."), $_POST['user_nid']);
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_WARNING);
			}
			if ($inerror) { return false; }
			$user_node = luna::$model->get_node($user_nid, 'Person', 'foaf');
			$user_lid = luna::$model->get_lid($user_node); 
			// Are we trying to modify an innocent guest?
			if ($_POST['modify_user_email'] == ANONYMOUS) { 
				$inerror++; 
				$message = _('You cannot modify the guest user.');
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_NOTICE);
			}
			if ($inerror) { return false; }
			// check email
			if (!lunaTools::check_email($_POST['modify_user_email'])) { 
				$inerror++; 
				$message = sprintf(_("The email address “%1\$s” is invalid."), $_POST['modify_user_email']);
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_NOTICE);
			}
			if ($inerror) { return false; }
			// check if the user is trying to deactivate himself (do not let him do it!)
			if ($_POST['user_nid'] == luna::$session->user->nid && $_POST['modify_user_is_inactive']) { 
				$inerror++; 
				$message = _('You cannot deactivate yourself.');
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_NOTICE);
			}
			if ($inerror) { return false; }
			// check if this email already exists in the database
			$res = lunaDB::query('
				SELECT 
					u.nid,
					u.firstname,
					u.lastname,
					n.lid
				FROM
					'.luna::get_ini('DBtables', 'USERS').' u,
					'.luna::get_ini('DBtables', 'NODES').' n
				WHERE 
					n.lid = '.lunaDB::quote($_POST['modify_user_email']).'
					AND u.nid = n.nid
					AND n.nid <> '.lunaDB::quote($_POST['user_nid']).'
				LIMIT 1
			');
			$row = $res->fetchRow();
			if (isset($row->nid) && $row->nid > 0) { 
				$inerror++; 
				$message = sprintf(_("A user with the same email (%1\$s) already exists."), $row->firstname.' '.$row->lastname.' #'.$row->nid);
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_NOTICE);
			}
			if ($inerror) { return false; }
			if (!$group_default_nid = luna::$model->get_nid_from_lid('group_default')) { throw new lunaException(_('Error: cannot load “group_default”'), PEAR_LOG_CRIT); }
			$_POST['modify_user_groups'][$group_default_nid] = $group_default_nid;
			if ($node = luna::$model->update($_POST['user_nid'], $_POST['modify_user_email'], ($_POST['modify_user_is_inactive']? 0 : 1))) { 
				luna::$model->unlink($node, 'group');
				luna::$model->link($node, $_POST['modify_user_groups']);
				$res = lunaDB::query('
					UPDATE
						'.luna::get_ini('DBtables', 'USERS').'
					SET
						firstname = '.lunaDB::quote($_POST['modify_user_firstname']).', 
						lastname = '.lunaDB::quote($_POST['modify_user_lastname']).'
						'.((isset($_POST['modify_user_password']) && !empty($_POST['modify_user_password']))? ', password = '.lunaDB::quote(md5($_POST['modify_user_password'])) : ' ').'
					WHERE
						nid = '.lunaDB::quote($node).'
				');
				lunaTools::purge_cache(false);
				luna::$model->purge_index();
				$this->requested_nid = NULL;
				$message = sprintf(_("User “%1\$s” has been modified."), ($_POST['modify_user_firstname'].' '.$_POST['modify_user_lastname'])); 
				luna::$messages['okay'][] = $message;
				lunaLog::log($message, PEAR_LOG_INFO);
				lunaTools::unrequest(array('modify_user_email', 'modify_user_groups', 'modify_user_firstname', 'modify_user_lastname', 'modify_item_nid', 'user_nid'));
			} else {
				$message = sprintf(_("The modification of the item “%1\$s” has failed."), _($_POST['modify_user_email']));
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_WARNING);
			}
			return true;
		}
	}
	// }}}
	// {{{ submit_delete()
	/**
	 * submit_delete()
	 *
	 * @access public
	 * @return void
	 */
	public function submit_delete() { 
		if (isset($_POST['user_nid']) && !empty($_POST['user_nid'])) {
			$inerror = 0;
			if (!luna::$model->merge_index(luna::$model->load_nodes('group', 'level'))) { throw new lunaException(_('Error: cannot load data'), PEAR_LOG_CRIT); }
			$_POST['modify_user_is_inactive'] = isset($_POST['modify_user_is_inactive'])? ($_POST['modify_user_is_inactive'] == 1? 1 : 0) : 0;
			$_POST['user_nid'] = intval($_POST['user_nid']);
			// check if the user exists
			luna::$model->merge_index(luna::$model->load_users($_POST['user_nid']));
			$user_nid = luna::$model->check_requested_node('user_nid', 'Person', 'foaf');
			if (empty($user_nid)) { 
				$inerror++; 
				$message = sprintf(_("User #%1\$s does not exist."), $_POST['user_nid']);
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_WARNING);
			}
			if ($inerror) { return false; }
			$user_node = luna::$model->get_node($user_nid, 'Person', 'foaf');
			$user_lid = luna::$model->get_lid($user_node);
			// Are we trying to delete ourselve? (we cannot allow this to happen)
			if ($_POST['user_nid'] == luna::$session->user->nid) {  
				$inerror++; 
				$message = _('You cannot delete yourself.');
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_NOTICE);
			}
			if ($inerror) { return false; }
			// Are we trying to delete an innocent guest? shame on us..
			if ($_POST['modify_user_email'] == ANONYMOUS) { 
				$inerror++; 
				$message = _('You cannot delete the guest user.');
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_NOTICE);
			}
			if ($inerror) { return false; }
			if (luna::$model->delete($_POST['user_nid'])) {
				$res = lunaDB::query('
					DELETE FROM
						'.luna::get_ini('DBtables', 'USERS').'
					WHERE 
						nid = '.lunaDB::quote($_POST['user_nid']).'
				');
				lunaTools::purge_cache(false);
				luna::$model->purge_index();
				$message = sprintf(_("User “%1\$s” has been deleted."), ($_POST['modify_user_firstname'].' '.$_POST['modify_user_lastname'])); 
				luna::$messages['okay'][] = $message;
				lunaLog::log($message, PEAR_LOG_INFO);
				lunaTools::unrequest(array('modify_user_email', 'modify_user_groups', 'modify_user_firstname', 'modify_user_lastname', 'modify_item_nid', 'user_nid'));
			} else {
				$message = sprintf(_("The modification of the item “%1\$s” has failed."), _($oldpagelid));
				luna::$messages['warning'][] = $message;
				lunaLog::log($message, PEAR_LOG_WARNING);
			}
		}
		return true;
	}
	// }}}
	// {{{ load()
	/**
	 * load()
	 * @access public
	 * @return void
	 */
	public function load() {
		$inerror = 0;
		if (!luna::$model->merge_index(luna::$model->load_nodes('group', 'level'))) { throw new lunaException(_('Error: cannot load data'), PEAR_LOG_CRIT); }
		$user_nid = lunaTools::request('user_nid');
		luna::$model->merge_index(luna::$model->load_users($user_nid));
		$user_nid = luna::$model->check_requested_node('user_nid', 'Person', 'foaf');
		return true;
	}
	// }}}
}
// }}}
?>