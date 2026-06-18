<?php
/**
 * lunar mod_journal module
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
class mod_journal {
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
			'List of the log entries',
			'id',
			'message',
			'Message',
			'Type',
			'type',
			'user',
			'User',
			'date',
			'Date',
			'Log entry analyse',
			'code',
			'error',
			'date',
			'user-name',
			'Server'
		));
		return true;
	}
	// }}}
	// {{{ load()
	/**
	 * @access	public
	 * @return boolean
	 */
	public function load() {
		$inerror = 0;
		// POST-only: request() also reads GET, so a forged link/img could wipe the
		// whole log with a single request. Require a real form POST.
		if (isset($_POST['purgelogs'])) {
			$res = lunaDB::query('
				DELETE FROM
					'.luna::get_ini('DBtables', 'LOGS').'
			');
		}
		$log_id = false;
		if ($log_id = lunaTools::request('log_id')) { 
			luna::$data['log_id'] = $log_id; 
			$res = lunaDB::query('
				SELECT
					*
				FROM
					'.luna::get_ini('DBtables', 'LOGS').'
				WHERE
					id = '.lunaDB::quote(intval($log_id)).'
			');
			while ($row = $res->fetchRow()) { 
				$row->message = unserialize($row->message); 
				$message = get_class($row->message) == 'lunaException'? $row->message->getMessage() : lunaTools::display_string($row->message->message);
				$var = array(
					'type' => 'log',
					'lid' => $row->id,
					'value' => array(
						'message' => $message,
						'code' => _(Log::priorityToString($row->priority)),
						'date' => $row->logtime,
						'content' => print_r($row->message, 1)
					),
				);
				if (!luna::$model->merge_index(luna::$model->load_var($var))) { throw new lunaException(_('Error: cannot load log entry.'), PEAR_LOG_CRIT); }
			}
			$res->free();
		} else { //lunaTools::debug();
			$cookie = array();
			if (isset($_COOKIE[luna::$data['lid'].'_sort'])) {
				// json_decode (not unserialize) to avoid PHP object injection from a crafted cookie.
				$cookie = json_decode($_COOKIE[luna::$data['lid'].'_sort'], true);
				$cookie = is_array($cookie)? lunaTools::sanitize($cookie) : array();
				if (!is_array($cookie)) { $cookie = array(); }
				foreach ($cookie as $k => $v) { $_COOKIE[$k] = $v; }
			}
			// Whitelist the sort column: it is interpolated as a SQL identifier into
			// COUNT()/ORDER BY below, so it must never come straight from request input.
			$order_by = lunaTools::request('order_by', 0, 'logtime');
			$allowed_order_by = array('logtime', 'id', 'priority', 'ident');
			if (!in_array($order_by, $allowed_order_by, true)) { $order_by = 'logtime'; }
			luna::$data['order_by'] = $order_by;
			$cookie['order_by'] = luna::$data['order_by'];
			$order_by_ok = 'l.'.$order_by;
			$order_dir = (lunaTools::request('order_dir', 0, 'DESC') == 'ASC')? 'ASC' : 'DESC';
			luna::$data['order_dir'] = $order_dir;
			$cookie['order_dir'] = luna::$data['order_dir'];
			if (!defined('PERPAGE')) { define('PERPAGE', 20); }
			luna::$data['limit'] = intval(lunaTools::request('limit', 0, PERPAGE));
			if (luna::$data['limit'] < 1) { luna::$data['limit'] = PERPAGE; }
			$cookie['limit'] = luna::$data['limit'];
			if (isset($_GET['start'])) {
				$start = $_GET['start'];
			} else {
				$start = lunaTools::request('start', $_GET);
			}
			$start = intval($start);
			if ($start < 0) { $start = 0; }
			luna::$data['start'] = $start;
			$cookie['start'] = luna::$data['start'];
			if (!lunaTools::set_cookie(luna::$data['lid'].'_sort', $cookie)) { throw new lunaException(_('Error: cannot set cookie.'), PEAR_LOG_CRIT); }
			// lunaTools::debug(luna::$data);
			$res = lunaDB::query('
				SELECT
					COUNT('."$order_by_ok".') as total
				FROM
					'.luna::get_ini('DBtables', 'LOGS').' l 
			');
			$row = $res->fetchRow();
			$res->free();
			$total = $row->total;
			$res = lunaDB::query('
				SELECT
					*
				FROM
					'.luna::get_ini('DBtables', 'LOGS').' l
				ORDER BY
					'.$order_by_ok.' '.$order_dir.'
				LIMIT
					'.$start.', '.luna::$data['limit'].'
			');
			while ($row = $res->fetchRow()) { 
				$row->message = unserialize($row->message);
				$message = get_class($row->message) == 'lunaException'? $row->message->getMessage() : lunaTools::display_string($row->message->message);
				$var = array(
					'type' => 'log',
					'lid' => $row->id,
					'value' => array(
						'message' => $message,
						'code' => _(Log::priorityToString($row->priority)),
						'date' => $row->logtime,
						'user-name' => isset($row->message->session->user->firstname)? $row->message->session->user->firstname.' '.$row->message->session->user->lastname : _(ANONYMOUS)
					),
				);
				if (!luna::$model->merge_index(luna::$model->load_var($var))) { throw new lunaException(_('Error: cannot load log entry.'), PEAR_LOG_CRIT); }
			}
			$res->free();
			luna::$model->merge_index(luna::$model->load_pager($total, $start, luna::$data['limit'], __CLASS__));
		}
		// luna::$model->dump();
		return true;
	}

	// }}}
}
// }}}
?>