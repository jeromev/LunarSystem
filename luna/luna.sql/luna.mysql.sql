DROP TABLE IF EXISTS `luna_actions`;
CREATE TABLE `luna_actions` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `nid` int(11) unsigned NOT NULL default '0',
  `unid` int(11) unsigned NOT NULL default '0',
  `ntime` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `nid` (`nid`),
  KEY `ntime` (`ntime`),
  KEY `unid` (`unid`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_config`;
CREATE TABLE `luna_config` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `value` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_logs`;
CREATE TABLE `luna_logs` (
  `id` int(11) NOT NULL auto_increment,
  `logtime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ident` varchar(16) NOT NULL default '',
  `priority` int(11) NOT NULL default '0',
  `message` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_nodes`;
CREATE TABLE `luna_nodes` (
  `nid` int(11) unsigned NOT NULL auto_increment,
  `lid` varchar(255) default '',
  `tid` int(11) unsigned NOT NULL default '0',
  `parent_nid` int(11) unsigned default NULL,
  `is_active` tinyint(1) NOT NULL default '1',
  PRIMARY KEY  (`nid`),
  UNIQUE KEY `lid_2` (`lid`),
  KEY `is_active` (`is_active`),
  KEY `parent_nid` (`parent_nid`),
  KEY `tid` (`tid`),
  FULLTEXT KEY `lid` (`lid`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_nodes_map`;
CREATE TABLE `luna_nodes_map` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `nid1` int(11) unsigned NOT NULL default '0',
  `nid2` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `nid1` (`nid1`),
  KEY `nid2` (`nid2`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_sessions`;
CREATE TABLE `luna_sessions` (
  `session_id` varchar(32) NOT NULL default '',
  `session_user_nid` int(11) NOT NULL default '0',
  `session_start` int(11) NOT NULL default '0',
  `session_time` int(11) NOT NULL default '0',
  `session_ip` varchar(8) NOT NULL default '0',
  `session_url` varchar(255) default NULL,
  `session_logged_in` tinyint(1) NOT NULL default '0',
  `session_lang` varchar(5) default NULL,
  `session_useragent` varchar(255) default NULL,
  PRIMARY KEY  (`session_id`),
  KEY `session_user_nid` (`session_user_nid`),
  KEY `session_time` (`session_time`),
  KEY `session_start` (`session_start`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_texts`;
CREATE TABLE `luna_texts` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `nid` int(11) unsigned NOT NULL default '0',
  `title` tinytext,
  `lang` char(2) default NULL,
  `content_html` longtext,
  PRIMARY KEY  (`id`),
  KEY `nid` (`nid`),
  FULLTEXT KEY `content` (`content_html`,`title`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_types`;
CREATE TABLE `luna_types` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `lid` varchar(255) default NULL,
  `page_nid` int(11) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `lid` (`lid`),
  KEY `page_nid` (`page_nid`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_users`;
CREATE TABLE `luna_users` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `nid` int(11) unsigned NOT NULL default '0',
  `firstname` varchar(255) default NULL,
  `lastname` varchar(255) default NULL,
  `password` varchar(32) default NULL,
  `regis_time` int(11) default NULL,
  `last_time` int(11) default NULL,
  `last_url` varchar(255) default NULL,
  `newpasswd` varchar(32) default NULL,
  `login_attempts` tinyint(1) NOT NULL default '0',
  `lang` varchar(5) default NULL,
  PRIMARY KEY  (`id`),
  KEY `last_time` (`last_time`),
  KEY `nid` (`nid`),
  FULLTEXT KEY `firstname` (`firstname`,`lastname`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `luna_nodes_seq`;
CREATE TABLE `luna_nodes_seq` (
  `sequence` int(10) unsigned NOT NULL auto_increment,
  PRIMARY KEY  (`sequence`)
) ENGINE=MyISAM;

INSERT INTO `luna_config` (`name`, `value`) VALUES 
('disable', '0'),
('keywords', ''),
('session_length', '604800'),
('startdate', ''),
('root_module', NULL),
('disable_txt', '<p>This website is temporarily closed. Please come back later.</p>'),
('langs', 'en, fr'),
('cache_timeout', '3600'),
('author', 'Admin'),
('sitename', 'My lunar Website'),
('version', '1'),
('general_email', 'admin@mywebsite.org'),
('site_desc', 'This is the description of my lunar website.'),
('timezone', '1');

INSERT INTO `luna_nodes` (`nid`, `lid`, `tid`, `parent_nid`, `is_active`) VALUES 
(1, 'admin@lunarsystem.local', 1, NULL, 1),
(2, 'guest', 1, NULL, 0),
(3, 'group_default', 2, NULL, 1),
(4, 'group_admin', 2, 0, 1),
(5, 'group_edition', 2, NULL, 1),
(6, 'level_public', 3, NULL, 1),
(7, 'level_admin', 3, NULL, 1),
(8, 'level_edition', 3, NULL, 1),
(9, 'root', 5, 0, 1),
(10, 'admin', 5, 9, 1),
(11, 'admin_groups', 5, 10, 1),
(12, 'admin_levels', 5, 10, 1),
(13, 'admin_mods', 5, 10, 1),
(14, 'admin_pages', 5, 10, 1),
(15, 'admin_users', 5, 10, 1),
(16, 'edition', 5, 9, 1),
(17, 'edit_texts', 5, 16, 1),
(18, 'journal', 5, 10, 1),
(19, 'login', 5, 9, 1),
(20, 'logout', 5, 9, 1),
(21, 'mod_admin', 6, NULL, 1),
(22, 'mod_admin_groups', 6, NULL, 1),
(23, 'mod_admin_levels', 6, NULL, 1),
(24, 'mod_admin_mods', 6, 0, 1),
(25, 'mod_admin_pages', 6, NULL, 1),
(26, 'mod_admin_users', 6, NULL, 1),
(27, 'mod_edit_texts', 6, NULL, 1),
(28, 'mod_journal', 6, NULL, 1),
(29, 'mod_log', 6, NULL, 1),
(30, 'mod_online_users', 6, NULL, 1),
(31, 'mod_node', 6, NULL, 1),
(32, 'node', 5, 9, 1);

INSERT INTO `luna_nodes_seq` (`sequence`) VALUES 
(33);

INSERT INTO `luna_nodes_map` (`nid1`, `nid2`) VALUES 
(1, 3), (3, 1),
(1, 4), (4, 1),
(1, 5), (5, 1),
(2, 3), (3, 2),
(3, 6), (6, 3),
(4, 6), (6, 4),
(4, 7), (7, 4),
(4, 8), (8, 4),
(5, 6), (6, 5),
(5, 8), (8, 5),
(9, 6), (6, 9),
(10, 7), (7, 10),
(11, 7), (7, 11),
(12, 7), (7, 12),
(13, 7), (7, 13),
(14, 7), (7, 14),
(15, 7), (7, 15),
(16, 8), (8, 16),
(17, 8), (8, 17),
(18, 7), (7, 18),
(19, 6), (6, 19),
(20, 6), (6, 20),
(10, 7), (7, 10),
(21, 7), (7, 21),
(22, 7), (7, 22),
(23, 7), (7, 23),
(24, 7), (7, 24),
(25, 7), (7, 25),
(26, 7), (7, 26),
(27, 8), (8, 27),
(28, 7), (7, 28),
(29, 6), (6, 29),
(30, 7), (7, 30),
(10, 21), (21, 10),
(10, 22), (22, 10),
(10, 23), (23, 10),
(10, 30), (30, 10),
(11, 22), (22, 11),
(12, 23), (23, 12),
(13, 24), (24, 13),
(14, 25), (25, 14),
(15, 26), (26, 15),
(17, 27), (27, 17),
(18, 28), (28, 18),
(19, 29), (29, 19),
(20, 29), (29, 20),
(31, 32), (32, 31),
(31, 6), (6, 31),
(6, 32), (32, 6);

INSERT INTO `luna_actions` (`nid`, `unid`, `ntime`) VALUES 
(1, 1, 0),
(2, 1, 0),
(3, 1, 0),
(4, 1, 0),
(5, 1, 0),
(6, 1, 0),
(7, 1, 0),
(8, 1, 0),
(9, 1, 0),
(10, 1, 0),
(11, 1, 0),
(12, 1, 0),
(13, 1, 0),
(14, 1, 0),
(15, 1, 0),
(16, 1, 0),
(17, 1, 0),
(18, 1, 0),
(19, 1, 0),
(20, 1, 0),
(21, 1, 0),
(22, 1, 0),
(23, 1, 0),
(24, 1, 0),
(25, 1, 0),
(26, 1, 0),
(27, 1, 0),
(28, 1, 0),
(29, 1, 0),
(30, 1, 0),
(31, 1, 0),
(32, 1, 0);

INSERT INTO `luna_types` (`id`, `lid`, `page_nid`) VALUES 
(1, 'user', 15),
(2, 'group', 11),
(3, 'level', 12),
(4, 'text', 17),
(5, 'page', 14),
(6, 'mod', 13);

INSERT INTO `luna_users` (`id`, `nid`, `firstname`, `lastname`, `password`, `regis_time`, `last_time`, `last_url`, `newpasswd`, `login_attempts`, `lang`) VALUES 
(1, 1, 'Admin', 'Luna', 'ba8a48b0e34226a2992d871c65600a7c', 0, 0, NULL, '', 0, ''),
(2, 2, 'guest', '', '8ff953dd97c4405234a04291dee39e0b', 0, 0, NULL, '', 0, '');

OPTIMIZE TABLE `luna_actions` , `luna_config` , `luna_logs` , `luna_nodes` , `luna_nodes_map` , `luna_nodes_seq` , `luna_sessions` , `luna_texts` , `luna_types` , `luna_users`;