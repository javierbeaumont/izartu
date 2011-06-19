-- Server version: 5.1.54

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `izartu`
--

-- --------------------------------------------------------

--
-- Table structure for table `data`
--

CREATE TABLE IF NOT EXISTS `data` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `lang` smallint(5) unsigned NOT NULL,
  `type` smallint(5) unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `hlink` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `hlang` smallint(5) unsigned NOT NULL,
  `htype` smallint(5) unsigned NOT NULL,
  `text` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `user` smallint(5) unsigned NOT NULL,
  `add` datetime NOT NULL,
  `mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `data_tag`
--

CREATE TABLE IF NOT EXISTS `data_tag` (
  `data` smallint(5) unsigned NOT NULL,
  `tag` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`data`,`tag`),
  UNIQUE KEY `tag_data` (`tag`,`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tag`
--

CREATE TABLE IF NOT EXISTS `tag` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `lang` tinyint(3) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=3 ;
