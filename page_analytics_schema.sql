-- Page view analytics table for tracking token/linkpage visits
CREATE TABLE IF NOT EXISTS `page_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_page_id` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_link_page_id` (`link_page_id`),
  KEY `idx_viewed_at` (`viewed_at`),
  CONSTRAINT `page_analytics_ibfk_1` FOREIGN KEY (`link_page_id`) REFERENCES `link_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
