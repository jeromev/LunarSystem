<?php
/**
 * lunar model class
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
class lunaModel {
	/**
	 * index
	 * @var array
	 * @access	private
	 */
	private $index = array();
	/**
	 * node_path
	 * @var string
	 * @access	public
	 */
	public $node_path = '';
	/**
	 * aliases
	 * @var array
	 * @access	private
	 */
	private $aliases = array();
	/**
	 * conf
	 * @var array
	 * @access	private
	 */
	private $conf = array();
	/**
	 * triples
	 * @var array
	 * @access	private
	 */
	private $triples = array();
	/**
	 * instance
	 * @var object
	 * @access	private
	 */
	private static $instance = null;
	/**
	 * xsl
	 * @var object
	 * @access	private
	 */
	private $xsl = null;
	/**
	 * xslprocessor
	 * @var object
	 * @access	private
	 */
	private $xslprocessor = null;
	/**
	 * dom
	 * @var object
	 * @access	private
	 */
	private $dom = null;
	/**
	 * lunaNameSpace
	 * @access	public
	 * @var		string
	 */
	public $lunaNameSpace = 'http://lunarsystem.org/ontology#';
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
	 * @return void
	 */
	private function __construct() {
		ksort(luna::$session->user->levels); 
		$cache_rdf_name = 'luna.'.implode('-',luna::$session->user->levels).'.'.luna::$lang;
		$this->conf = array(
			'ns' => array(
				'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
				'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
				'foaf' => 'http://xmlns.com/foaf/0.1/',
				'dc' => 'http://purl.org/dc/elements/1.1/',
				'dcterms' => 'http://purl.org/dc/terms/',
				'owl' => 'http://www.w3.org/2002/07/owl#',
				'luna' => $this->lunaNameSpace,
			),
			'serializer_prettyprint_containers' => 1,
			'serializer_type_nodes' => 1,
		);
		$this->node_path = lunaTools::link('node', true);
		if (luna::$cache) { $cache_obj =& new Cache_Lite(array('cacheDir' => CACHE_PATH, 'lifetime' => luna::$cache_timeout)); }
		if (luna::$cache && ($cache_str = $cache_obj->get($cache_rdf_name))) {
			$array = unserialize($cache_str);
			$this->index = $array['index'];
			$this->aliases = $array['aliases'];
			unset($array);
		} else { 
			// load all the pages from the db into the model
			$this->merge_index($this->load_nodes('page', 'level'));
			if (empty($this->index)) { throw new lunaException(_('Error: cannot build index.'), PEAR_LOG_CRIT); } 
			if (luna::$cache) { $cache_obj->save(serialize(array('index' => $this->index, 'aliases' => $this->aliases))); }
		}
		// lunaTools::debug($this->index);
		return true;
	}
	// }}}
	// {{{ get_node()
	/**
	 * @access public
	 * @param int $nid
	 * @param string $type
	 * @return mixed
	 */
	public function get_node($nid = false, $type = false, $ns = 'luna') { 
		$nid = intval($nid);
		if (empty($nid)) { return false; }
		if (empty($ns) || !isset($this->conf['ns']["$ns"])) { $ns = 'luna'; }
		$ns = $this->conf['ns']["$ns"];
		if (!isset($this->index[$this->node_path.'/'.$nid])) { return false; }
		if (!empty($type)) {
			if ($this->index[$this->node_path.'/'.$nid][$this->conf['ns']['rdf'].'type'][0]['value'] != $ns.$type) { return false; }
		}
		return $this->index[$this->node_path.'/'.$nid];
	}
	// }}}
	// {{{ get_ns()
	/**
	 * @access public
	 * @param string $lid
	 * @return mixed
	 */
	public function get_ns($lid = 'luna') {
		if (empty($lid)) { $lid = 'luna'; }
		if (!isset($this->conf['ns']["$lid"])) { $lid = 'luna'; }
		return $this->conf['ns']["$lid"];
	}
	// }}}
	// {{{ merge_index()
	/**
	 * @access public
	 * @param array $nodes
	 * @return mixed
	 */
	public function merge_index($nodes) {
		if (!empty($nodes)) { $this->index = $this->merge_nodes($this->index, $nodes); }
		return true;
	}
	// }}}
	// {{{ purge_index()
	/**
	 * @access public
	 * @return mixed
	 */
	public function purge_index() {
		$this->index = array();
		$this->aliases = array();
		$this->merge_index($this->load_nodes('page', 'level'));
		if (empty($this->index)) { throw new lunaException(_('Error: cannot build index.'), PEAR_LOG_CRIT); } 
		return true;
	}
	// }}}
	// {{{ merge_nodes()
	/**
	 * @access public
	 * @param array $nodes1
	 * @param array $nodes2
	 * @return array
	 */
	public function merge_nodes($nodes1, $nodes2) { 
		if (!is_array($nodes1) || !is_array($nodes2)) { return false; }
		foreach($nodes2 as $node2_uri => $node2_data) {
			if (!isset($nodes1[$node2_uri])) {
				$nodes1[$node2_uri] = $node2_data;
			} else {
				foreach($node2_data as $data2_uri => $data2_array) {
					if (!isset($nodes1[$node2_uri][$data2_uri])) {
						$nodes1[$node2_uri][$data2_uri] = $data2_array;
					} else {
						foreach($data2_array as $k2 => $data2) { 
							$found = false;
							foreach($nodes1[$node2_uri][$data2_uri] as $k1 => $data1) {
								if ($data1['value'] == $data2['value']) {
									$found = true;
								}
							}
							if (!$found) { $nodes1[$node2_uri][$data2_uri][] = $data2; }
						}
					}
				}
			}
		}
		return $nodes1;
	}
	// }}}
	// {{{ get_level_node()
	/**
	 * @access public
	 * @param array $node
	 * @return mixed
	 */
	public function get_level_node($node = false) {
		if (empty($node) || !is_array($node)) { return false; }
		if (!isset($node[$this->conf['ns']['luna'].'level'][0]['value'])) { return false; }
		if (!isset($this->index[$node[$this->conf['ns']['luna'].'level'][0]['value']])) { return false; }
		return $this->index[$node[$this->conf['ns']['luna'].'level'][0]['value']];
	}
	// }}}
	// {{{ get_page_node_from_alias()
	/**
	 * @access public
	 * @param string $path (optional)
	 * @return mixed
	 */
	public function get_page_node_from_alias($path = '') { 
		$pagenode = false;
		$subdir = false;
		if (empty($path)) { return $this->get_node_from_alias("root", "page"); }
		$node = $this->get_node_from_alias($path); 
		if (!$node) { 
			if (strpos($path, '/') === false) { 
				return false; 
			} else { 
				$patharray = explode('/', $path); 
				foreach ($patharray as $k => $v) { if (empty($v)) { unset($patharray[$k]); } }
				$subdir = array_pop($patharray);
				$path = implode('/', $patharray); 
				// TO DO: if ($path == 'node') { $node_nid = intval($subdir); $path = ''; $node = $this->get_node_from_alias("root", "page"); }
				$node = $this->get_node_from_alias($path, 'page');
				if (!$node) { 
					return false;
				} else {
					luna::$data['subdir'] = $subdir;
					return $node;
				}
			}
		} else {
			return $node;
		}
		return false;
	}
	// }}}
	// {{{ get_node_from_alias()
	/**
	 * @access public
	 * @param string $alias
	 * @param string $type
	 * @param string $ns
	 * @return mixed
	 */
	public function get_node_from_alias($alias = '', $type = false, $ns = 'luna') {
		if (empty($alias)) { $alias = "root"; }
		if (!isset($this->aliases["$alias"])) { return false; }
		if (empty($ns) || !isset($this->conf['ns']["$ns"])) { $ns = 'luna'; }
		$ns = $this->conf['ns']["$ns"];
		if (!isset($this->index[$this->node_path.'/'.$this->aliases["$alias"][$this->conf['ns']['luna'].'nid'][0]['value']])) { return false; }
		if (!empty($type)) {
			if ($this->index[$this->node_path.'/'.$this->aliases["$alias"][$this->conf['ns']['luna'].'nid'][0]['value']][$this->conf['ns']['rdf'].'type'][0]['value'] != $ns.$type) { return false; }
		}
		return $this->index[$this->node_path.'/'.$this->aliases["$alias"][$this->conf['ns']['luna'].'nid'][0]['value']];
	}
	// }}}
	// {{{ get_nid()
	/**
	 * @access public
	 * @param array $node
	 * @param string $type
	 * @param string $ns
	 * @return mixed
	 */
	public function get_nid($node = false, $type = false, $ns = 'luna') { 
		if (empty($node) || !is_array($node)) { return false; }
		if (empty($ns) || !isset($this->conf['ns']["$ns"])) { $ns = 'luna'; }
		$ns = $this->conf['ns']["$ns"];
		if (!isset($node[$this->conf['ns']['luna'].'nid'][0]['value'])) { return false; }
		if (!empty($type)) {
			if ($node[$this->conf['ns']['rdf'].'type'][0]['value'] != $ns.$type) { return false; }
		}
		return $node[$this->conf['ns']['luna'].'nid'][0]['value'];
	}
	// }}}
	// {{{ get_type()
	/**
	 * @access public
	 * @param array $node
	 * @return mixed
	 */
	public function get_type($node = false) { 
		if (empty($node) || !is_array($node)) { return false; }
		if (!isset($node[$this->conf['ns']['rdf'].'type'][0]['value'])) { return false; }
		return $node[$this->conf['ns']['rdf'].'type'][0]['value'];
	}
	// }}}
	// {{{ get_lid()
	/**
	 * @access public
	 * @param array $node
	 * @return mixed
	 */
	public function get_lid($node = false) { 
		if (empty($node) || !is_array($node)) { return false; }
		if (!isset($node[$this->conf['ns']['luna'].'lid'][0]['value'])) { return false; }
		return $node[$this->conf['ns']['luna'].'lid'][0]['value'];
	}
	// }}}
	// {{{ set_property()
	/**
	 * @access public
	 * @param array $node
	 * @param string $prop_lid
	 * @param string $prop_value
	 * @param string $ns
	 * @return mixed
	 */
	public function set_property($node = false, $prop_lid = false, $prop_value = false, $ns = 'luna') { 
		if (empty($node) || !is_array($node) || empty($prop_lid) || empty($prop_value)) { return false; }
		if (empty($ns) || !isset($this->conf['ns']["$ns"])) { $ns = 'luna'; }
		$ns = $this->conf['ns']["$ns"];
		if (!$nid = $this->get_nid($node)) { return false; }
		$node[$ns.$prop_lid][0]['value'] = "$prop_value";
		return $node;
	}
	// }}}
	// {{{ get_nid_from_lid()
	/**
	 * @access public
	 * @param string $lid
	 * @return mixed
	 */
	public function get_nid_from_lid($lid = false) {
		if (empty($lid)) { return false; }
		$nid = false;
		$res = lunaDB::query('
			SELECT
				nid
			FROM
				'.luna::get_ini('DBtables', 'NODES').'
			WHERE
				lid = '.lunaDB::quote("$lid").'
			LIMIT 1
		');
		while ($row = $res->fetchRow()) { $nid = $row->nid; }
		$res->free();
		return $nid;
	}
	// }}}
	// {{{ get_parent_node()
	/**
	 * @access public
	 * @param array $node
	 * @return array
	 */
	public function get_parent_node($node = false) {
		if (empty($node) || !is_array($node)) { return false; }
		if (!isset($node[$this->conf['ns']['owl'].'isChildOf'][0]['value'])) { return false; }
		if (!isset($this->index[$node[$this->conf['ns']['owl'].'isChildOf'][0]['value']])) { return false; }
		return $this->index[$node[$this->conf['ns']['owl'].'isChildOf'][0]['value']];
	}
	// }}}
	// {{{ get_children_nids()
	/**
	 * @access public
	 * @param array $parent_node
	 * @return array
	 */
	public function get_children_nids($parent_node = false) { 
		if (empty($parent_node) || !is_array($parent_node)) { return false; }
		$children_nodes = $this->get_children_nodes($parent_node);
		$children_nids = array();
		if ($children_nodes) {
			foreach($children_nodes as $node) {
				$nid = $this->get_nid($node);
				$children_nids[$nid] = $nid;
			}
		}
		return $children_nids;
	}
	// }}}
	// {{{ get_children_nodes()
	/**
	 * @access public
	 * @param array $parent_node
	 * @return array
	 */
	public function get_children_nodes($parent_node = false) { 
		if (empty($parent_node) || !is_array($parent_node)) { return false; }
		$children = array(); 
		$parent_nid = $this->get_nid($parent_node);
		foreach($this->index as $node) {
			$node_parent_node = $this->get_parent_node($node);
			$node_parent_nid = $this->get_nid($node_parent_node);
			if ($node_parent_nid == $parent_nid) {
				$children[] = $node;
				$subchildren = $this->get_children_nodes($node);
				if ($subchildren) {
					$children[] = $subchildren;
				}
			}
		}
		return $children;
	}
	// }}}
	// {{{ dump()
	/**
	 * @access public
	 * @param string $flavor
	 * @param boolean $return
	 * @return mixed
	 */
	public function dump($flavor = 'xml', $return = false, $node = false) {
		require_once('arc/ARC2.php');
		$index = (empty($node) || !is_array($node))? $this->index : $node;
		switch($flavor) {
			case 'json':
				$ser = ARC2::getRDFJSONSerializer($this->conf);
				$doc = $ser->getSerializedIndex($index);
				if ($return) { return $doc; }
				header('Content-Type: application/rdf+json');
				die($doc);
			case 'n3':
				$ser = ARC2::getNtriplesSerializer($this->conf);
				$doc = $ser->getSerializedIndex($index);
				if ($return) { return $doc; }
				header('Content-Type: application/rdf+n3');
				die($doc);
			case 'turtle':
				$ser = ARC2::getTurtleSerializer($this->conf);
				$doc = $ser->getSerializedIndex($index);
				header('Content-Type: application/rdf+turtle');
				if ($return) { return $doc; }
				die($doc);
			case 'xml':
			default:
				$ser = ARC2::getRDFXMLSerializer($this->conf); 
				$doc = $ser->getSerializedIndex($index); 
				if ($return) { return $doc; }
				header('Content-Type: application/rdf+xml');
				die($doc);
		}
	}
	// }}}
	// {{{ load_messages()
	/** @access public
	 * @param array $messages
	 * @return array
	 */
	public function load_messages($messages = false) { 
		if (!is_array($messages)) { return false; }
		$nodes = array();
		foreach($messages as $code => $code_messages) {
			foreach($code_messages as $k => $message) {
				$var_node = $this->load_var(array(
					'type' => 'message',
					'lid' => $k.md5($message),
					'value' => array(
						'value' => "$message",
						'code' => "$code"
					)
				));
				$nodes = $this->merge_nodes($nodes, $var_node);
			}
		}
		return $nodes;
	}
	// }}}
	// {{{ load_pager()
	/** @access public
	 * @param integer $total_items
	 * @param integer $start_item
	 * @param integer $per_page
	 * @param string $name
	 * @return array
	 */
	public function load_pager($total_items = 0, $start_item = 0, $per_page = 0, $name = 0) {
		if (empty($total_items)) { return false; }
		if (empty($name)) { $name = 'default'; }
		$total_items = intval($total_items);
		$start_item = intval($start_item);
		$per_page = intval($per_page);
		$per_page = $per_page > 0? $per_page : PERPAGE;
		$total_pages = ceil($total_items/$per_page);
		$on_page = floor($start_item / $per_page) + 1;
		$var_node = $this->load_var(array(
			'type' => 'pager',
			'lid' => "$name",
			'value' => array(
				'value' => "$on_page",
				'perpage' => "$per_page",
				'total' => "$total_pages"
			)
		));
		return $var_node;
	}
	// }}}
	// {{{ load_user()
	/**
	 * @access public
	 * @param mixed $user
	 * @param boolean $is_current
	 * @return mixed
	 */
	public function load_user($user = false, $is_current = false) { //lunaTools::debug($user);
		if (empty($user)) { return false; }
		$nodes = array();
		if (is_array($user) && !isset($user['nid'])) { 
			foreach($user as $k => $u) { $nodes = $this->merge_nodes($nodes, $this->load_user($u)); }
		} else {
			if (is_object($user)) { $user = get_object_vars($user); }
			if (isset($user['is_current'])) { $is_current = $user['is_current']? 1 : 0; }
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['foaf'].'Person';
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'nid'][0]['value'] = $user['nid'];
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'ip'][0]['value'] = $user['session_ip'];
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'is_active'][0]['value'] = $user['is_active'];
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'is_guest'][0]['value'] = $user['email'] == ANONYMOUS? '1' : '0';
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'is_current'][0]['value'] = $is_current? '1' : '0';
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'url'][0]['value'] = $user['last_url'];
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'last-visit'][0]['value'] = lunaTools::get_time_since($user['last_time']);
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'registration-date'][0]['value'] = ($user['regis_time'] == 0? '' : lunaTools::format_date($user['regis_time']));
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['foaf'].'name'][0]['value'] = trim(lunaTools::display_string($user['firstname']).' '.lunaTools::display_string($user['lastname']));
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['foaf'].'firstName'][0]['value'] = trim(lunaTools::display_string($user['firstname']));
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['foaf'].'surName'][0]['value'] = trim(lunaTools::display_string($user['lastname']));
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['foaf'].'mbox'][0]['value'] = 'mailto:'.$user['email'];
			$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['foaf'].'mbox'][0]['type'] = 'uri';
			if (isset($user['groups']) && is_array($user['groups'])) {
				foreach ($user['groups'] as $group_nid) { 
					$nodes[$this->node_path.'/'.$group_nid][$this->conf['ns']['luna'].'nid'][0]['value'] = $group_nid;
					$nodes[$this->node_path.'/'.$group_nid][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].'group';
					$nodes[$this->node_path.'/'.$group_nid][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
					$needle = array(
						'value' => $this->node_path.'/'.$group_nid,
						'type' => 'uri'
					);
					if (!isset($nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'group']) || !in_array($needle, $nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'group'])) {
						$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'group'][] = $needle;
					}
				}
			}
			if (isset($user['levels']) && is_array($user['levels'])) {
				foreach ($user['levels'] as $level_nid) { 
					$nodes[$this->node_path.'/'.$level_nid][$this->conf['ns']['luna'].'nid'][0]['value'] = $level_nid;
					$nodes[$this->node_path.'/'.$level_nid][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].'level';
					$nodes[$this->node_path.'/'.$level_nid][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
					$needle = array(
						'value' => $this->node_path.'/'.$level_nid,
						'type' => 'uri'
					);
					if (!isset($nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'level']) || !in_array($needle, $nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'level'])) {
						$nodes[$this->node_path.'/'.$user['nid']][$this->conf['ns']['luna'].'level'][] = $needle;
					}
				}
			}
		}
		return $nodes;
	}
	// }}}
	// {{{ load_users()
	/**
	 * @access public
	 * @param integer $user_nid
	 * @param integer $group
	 * @return array
	 */
	public function load_users($user_nid = false, $group = false) { 
		lunaTools::parse_sort_cookie(luna::$data['lid']);
		$nodes = array();
		$user_nid = intval($user_nid);
		if (!empty($user_nid)) {
			$res = lunaDB::query('
				SELECT
					nu.nid,
					nu.is_active, 
					u.firstname, 
					u.lastname, 
					nu.lid as email,
					u.regis_time, 
					u.last_time, 
					u.last_url,
					u.lang,
					g.nid as group_nid,
					l.nid as level_nid
				FROM
					'.luna::get_ini('DBtables', 'USERS').' u,
					'.luna::get_ini('DBtables', 'NODES').' nu,
					'.luna::get_ini('DBtables', 'NODES').' g,
					'.luna::get_ini('DBtables', 'NODES').' l,
					'.luna::get_ini('DBtables', 'NODES_MAP').' gl,
					'.luna::get_ini('DBtables', 'CLASSES').' tg,
					'.luna::get_ini('DBtables', 'CLASSES').' tu,
					'.luna::get_ini('DBtables', 'NODES_MAP').' ug,
					'.luna::get_ini('DBtables', 'CLASSES').' tl
				WHERE 1= 1 
					AND nu.nid = '.lunaDB::quote($user_nid).'
					AND ug.nid1 = u.nid 
					AND ug.nid2 = g.nid
					AND u.nid = nu.nid
					AND tu.lid = '.lunaDB::quote('user').' 
					AND nu.tid = tu.id
					AND tg.lid = '.lunaDB::quote('group').' 
					AND g.tid = tg.id
					AND l.tid = tl.id
					AND tl.lid = '.lunaDB::quote('level').'
					AND gl.nid1 = g.nid 
					AND gl.nid2 = l.nid
			');
		} else {
			$cookie['order_by'] = luna::$data['order_by'] = lunaTools::request('order_by', 0, 'last_time');
			if (!empty($group)) { $_POST['group_nid'] = intval($group); }
			$group_nid = lunaTools::request('group_nid');
			$groupsql = !empty($group_nid)? ' AND g.nid = '.lunaDB::quote(intval($group_nid)).' ' : '';
			$order_dir = lunaTools::request('order_dir', 0, 'DESC');
			$alphastyle = 0;
			switch($cookie['order_by']) {
				case 'nid':
					$order_by_ok = 'nu.nid';
					$alphastyle = false;
					if (empty($order_dir)) { $order_dir = 'ASC'; }
					break;
				case 'firstname':
				case 'lastname':
					$order_by_ok = 'u.'.$cookie['order_by'];
					$alphastyle = true;
					if (empty($order_dir)) { $order_dir = 'ASC'; }
					break;
				case 'email':
					$order_by_ok = 'nu.lid';
					$alphastyle = true;
					if (empty($order_dir)) { $order_dir = 'ASC'; }
					break;
				case 'regis_time':
				case 'last_time': 
				default:
					$order_by_ok = 'u.'.$cookie['order_by'];
					$alphastyle = false;
					if (empty($order_dir)) { $order_dir = 'DESC'; }
					break;
			}
			$order_dir = ($order_dir == 'DESC' || empty($order_dir))? 'DESC' : 'ASC';
			luna::$data['order_dir'] = $order_dir;
			$cookie['order_dir'] = luna::$data['order_dir'];
			if (!defined('PERPAGE')) { define('PERPAGE', 20); } 
			luna::$data['limit'] = lunaTools::request('limit', 0, PERPAGE);
			luna::$data['limit'] = luna::$data['limit'];
			$start = lunaTools::request('start', 0, 0);
			if (empty($start)) { $start = 0; }
			luna::$data['start'] = luna::$data['start'] = $start;
			/*
			$letters = array();
			$this->letter = 'A';
			if ($alphastyle) {
				switch($order_by) {
					case 'firstname':
					case 'lastname':
					case 'email':
						$res = lunaDB::query('
							SELECT
								DISTINCT LEFT('.$order_by_ok.', 1) as letter
							FROM
								'.luna::get_ini('DBtables', 'USERS').' u,
								'.luna::get_ini('DBtables', 'NODES').' nu,
								'.luna::get_ini('DBtables', 'NODES').' g,
								'.luna::get_ini('DBtables', 'NODES_MAP').' ug,
								'.luna::get_ini('DBtables', 'CLASSES').' tu,
								'.luna::get_ini('DBtables', 'CLASSES').' tg
							WHERE
								tu.lid = '.lunaDB::quote('user').' AND nu.tid = tu.id
								AND tg.lid = '.lunaDB::quote('group').' AND g.tid = tg.id
								AND ug.nid1 = nu.nid AND ug.nid2 = g.nid '.$groupsql.'
						');
						break;
				}
				$letters = array();
				while ($row = $res->fetchRow()) { 
					$letter = strtoupper(substr($row->letter,0,1));
					if (!empty($letter)) { $letters[] = $letter; }
				}
				$res->free(); 
				$letters = array_unique($letters); 
				sort($letters); 
				// lunaTools::debug($letters);
				if ($l = lunaTools::request('letter')) { $this->letter = substr($l, 0, 1); } else if (!empty($letters)) { $this->letter = $letters[0]; }
				if (!in_array($this->letter, $letters)) { $this->letter = isset($letters[0])? $letters[0] : '' ; }
				luna::$model->insert_alphabet_nav($letters, $this->letter);
				luna::$data['letter'] = $this->letter;
				$cookie['letter'] = luna::$data['letter'];
			}
			*/
			lunaTools::set_cookie(luna::$data['lid'].'_sort', $cookie);
			switch($order_by) {
				case 'firstname':
				case 'lastname':
				case 'email':
				/*
					$res = lunaDB::query('
						SELECT
							COUNT(DISTINCT nu.nid) as total
						FROM
							'.luna::get_ini('DBtables', 'USERS').' u,
							'.luna::get_ini('DBtables', 'NODES').' nu,
							'.luna::get_ini('DBtables', 'NODES').' g,
							'.luna::get_ini('DBtables', 'NODES_MAP').' ug,
							'.luna::get_ini('DBtables', 'CLASSES').' tu,
							'.luna::get_ini('DBtables', 'CLASSES').' tg
						WHERE 1 = 1
							AND tu.lid = '.lunaDB::quote('user').' 
							AND nu.tid = tu.id
							AND u.nid = nu.nid
							AND tg.lid = '.lunaDB::quote('group').' 
							AND g.tid = tg.id
							AND ug.nid1 = nu.nid 
							AND ug.nid2 = g.nid '.$groupsql.'
							AND '.$order_by_ok.' LIKE '.lunaDB::quote($this->letter.'%').'
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
					'); //lunaTools::debug($res);
					break;
				*/
				case 'nid':
				case 'regis_time':
				case 'last_time':
				default:
					$res = lunaDB::query('
						SELECT
							COUNT(DISTINCT nu.nid) as total
						FROM
							'.luna::get_ini('DBtables', 'USERS').' u,
							'.luna::get_ini('DBtables', 'NODES').' nu,
							'.luna::get_ini('DBtables', 'NODES').' g,
							'.luna::get_ini('DBtables', 'NODES_MAP').' ug,
							'.luna::get_ini('DBtables', 'CLASSES').' tu,
							'.luna::get_ini('DBtables', 'CLASSES').' tg
						WHERE 1 = 1
							AND tu.lid = '.lunaDB::quote('user').' 
							AND nu.tid = tu.id
							AND u.nid = nu.nid
							AND tg.lid = '.lunaDB::quote('group').' 
							AND g.tid = tg.id
							AND ug.nid1 = nu.nid 
							AND ug.nid2 = g.nid '.$groupsql.'
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
					');
					break;
			}
			$row = $res->fetchRow();
			$res->free();
			$total = empty($row)? 0 : $row->total; 
			switch($order_by) {
				case 'firstname':
				case 'lastname':
				case 'email':
				/*
					$res = lunaDB::query('
						SELECT
							nu.nid,
							nu.is_active, 
							u.firstname, 
							u.lastname, 
							nu.lid as email,
							u.regis_time, 
							u.last_time, 
							u.last_url,
							u.lang
						FROM
							'.luna::get_ini('DBtables', 'USERS').' u,
							'.luna::get_ini('DBtables', 'NODES').' nu,
							'.luna::get_ini('DBtables', 'NODES').' g,
							'.luna::get_ini('DBtables', 'NODES_MAP').' ug,
							'.luna::get_ini('DBtables', 'CLASSES').' tu,
							'.luna::get_ini('DBtables', 'CLASSES').' tg
						WHERE 1 = 1
							AND tu.lid = '.lunaDB::quote('user').' 
							AND nu.tid = tu.id
							AND u.nid = nu.nid
							AND tg.lid = '.lunaDB::quote('group').' 
							AND g.tid = tg.id
							AND ug.nid1 = nu.nid 
							AND ug.nid2 = g.nid '.$groupsql.'
							AND '.$order_by_ok.' LIKE '.lunaDB::quote($this->letter.'%').'
						GROUP BY
							nu.nid
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
						LIMIT
							'.$start.', '.luna::$data['limit'].'
					');
					break;
				*/
				case 'nid':
				case 'regis_time':
				case 'last_time':
				default:
					$res = lunaDB::query('
						SELECT
							nu.nid,
							nu.is_active, 
							u.firstname, 
							u.lastname, 
							nu.lid as email,
							u.regis_time, 
							u.last_time, 
							u.last_url,
							u.lang,
							g.nid as group_nid,
							l.nid as level_nid
						FROM
							'.luna::get_ini('DBtables', 'USERS').' u,
							'.luna::get_ini('DBtables', 'NODES').' nu,
							'.luna::get_ini('DBtables', 'NODES').' g,
							'.luna::get_ini('DBtables', 'NODES').' l,
							'.luna::get_ini('DBtables', 'NODES_MAP').' gl,
							'.luna::get_ini('DBtables', 'NODES_MAP').' ug,
							'.luna::get_ini('DBtables', 'CLASSES').' tu,
							'.luna::get_ini('DBtables', 'CLASSES').' tg,
							'.luna::get_ini('DBtables', 'CLASSES').' tl
						WHERE 1 = 1
							AND tu.lid = '.lunaDB::quote('user').' 
							AND nu.tid = tu.id
							AND tg.lid = '.lunaDB::quote('group').' 
							AND l.tid = tl.id 
							AND tl.lid = '.lunaDB::quote('level').'
							AND gl.nid1 = g.nid 
							AND gl.nid2 = l.nid
							AND g.tid = tg.id
							AND ug.nid1 = nu.nid 
							AND ug.nid2 = g.nid '.$groupsql.'
							AND u.nid = nu.nid
						GROUP BY
							nu.nid
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
						LIMIT
							'.$start.', '.luna::$data['limit'].'
					');
					break;
			}
		}
		$users = array();
		while ($row = $res->fetchRow()) { 
			$users[$row->nid]['nid'] = $row->nid;
			$users[$row->nid]['is_active'] = $row->is_active;
			$users[$row->nid]['firstname'] = $row->firstname;
			$users[$row->nid]['lastname'] = $row->lastname;
			$users[$row->nid]['email'] = $row->email;
			$users[$row->nid]['regis_time'] = $row->regis_time;
			$users[$row->nid]['last_time'] = $row->last_time;
			$users[$row->nid]['last_url'] = $row->last_url;
			$users[$row->nid]['lang'] = $row->lang;
			$users[$row->nid]['groups'][$row->group_nid] = $row->group_nid;
			$users[$row->nid]['levels'][$row->level_nid] = $row->level_nid;
			$users[$row->nid]['is_current'] = ($row->nid == luna::$session->user->nid)? 1 : 0;
		}
		$res->free();
		// lunaTools::debug($users);
		$nodes = luna::$model->merge_nodes($nodes, luna::$model->load_user($users)); 
		luna::$model->merge_index(luna::$model->load_pager($total, $start, luna::$data['limit'], luna::$data['lid']));
		return $nodes;
	}
	// }}}
	// {{{ load_text()
	/**
	 * @access public
	 * @param mixed $item
	 * @return mixed
	 */
	public function load_text($item = false) { 
		if (empty($item)) { return false; }
		$nodes = array();
		if (is_array($item) && !isset($item['nid'])) { 
			foreach($item as $k => $v) { $nodes = $this->merge_nodes($nodes, $this->load_text($v)); }
		} else { //lunaTools::debug($item);
			if (is_object($item)) { $item = get_object_vars($item); }
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].'text';
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'nid'][0]['value'] = $item['nid'];
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'lid'][0]['value'] = $item['lid'];
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['rdfs'].'label'][0]['value'] = $item['title'];
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['rdfs'].'label'][0]['type'] = 'literal';
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['rdfs'].'label'][0]['lang'] = lunaTools::format_language($item['lang']);
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'is_active'][0]['value'] = $item['is_active'];
			$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'save_time'][0]['value'] = ($item['save_time'] == 0? '' : lunaTools::format_date($item['save_time']));
			if (isset($item['content_html']) && !empty($item['content_html'])) {
				$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'content'][0]['value'] = $item['content_html'];
				$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'content'][0]['type'] = 'literal';
				$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'content'][0]['lang'] = lunaTools::format_language($item['lang']);
			}
			if (isset($item['user']) && is_array($item['user'])) { //lunaTools::debug($item['user']);
				$nodes[$this->node_path.'/'.$item['user']['nid']][$this->conf['ns']['luna'].'nid'][0]['value'] = $item['user']['nid'];
				$nodes[$this->node_path.'/'.$item['user']['nid']][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['foaf'].'Person';
				$nodes[$this->node_path.'/'.$item['user']['nid']][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
				$needle = array(
					'value' => $this->node_path.'/'.$item['user']['nid'],
					'type' => 'uri'
				);
				if (!isset($nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'user']) || !in_array($needle, $nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'user'])) {
					$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'user'][] = $needle;
				}
			}
			if (isset($item['pages'])) { 
				foreach($item['pages'] as $page_nid) {
					$nodes[$this->node_path.'/'.$page_nid][$this->conf['ns']['luna'].'nid'][0]['value'] = $page_nid;
					$nodes[$this->node_path.'/'.$page_nid][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].'page';
					$nodes[$this->node_path.'/'.$page_nid][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
					$needle = array(
						'value' => $this->node_path.'/'.$page_nid,
						'type' => 'uri'
					);
					if (!isset($nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'page']) || !in_array($needle, $nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'page'])) {
						$nodes[$this->node_path.'/'.$item['nid']][$this->conf['ns']['luna'].'page'][] = $needle;
					}
				}
			}
		}
		return $nodes;
	}
	// }}}
	// {{{ load_texts()
	/**
	 * @access public
	 * @param integer $item_nid
	 * @param integer $page_nid
	 * @return array
	 */
	public function load_texts($item_nid = false, $page_nid = false) {
		lunaTools::parse_sort_cookie(luna::$data['lid']);
		$cookie = array();
		$nodes = array();
		$item_nid = intval($item_nid);
		$page_nid = intval($page_nid);
		// lunaTools::debug($item_nid);
		if (!empty($item_nid)) {
			$res = lunaDB::query('
				SELECT DISTINCT
					t.title,
					t.lang,
					t.content_html,
					n.nid,
					p.nid as page_nid,
					n.lid,
					p.lid as page_lid,
					n.is_active,
					u.nid as user_nid,
					u.firstname,
					u.lastname,
					a.ntime
				FROM
					'.luna::get_ini('DBtables', 'NODES').' n,
					'.luna::get_ini('DBtables', 'NODES').' p,
					'.luna::get_ini('DBtables', 'NODES_MAP').' map,
					'.luna::get_ini('DBtables', 'TEXTS').' t,
					'.luna::get_ini('DBtables', 'ACTIONS').' a,
					'.luna::get_ini('DBtables', 'USERS').' u
				WHERE
					n.tid = (
						SELECT 
							id 
						FROM 
							'.luna::get_ini('DBtables', 'CLASSES').' 
						WHERE 
							lid = '.lunaDB::quote('text').'
					)
					AND t.nid = n.nid
					AND a.nid = n.nid
					AND u.nid = a.unid
					AND n.nid = '.lunaDB::quote($item_nid).'
					AND map.nid1 = n.nid
					AND map.nid2 = p.nid
			');
		} else if (!empty($page_nid)) {
			$res = lunaDB::query('
				SELECT DISTINCT
					t.title,
					t.lang,
					t.content_html,
					n.nid,
					p.nid as page_nid,
					n.lid,
					p.lid as page_lid,
					n.is_active,
					u.nid as user_nid,
					u.firstname,
					u.lastname,
					a.ntime
				FROM
					'.luna::get_ini('DBtables', 'NODES').' n,
					'.luna::get_ini('DBtables', 'NODES').' p,
					'.luna::get_ini('DBtables', 'NODES_MAP').' map,
					'.luna::get_ini('DBtables', 'TEXTS').' t,
					'.luna::get_ini('DBtables', 'ACTIONS').' a,
					'.luna::get_ini('DBtables', 'USERS').' u
				WHERE
					n.tid = (
						SELECT 
							id 
						FROM 
							'.luna::get_ini('DBtables', 'CLASSES').' 
						WHERE 
							lid = '.lunaDB::quote('text').'
					)
					AND t.nid = n.nid
					AND a.nid = n.nid
					AND u.nid = a.unid
					AND p.nid = '.lunaDB::quote($page_nid).'
					AND map.nid1 = n.nid
					AND map.nid2 = p.nid
			');
		} else {
			$cookie['order_by'] = luna::$data['order_by'] = lunaTools::request('order_by', 0, 'last_time');
			$order_dir = lunaTools::request('order_dir', 'DESC');
			$alphastyle = 0;
			switch(luna::$data['order_by']) {
				case 'lid':
					$order_by_ok = 'n.'.luna::$data['order_by'];
					$alphastyle = true;
					if (empty($order_dir)) { $order_dir = 'ASC'; }
					break;
				case 'title':
				case 'lang':
					$order_by_ok = 't.'.luna::$data['order_by'];
					$alphastyle = true;
					if (empty($order_dir)) { $order_dir = 'ASC'; }
					break;
				case 'last_time':
				default:
					$order_by_ok = 'a.ntime';
					$alphastyle = false;
					if (empty($order_dir)) { $order_dir = 'DESC'; }
					break;
			}
			$cookie['order_dir'] = luna::$data['order_dir'] = ($order_dir == 'DESC' || empty($order_dir))? 'DESC' : 'ASC';
			if (!defined('PERPAGE')) { define('PERPAGE', 20); } 
			luna::$data['limit'] = lunaTools::request('limit', 0, PERPAGE);
			$cookie['limit'] = luna::$data['limit'];
			$start = lunaTools::request('start', 0, 0);
			if (empty($start)) { $start = 0; }
			$cookie['start'] = luna::$data['start'] = $start;
	/*	$letters = array();
			$this->letter = 'A';
			if ($alphastyle) {
				$res = lunaDB::query('
					SELECT
						DISTINCT LEFT('.$order_by_ok.', 1) as letter
					FROM
						'.luna::get_ini('DBtables', 'NODES').' n,
						'.luna::get_ini('DBtables', 'TEXTS').' t
					WHERE
						n.tid = (
							SELECT 
								id 
							FROM 
								'.luna::get_ini('DBtables', 'CLASSES').' 
							WHERE 
								lid = '.lunaDB::quote('text').'
						)
						AND n.nid = t.nid
				');
				$letters = array();
				while ($row = $res->fetchRow()) { 
					$letter = strtoupper(substr($row->letter,0,1));
					if (!empty($letter)) { $letters[] = $letter; }
				}
				$res->free(); 
				$letters = array_unique($letters); 
				sort($letters); 
				// lunaTools::debug($letters);
				if ($l = lunaTools::request('letter')) { $this->letter = substr($l, 0, 1); } else if (!empty($letters)) { $this->letter = $letters[0]; }
				if (!in_array($this->letter, $letters)) { $this->letter = isset($letters[0])? $letters[0] : ''; }
				lunaTools::insert_alphabet_nav(lunaTools::RDF, $letters, $this->letter);
				luna::$data['letter'] = $this->letter;
				$cookie['letter'] = luna::$data['letter'];
			}
			*/
			lunaTools::set_cookie(luna::$data['lid'].'_sort', $cookie);
			switch(luna::$data['order_by']) {
				case 'lid':
				case 'title':
				case 'lang':
				/*	$res = lunaDB::query('
						SELECT
							COUNT(DISTINCT n.nid) as total
						FROM
							'.luna::get_ini('DBtables', 'NODES').' n,
							'.luna::get_ini('DBtables', 'TEXTS').' t
						WHERE
							n.tid = (
								SELECT 
									id 
								FROM 
									'.luna::get_ini('DBtables', 'CLASSES').' 
								WHERE 
									lid = '.lunaDB::quote('text').'
							)
							AND n.nid = t.nid
							AND '.$order_by_ok.' LIKE '.lunaDB::quote($this->letter.'%').'
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
					');
					break;
				*/
				case 'last_time':
				default:
					$res = lunaDB::query('
						SELECT
							COUNT(DISTINCT n.nid) as total
						FROM
							'.luna::get_ini('DBtables', 'NODES').' n,
							'.luna::get_ini('DBtables', 'TEXTS').' t,
							'.luna::get_ini('DBtables', 'ACTIONS').' a
						WHERE
							n.tid = (
								SELECT 
									id 
								FROM 
									'.luna::get_ini('DBtables', 'CLASSES').' 
								WHERE 
									lid = '.lunaDB::quote('text').'
							)
							AND t.nid = n.nid
							AND a.nid = n.nid
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
					');
					break;
			}
			$row = $res->fetchRow();
			$res->free();
			$total = empty($row)? 0 : $row->total; //lunaTools::debug($row);
			switch(luna::$data['order_by']) {
				case 'lid':
				case 'title':
				case 'lang':
				/*	$res = lunaDB::query('
						SELECT
							t.title,
							t.lang,
							n.nid,
							n.lid,
							n.is_active,
							u.firstname,
							u.lastname,
							a.ntime
						FROM
							'.luna::get_ini('DBtables', 'NODES').' n,
							'.luna::get_ini('DBtables', 'TEXTS').' t,
							'.luna::get_ini('DBtables', 'ACTIONS').' a,
							'.luna::get_ini('DBtables', 'USERS').' u
						WHERE
							n.tid = (
								SELECT 
									id 
								FROM 
									'.luna::get_ini('DBtables', 'CLASSES').' 
								WHERE 
									lid = '.lunaDB::quote('text').'
							)
							AND t.nid = n.nid
							AND a.nid = n.nid
							AND u.nid = a.unid
							AND '.$order_by_ok.' LIKE '.lunaDB::quote($this->letter.'%').'
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
						LIMIT
							'.$start.', '.luna::$data['limit'].'
					');
					break;
				*/
				case 'last_time':
				default:
					$res = lunaDB::query('
						SELECT
							t.title,
							t.lang,
							n.nid,
							n.lid,
							n.is_active,
							u.nid as user_nid,
							u.firstname,
							u.lastname,
							a.ntime
						FROM
							'.luna::get_ini('DBtables', 'NODES').' n,
							'.luna::get_ini('DBtables', 'TEXTS').' t,
							'.luna::get_ini('DBtables', 'ACTIONS').' a,
							'.luna::get_ini('DBtables', 'USERS').' u
						WHERE
							n.tid = (
								SELECT 
									id 
								FROM 
									'.luna::get_ini('DBtables', 'CLASSES').' 
								WHERE 
									lid = '.lunaDB::quote('text').'
							)
							AND t.nid = n.nid
							AND a.nid = n.nid
							AND u.nid = a.unid
						ORDER BY
							'.$order_by_ok.' '.$order_dir.'
						LIMIT
							'.$start.', '.luna::$data['limit'].'
					');
					break;
			}
		}
		$texts = array();
		while ($row = $res->fetchRow()) { 
			$texts[$row->nid]['nid'] = $row->nid;
			$texts[$row->nid]['lid'] = $row->lid;
			$texts[$row->nid]['title'] = $row->title;
			$texts[$row->nid]['is_active'] = $row->is_active;
			$texts[$row->nid]['user']['nid'] = $row->user_nid;
			$texts[$row->nid]['user']['firstname'] = $row->firstname;
			$texts[$row->nid]['user']['lastname'] = $row->lastname;
			$texts[$row->nid]['pages'][$row->page_nid] = $row->page_nid;
			$texts[$row->nid]['save_time'] = $row->ntime;
			if (isset($row->content_html)) { $texts[$row->nid]['content_html'] = $row->content_html; }
			if (isset($row->lang)) { $texts[$row->nid]['lang'] = $row->lang; }
		}
		$res->free();
		// lunaTools::debug($texts);
		$nodes = luna::$model->merge_nodes($nodes, luna::$model->load_text($texts)); 
		luna::$model->merge_index(luna::$model->load_pager($total, $start, luna::$data['limit'], luna::$data['lid']));
		return $nodes;
	}
	// }}}
	// {{{ load_data()
	/**
	 * @access public
	 * @param array $data
	 * @return array
	 */
	public function load_data($data = false, $label = 'data') {
		if (!is_array($data)) { return false; }
		$nodes = array();
		foreach($data as $k => $v) {
			if (is_array($v)) {
				foreach($v as $vk => $vv) {
					if ($vk != 'PHPSESSID') {
						$var_node = $this->load_var(array(
							'type' => $label,
							'lid' => "$k.$vk",
							'value' => "$vv"
						));
						$nodes = $this->merge_nodes($nodes, $var_node);
					}
				}
			} else {
				if ($k != 'PHPSESSID') {
					$var_node = $this->load_var(array(
						'type' => $label,
						'lid' => "$k",
						'value' => "$v"
					));
					$nodes = $this->merge_nodes($nodes, $var_node);
				}
			}
		}
		return $nodes;
	}
	// }}}
	// {{{ load_request
	/**
	 * @access public
	 * @param array $data
	 * @param string $label
	 * @return boolean
	 */
	public function load_request($data = false, $label = false) { //lunaTools::debug($data);
		if (empty($label)) { return false; }
		$nodes = array();
		if (is_array($data)) { 
			foreach($data as $k => $v) { 
				if (is_array($v)) { //lunaTools::debug($v);
					$klabel = ($label == 'request')? "$k" : $label.'.'."$k"; 
					$nodes = $this->merge_nodes($nodes, $this->load_request($v, $klabel)); // lunaTools::debug($nodes);
				} else { 
					if (empty($k)) { $k = "0"; }
					if ($k != 'PHPSESSID') { 
						$klabel = ($label == 'request')? "$k" : $label.'.'."$k"; 
						$serv = $v;
						$unserv = unserialize($v); 
						if (empty($unserv)) {
							$nodes = $this->merge_nodes($nodes, $this->load_request($serv, $klabel));
						} else {
							$nodes = $this->merge_nodes($nodes, $this->load_request($unserv, $klabel));
						}
					}
				}
			}
		} else {
			if ($label != 'PHPSESSID') {
				$var_node = $this->load_var(array(
					'type' => 'request',
					'lid' => "$label",
					'value' => "$data"
				));
				$nodes = $this->merge_nodes($nodes, $var_node);
			}
		}
		return $nodes;
	}
	// }}}
	// {{{ load_vocabulary()
	/**
	 * @access public
	 * @param array $vocabulary
	 * @return array
	 */
	public function load_vocabulary($vocabulary = false) { 
		if (!is_array($vocabulary)) { return false; }
		$nodes = array(); 
		foreach($vocabulary as $k => $v) {
			$var_node = $this->load_var(array(
				'type' => 'vocabulary',
				'lid' => "$k",
				'value' => _("$v"),
				'lang' => luna::$lang
			));
			$nodes = $this->merge_nodes($nodes, $var_node);
		}
		return $nodes;
	}
	// }}}
	// {{{ check_requested_node()
	/**
	 * @access public
	 * @param string var
	 * @param string type
	 * @param string ns
	 * @return integer
	 */
	public function check_requested_node($var = false, $type = false, $ns = 'luna') {
		if (empty($var)) { return false; } 
		if (empty($ns)) { $ns = 'luna'; }
		$nid = lunaTools::request("$var"); 
		if ($nid) { $node = luna::$model->get_node($nid, "$type", "$ns"); } 
		if ($node) { 
			$_POST["$var"] = $_REQUEST["$var"] = $nid; 
			luna::$data['modify_item_nid'] = $nid;
		} else {
			if (isset(luna::$data['subdir']) && !empty(luna::$data['subdir'])) {
				$nid = luna::$model->get_nid_from_lid(luna::$data['subdir']); 
				$node = luna::$model->get_node($nid, "$type");
				if ($node) { $_POST[$var] = $_REQUEST[$var] = $nid; }
				luna::$data['modify_item_nid'] = $nid;
				luna::$model->merge_index(luna::$model->load_users(false, $nid));
			}
		}
		return $nid;
	}
	// }}}
	// {{{ check_if_node_exists()
	/**
	 * @access public
	 * @param integer nid
	 * @param string type
	 * @return array
	 */
	public function check_if_node_exists($nid = false, $type = false) {
		$nid = intval($nid);
		if (empty($nid) || empty($type)) { return false; }
		$item_node = luna::$model->get_node($nid, "$type");
		if (!$item_node) {
			$message = sprintf(_("Unknown $type #%1\$s."), $_POST['modify_item_nid']);
			luna::$messages['warning'][] = $message;
			lunaLog::log($message, PEAR_LOG_WARNING); 
			return false;
		}
		return $item_node;
	}
	// }}}
	// {{{ check_if_lid_is_protected()
	/**
	 * @access public
	 * @param array node
	 * @param array lids
	 * @return array
	 */
	public function check_if_lid_is_protected($node = false, $lids = false) {
		if (empty($node) || !is_array($lids) || empty($lids)) { return false; }
		$item_lid = $this->get_lid($node);
		if (!$item_lid || in_array($item_lid, $lids)) {
			$message = sprintf(_("You cannot modify the item labeled “%1\$s”."), _($item_lid));
			luna::$messages['warning'][] = $message;
			lunaLog::log($message, PEAR_LOG_NOTICE);
			return false;
		}
		return $item_lid;
	}
	// }}}
	// {{{ check_if_lid_is_taken()
	/**
	 * @access public
	 * @param array node
	 * @param array lids
	 * @return array
	 */
	public function check_if_lid_is_taken($lid = false, $nid = false) {
		if (empty($lid)) { return false; }
		$nid = intval($nid);
		if ($this->lid_is_taken("$lid", $nid)) {
			$inerror++; 
			$message = sprintf(_("The identifier “%1\$s” is already taken."), "$lid");
			luna::$messages['warning'][] = $message;
			lunaLog::log($message, PEAR_LOG_NOTICE); 
			return false;
		}
		return true;
	}
	// }}}
	// {{{ load_var()
	/**
	 * @access public
	 * @param array $var
	 * @return array
	 */
	public function load_var($var = false) {
		if (!is_array($var)) { return false; }
		if (!isset($var['type'])) {
			$nodes = array();
			foreach($var as $v) { 
				$subnodes = $this->load_var($v);
				if (!$subnodes) { return false; }
				$nodes = $this->merge_nodes($nodes, $subnodes);
			}
		} else {
			if (!isset($var['lid']) || !isset($var['value'])) { return false; }
			$nodes = array();
			$lid = lunaTools::prepare_lid($var['lid']);
			$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['luna'].'lid'][0]['value'] = $var['lid'];
			$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['luna'].'lid'][0]['type'] = 'bnode';
			if (isset($var['lang'])) { $nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['luna'].'lid'][0]['lang'] = $var['lang']; }
			if (is_array($var['value'])) {
				foreach($var['value'] as $k => $v) {
					$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['luna']."$k"][0]['value'] = "$v";
					$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['luna']."$k"][0]['type'] = 'bnode';
				}
			} else {
				$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['luna']."value"][0]['value'] = $var['value'];
				$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['luna']."value"][0]['type'] = 'bnode';
			}
			$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].$var['type'];
			$nodes['_:'.$var['type'].'-'.$lid][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
			// lunaTools::debug($nodes);
			return $nodes;
		}
	}
	// }}}
	// {{{ load_node()
	/**
	 * @access public
	 * @param mixed $node
	 * @param string $type1
	 * @param mixed $type2
	 * @return array
	 */
	public function load_node($node = false, $type1 = false, $type2 = false) { //lunaTools::debug($node);
		if (empty($node)) { return false; }
		if (is_object($node)) { $node = get_object_vars($node); } 
		if (!isset($node['nid']) || empty($node['nid'])) { return false; }
		if (empty($type1) && isset($node['type1'])) { $type1 = $node['type1']; }
		if (empty($type1)) { return false; }
		$nodes = array();
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].'nid'][0]['value'] = $node['nid'];
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].'lid'][0]['value'] = $node['lid'];
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['rdfs'].'label'][0]['value'] = _($node['lid']);
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['rdfs'].'label'][0]['lang'] = luna::$lang;
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['rdfs'].'label'][0]['type'] = 'literal';
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].'is_active'][0]['value'] = $node['is_active'];
		if (isset($node['parent_nid']) && $node['parent_nid']) { 
			$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['owl'].'isChildOf'][0]['value'] = $this->node_path.'/'.$node['parent_nid']; 
			$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['owl'].'isChildOf'][0]['type'] = 'uri';
		} else if ($node['parent_nid'] == 0 && $type1 == 'page') {
			$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['owl'].'isChildOf'][0]['value'] = $this->node_path.'/'.$node['nid']; 
			$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['owl'].'isChildOf'][0]['type'] = 'uri';
		}
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].$type1;
		$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
		if (is_string($type2) && isset($node['nid2'])) { 
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['luna'].'nid'][0]['value'] = $node['nid2'];
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['luna'].'lid'][0]['value'] = $node['lid2'];
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['rdfs'].'label'][0]['value'] = _($node['lid2']);
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['rdfs'].'label'][0]['lang'] = luna::$lang;
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['rdfs'].'label'][0]['type'] = 'literal';
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['luna'].'is_active'][0]['value'] = $node['is_active2'];
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].$type2;
			$nodes[$this->node_path.'/'.$node['nid2']][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
			$needle = array(
				'value' => $this->node_path.'/'.$node['nid2'],
				'type' => 'uri'
			);
			if (!isset($nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].$type2]) || !in_array($needle, $nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].$type2])) {
				$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].$type2][] = $needle;
			}
		} else if (is_array($type2)) { 
			$i = 1;
			foreach ($type2 as $typex) { 
				$nidx = 'nid'.($i + 1);
				$lidx = 'lid'.($i + 1);
				$is_activex = 'is_active'.($i + 1);
				if (isset($node[$nidx])) {
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['luna'].'nid'][0]['value'] = $node[$nidx];
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['luna'].'lid'][0]['value'] = $node[$lidx];
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['rdfs'].'label'][0]['value'] = _($node[$lidx]);
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['rdfs'].'label'][0]['lang'] = luna::$lang;
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['rdfs'].'label'][0]['type'] = 'literal';
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['luna'].'is_active'][0]['value'] = $node[$is_activex];
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['rdf'].'type'][0]['value'] = $this->conf['ns']['luna'].$typex;
					$nodes[$this->node_path.'/'.$node[$nidx]][$this->conf['ns']['rdf'].'type'][0]['type'] = 'uri';
					$needle = array(
						'value' => $this->node_path.'/'.$node[$nidx],
						'type' => 'uri'
					);
					if (!isset($nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].$typex]) || !in_array($needle, $nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].$typex])) {
						$nodes[$this->node_path.'/'.$node['nid']][$this->conf['ns']['luna'].$typex][] = $needle;
					}
				}
				$i++;
			}
		}
		return $nodes;
	}
	// }}}
	// {{{ load_nodes()
	/**
	 * @access public
	 * @param string $type1
	 * @param mixed $type2
	 * @param integer $nid
	 * @return mixed
	 */
	public function load_nodes($type1 = false, $type2 = false, $nid = false) { 
		if (empty($type1) || !is_string($type1)) { $type1 = false; }
		if (empty($type2) || (!is_string($type2) && !is_array($type2))) { $type2 = false; }
		$nid = intval($nid);
		if (empty($nid)) { $nid = false; }
		$sql = array(
			'select' => '',
			'from' => '',
			'where' => ''
		);
		$type1_sql = '';
		if (!empty($type1)) { $type1_sql = ' AND n.tid = (SELECT id FROM '.luna::get_ini('DBtables', 'CLASSES').' WHERE lid = '.lunaDB::quote("$type1").') '; }
		if (is_string($type2)) { 
			$i = 1;
			$sql['select'] .=  'n'.($i + 1).'.nid as nid'.($i + 1).', ';
			$sql['select'] .=  'n'.($i + 1).'.lid as lid'.($i + 1).', ';
			$sql['select'] .=  'n'.($i + 1).'.is_active as is_active'.($i + 1).', ';
			$sql['from'] .=  ', 
				'.luna::get_ini('DBtables', 'NODES_MAP').' map'.($i + 1).' 
			LEFT JOIN 
				'.luna::get_ini('DBtables', 'NODES').' n'.($i + 1).' 
			ON 
				map'.($i + 1).'.nid2 = n'.($i + 1).'.nid 
				';
			$sql['where'] .=  'AND map'.($i + 1).'.nid1 = n.nid AND n'.($i + 1).'.tid = (SELECT id FROM '.luna::get_ini('DBtables', 'CLASSES').' WHERE lid = '.lunaDB::quote("$type2").') ';
			if ($type2 == 'level') { $sql['where'] .= 'AND n2.nid IN ('.implode(',', luna::$session->user->levels).')'; }
		} else if (is_array($type2)) { 
			$i = 1;
			foreach ($type2 as $type2x) {
				$sql['select'] .=  'n'.($i + 1).'.nid as nid'.($i + 1).', ';
				$sql['select'] .=  'n'.($i + 1).'.lid as lid'.($i + 1).', ';
				$sql['select'] .=  'n'.($i + 1).'.is_active as is_active'.($i + 1).', ';
				$sql['from'] .=  ', '.luna::get_ini('DBtables', 'NODES_MAP').' map'.($i + 1).' LEFT JOIN '.luna::get_ini('DBtables', 'NODES').' n'.($i + 1).' ON map'.($i + 1).'.nid2 = n'.($i + 1).'.nid ';
				$sql['where'] .=  'AND map'.($i + 1).'.nid1 = n.nid AND n'.($i + 1).'.tid = (SELECT id FROM '.luna::get_ini('DBtables', 'CLASSES').' WHERE lid = '.lunaDB::quote("$type2x").') ';
				if ($type2x == 'level') { $sql['where'] .= 'AND n'.($i + 1).'.nid IN ('.implode(',', luna::$session->user->levels).')'; }
				$i++;
			}
		}
		if ($nid > 0) { $sql['where'] 	.=  ' AND n.nid = '.lunaDB::quote($nid).' '; }
		$query = '
			SELECT 
				DISTINCT n.nid,
				t.lid as type1,
				n.lid, 
				'.$sql['select'].'
				n.is_active,
				n.parent_nid,
				u.nid as user_nid,
				u.firstname,
				u.lastname,
				a.ntime
			FROM 
				'.luna::get_ini('DBtables', 'NODES').' n,
				'.luna::get_ini('DBtables', 'USERS').' u,
				'.luna::get_ini('DBtables', 'CLASSES').' t,
				'.luna::get_ini('DBtables', 'ACTIONS').' a'.$sql['from'].'
			WHERE 1 = 1
				'.$type1_sql.'
				AND a.nid = n.nid 
				AND t.id = n.tid
				AND u.nid = a.unid
				'.$sql['where'].'
			ORDER BY
				n.lid,
				a.ntime ASC
		';
		$res = lunaDB::query($query);
		$nodes = array();
		while ($row = $res->fetchRow()) { $nodes = $this->merge_nodes($nodes, $this->load_node($row, $type1, $type2)); }
		$res->free();
		if ($type1 == 'page' && !is_array($type2) && ($type2 == 'level' || empty($type2))) {
			$this->aliases = array();
			if (!$nodes = $this->calculate_aliases($nodes)) { throw new lunaException(_('Error: cannot calculate aliases.'), PEAR_LOG_CRIT); }
		}
		return $nodes;
	}
	// }}}
	// {{{ insert()
	/**
	 * @access public
	 * @param string $type1
	 * @param string $lid
	 * @param boolean $is_active
	 * @param integer $parent_nid
	 * @param integer $time
	 * @return integer
	 */
	public function insert($type1 = false, $lid = false, $is_active = false, $parent_nid = false, $time = false) {
		$db = lunaDB::get();
		if (empty($type1) || !is_string($type1)) { return false; }
		$time = intval($time);
		if (empty($time)) { $time = NOW; }
		if (empty($lid) || !is_string($lid)) { return false; }
		$is_active = ($is_active == true)? true : false;
		$parent_nid = intval($parent_nid);
		if (empty($parent_nid)) { $parent_nid = false; }
		$nextID = $db->nextID(luna::get_ini('DBtables', 'NODES'));
		if (PEAR::isError($nextID) || empty($nextID)) { throw new lunaException($nextID->getUserInfo(), PEAR_LOG_ERR); }
		$res = lunaDB::query('
			INSERT INTO
				'.luna::get_ini('DBtables', 'NODES').'
				(nid, lid, tid, is_active, parent_nid)
			VALUES
				(
					'.lunaDB::quote($nextID).', 
					'.lunaDB::quote($lid).',
					(
						SELECT 
							id 
						FROM 
							'.luna::get_ini('DBtables', 'CLASSES').' 
						WHERE 
							lid = '.lunaDB::quote($type1).'
					),
					'.lunaDB::quote($is_active).', 
					'.lunaDB::quote($parent_nid).'
				)
		');
		$this->insert_action($nextID, $time);
		return $nextID;
	}
	// }}}
	// {{{ update()
	/**
	 * @access public
	 * @param integer $nid
	 * @param string $lid
	 * @param boolean $is_active
	 * @param integer $parent_nid
	 * @return integer
	 */
	public function update($nid = false, $lid = false, $is_active = false, $parent_nid = false) { 
		if (empty($nid) || !is_integer(intval($nid))) { return false; }
		if (empty($lid) || !is_string($lid)) { return false; }
		$is_active = ($is_active == true)? true : false; 
		$parent_nid = intval($parent_nid);
		if (empty($parent_nid)) { $parent_nid = false; }
		$res = lunaDB::query('
			UPDATE
				'.luna::get_ini('DBtables', 'NODES').'
			SET
				lid = '.lunaDB::quote($lid).',
				is_active = '.lunaDB::quote($is_active).',
				parent_nid = '.lunaDB::quote($parent_nid).'
			WHERE
				nid = '.lunaDB::quote($nid).'
		');
		$this->insert_action($nid);
		return $nid;
	}
	// }}}
	// {{{ delete()
	/**
	 * @access public
	 * @param integer $nid
	 * @return boolean
	 */
	public function delete($nid = false) { 
		if (empty($nid) || !is_integer(intval($nid))) { return false; }
		$res = lunaDB::query('
			DELETE FROM
				'.luna::get_ini('DBtables', 'NODES').'
			WHERE 
				nid = '.lunaDB::quote($nid).'
		');
		$res = lunaDB::query('
			DELETE FROM
				'.luna::get_ini('DBtables', 'NODES_MAP').'
			WHERE 
				nid1 = '.lunaDB::quote($nid).'
				OR nid2 = '.lunaDB::quote($nid).'
		');
		$res = lunaDB::query('
			DELETE FROM
				'.luna::get_ini('DBtables', 'ACTIONS').'
			WHERE 
				nid = '.lunaDB::quote($nid).'
		');
		return true;
	}
	// }}}
	// {{{ link()
	/**
	 * @access public
	 * @param integer $nodeid1
	 * @param mixed $nodeid2
	 * @return boolean
	 */
	public function link($nodeid1 = false, $nodeid2 = false) {
		if (empty($nodeid1) || !is_integer(intval($nodeid1))) { return false; }
		if (empty($nodeid2)) { return false; }
		if (isset($nodeid2) && !empty($nodeid2)) {
			$sql = '';
			if (is_array($nodeid2)) {
				foreach ($nodeid2 as $nid) { 
					if (empty($nid) || !is_integer(intval($nid))) { return false; } 
					$sql .= '('.$nid.', '.$nodeid1.'), ('.$nodeid1.', '.$nid.'),'; 
				}
				$sql = substr($sql, 0, -1);
			} else {
				$nodeid2 = intval($nodeid2);
				if (empty($nodeid2)) { return false; } 
				$sql .= '('.$nodeid2.', '.$nodeid1.'), ('.$nodeid1.', '.$nodeid2.')';
			}
			$res = lunaDB::query('
				INSERT INTO
					'.luna::get_ini('DBtables', 'NODES_MAP').'
					(nid1, nid2)
				VALUES
					'.$sql.'
			');
		}
		return true;
	}
	// }}}
	// {{{ unlink()
	/**
	 * @access public
	 * @param integer $nodeid
	 * @param string $type1
	 * @return boolean
	 */
	public function unlink($nodeid = false, $type1 = false) {
		if (empty($nodeid) || !is_integer(intval($nodeid))) { return false; }
		if (empty($type1) || !is_string($type1)) { return false; }
		$res = lunaDB::query('
			DELETE FROM
				'.luna::get_ini('DBtables', 'NODES_MAP').'
			WHERE 
				(
					nid1 = '.lunaDB::quote($nodeid).'
					AND nid2 IN 
						(
							SELECT 
								nid 
							FROM 
								'.luna::get_ini('DBtables', 'NODES').'
							WHERE
								tid = 
									(
										SELECT id FROM '.luna::get_ini('DBtables', 'CLASSES').' WHERE lid = '.lunaDB::quote($type1).' LIMIT 1
									)
						)
				)
				OR
				(
					nid2 = '.lunaDB::quote($nodeid).'
					AND nid1 IN 
						(
							SELECT 
								nid 
							FROM 
								'.luna::get_ini('DBtables', 'NODES').'
							WHERE
								tid = 
									(
										SELECT id FROM '.luna::get_ini('DBtables', 'CLASSES').' WHERE lid = '.lunaDB::quote($type1).' LIMIT 1
									)
						)
				)
		');
		return true;
	}
	// }}}
	// {{{ insert_action()
	/**
	 * @access public
	 * @param integer $nid
	 * @param integer $time
	 * @return boolean
	 */
	public function insert_action($nid, $time = false) {
		$nid = intval($nid);
		if (empty($nid)) { return false; }
		$time = intval($time);
		if (empty($time)) { $time = NOW; }
		$res = lunaDB::query('
			INSERT INTO 
				'.luna::get_ini('DBtables', 'ACTIONS').' 
				(
					nid, 
					unid,
					ntime
				)
			VALUES
				(
					'.lunaDB::quote($nid).', 
					'.lunaDB::quote(luna::$session->user->nid).',
					'.lunaDB::quote($time).'
				)
		');
		return true;
	}
	// }}}
	// {{{ is_taken()
	/**
	 * @access public
	 * @param string $lid
	 * @param int $nid
	 * @return mixed
	 */
	public function lid_is_taken($lid = false, $nid = false) {
		if (empty($lid)) { return true; }
		$nid = intval($nid);
		$sql = '';
		if (!empty($nid)) { $sql = ' AND nid <> '.lunaDB::quote($nid); }
		$res = lunaDB::query('
			SELECT
				nid
			FROM 
				'.luna::get_ini('DBtables', 'NODES').' 
			WHERE
				lid = '.lunaDB::quote($lid).$sql.'
			LIMIT
				1
		');
		$row = $res->fetchRow();
		$res->free(); 
		if (empty($row)) { return false; } else { return $row->nid; }
	}
	// }}}
	// {{{ exists()
	/**
	 * @access public
	 * @param integer $nid
	 * @param string $class
	 * @return mixed
	 */
	public function exists($nid = false, $class = false) {
		if (empty($nid) || empty($class) || !is_string($class)) { return true; }
		$nid = intval($nid);
		$res = lunaDB::query('
			SELECT
				lid
			FROM 
				'.luna::get_ini('DBtables', 'NODES').' 
			WHERE 1 = 1
				AND nid = '.lunaDB::quote($nid).'
				AND tid = (
					SELECT 
						id 
					FROM 
						'.luna::get_ini('DBtables', 'CLASSES').' 
					WHERE 
						lid = '.lunaDB::quote("$class").'
				)
			LIMIT
				1
		');
		$row = $res->fetchRow();
		$res->free(); 
		if (empty($row)) { return false; } else { return $row->lid; }
		return true;
	}
	// }}}
	// {{{ calculate_aliases()
	/**
	 * @access public
	 * @param array $nodes
	 * @param integer $nid
	 * @return array
	 */
	public function calculate_aliases($nodes = false, $nid = false) { //lunaTools::debug(func_get_args());
		if (empty($nodes) || !is_array($nodes)) { return false; }
		// if no nid is given
		if ($nid < 1) { 
			// then walk throught the array and calculate the alias of each node
			foreach ($nodes as $node) { 
				if (empty($node) || !is_array($node)) { return false; } 
				if (!$nodes = $this->calculate_aliases($nodes, $node[$this->conf['ns']['luna'].'nid'][0]['value'])) { return false; }
			}
		} else { 
			// We have a nid, check if the node exists
			if (!isset($nodes[$this->node_path.'/'.$nid])) { return false; }
			// store the node’s uri, we’ll need it later
			$node_uri = $this->node_path.'/'.$nid;
			// do the same for its parent
			$parent_uri = $nodes[$node_uri][$this->conf['ns']['owl'].'isChildOf'][0]['value']; 
			// and also grab its nid, we might need it
			$parent_nid = $nodes[$parent_uri][$this->conf['ns']['luna'].'nid'][0]['value'];
			// if the node’s uri is the same as its parent’s, then we just hit the root page.
			if ($parent_uri == $node_uri) { 
				// that means: empty alias
				$nodes[$node_uri][$this->conf['ns']['luna'].'alias'][0]['value'] = '';
				// store "root"
				$this->aliases["root"][$this->conf['ns']['luna'].'nid'][0]['value'] = $nid;
			// else, if the parent exists
			} else if (isset($nodes[$parent_uri])) { 
				// grab the literal identifier of the parent
				$parent_lid = $nodes[$parent_uri][$this->conf['ns']['luna'].'lid'][0]['value'];
				// check if the parent is the root page
				if ($parent_lid == 'root') {
					// if yes, then the alias we’re looking for is just the literal identifier of the current node. 
					$nodes[$node_uri][$this->conf['ns']['luna'].'alias'][0]['value'] = $nodes[$node_uri][$this->conf['ns']['luna'].'lid'][0]['value'];
					// store it
					$this->aliases[$nodes[$node_uri][$this->conf['ns']['luna'].'lid'][0]['value']][$this->conf['ns']['luna'].'nid'][0]['value'] = $nid;
					// return everything
					return $nodes;
				} else {
					// if the parent is not the root page, then what we need to do first here is calculate the parent’s alias
					if (!$nodes = $this->calculate_aliases($nodes, $parent_nid)) { return false; }
					$parent_alias = $nodes[$parent_uri][$this->conf['ns']['luna'].'alias'][0]['value'];
					if (!empty($parent_alias)) { $parent_alias .= '/'; }
					$nodes[$node_uri][$this->conf['ns']['luna'].'alias'][0]['value'] = $parent_alias.$nodes[$node_uri][$this->conf['ns']['luna'].'lid'][0]['value'];
					$this->aliases[$parent_alias.$nodes[$node_uri][$this->conf['ns']['luna'].'lid'][0]['value']][$this->conf['ns']['luna'].'nid'][0]['value'] = $nid;
				}
			}
		}
		return $nodes;
	}
	// }}}
	// {{{ load_xsl
	/**
	 * @access private
	 * @param string $file
	 * @return boolean
	 */
	private function load_xsl($file = false) {
		if (empty($file) || !is_string($file) || !file_exists($file)) { return false; }
		$this->xsl =& new DomDocument;
		$this->xsl->load($file);
		$this->xsl->preserveWhiteSpace = FALSE;
		return true;
	}
	// }}}
	// {{{ transform()
	/**
	 * @param string $xslfile
	 * @return mixed
	 */
	public function transform($xslfile = false) {
		$code_str = md5(serialize(array($this->conf, $this->index)));
		if (luna::$cache) { $cache_obj =& new Cache_Lite(array('cacheDir' => CACHE_PATH, 'lifetime' => luna::$cache_timeout)); }
		if (luna::$cache && ($cache_str = $cache_obj->get($code_str))) {
			$res = unserialize($cache_str);
		} else { 
			if (!$this->load_xsl($xslfile)) { return false; }
			include_once('arc/ARC2.php');
			$ser = ARC2::getRDFXMLSerializer($this->conf);
			$this->dom = new DomDocument; 
			$this->dom->loadXML($ser->getSerializedIndex($this->index)); 
			$this->xslprocessor =& new XsltProcessor();
			$this->xslprocessor->importStyleSheet($this->xsl);
			$res = $this->xslprocessor->transformToXML($this->dom);
			if (luna::$cache) { $cache_obj->save(serialize($res)); }
		}
		return $res;
	}
	// }}}
}
// }}}
?>