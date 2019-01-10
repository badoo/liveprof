CREATE TABLE IF NOT EXISTS `details` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app` varchar(32) DEFAULT NULL,
  `label` varchar(64) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `perfdata` mediumblob,
  PRIMARY KEY (`id`),
  KEY `timestamp` (`timestamp`),
  KEY `app` (`app`),
  KEY `label` (`label`),
  KEY `timestamp_label_idx` (`timestamp`,`label`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
