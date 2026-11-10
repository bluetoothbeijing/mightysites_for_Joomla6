CREATE TABLE IF NOT EXISTS `#__mightysites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL DEFAULT '',
  `aliases` text NULL,
  `db` varchar(255) NOT NULL DEFAULT '',
  `dbprefix` varchar(255) NOT NULL DEFAULT '',
  `params` text NULL,
  `checked_out` int unsigned NOT NULL DEFAULT 0,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `type` tinyint unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1;
