<?php
/**
 * lunar mod_example module
 *
 * PHP versions 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 * For more details, see <http://www.gnu.org/copyleft/gpl.html>
 *
 * @author		Odradek
 * @license		http://www.gnu.org/copyleft/gpl.html  GPL
 * @link		https://github.com/jeromev/LunarSystem
 * @package		lunarSystem
 */
// {{{
class mod_example {
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
	 * Class constructor.
	 * @access	private
	 * @return boolean
	 */
	private function __construct() {
		lunaTools::add_vocabulary(array(
			'example'
		));
		return true;
	}
	// }}}
	// {{{ submit()
	/**
	 * do things
	 * @access public
	 * @return boolean
	 */
	public function submit() {
		// do things
		return true;
	}
	// }}}
	// {{{ load()
	/**
	 * load things
	 * @access public
	 * @return boolean
	 */
	public function load() {
		// load things
		return true;
	}
	// }}}
}
// }}}
?>
