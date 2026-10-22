CREATE TABLE IF NOT EXISTS `migration` (
  `version` smallint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `applied` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `bookmark` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `hlink` varchar(2048) COLLATE utf8mb4_bin NOT NULL,
  `text` varchar(1024) NOT NULL,
  `user` smallint unsigned NOT NULL,
  `visibility` enum('public','private') NOT NULL DEFAULT 'private',
  `add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `bookmark_tag` (
  `bookmark` smallint unsigned NOT NULL,
  `tag` smallint unsigned NOT NULL,
  PRIMARY KEY (`bookmark`,`tag`),
  UNIQUE KEY `tag_bookmark` (`tag`,`bookmark`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tag` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `user` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `email` varchar(255) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('owner','admin','user') NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
