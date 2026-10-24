-- Linkydz Card Management System - Database Migration
-- Run this script to add card types and enhanced token management

-- Create card_types table
CREATE TABLE IF NOT EXISTS `card_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `width_cm` decimal(5,2) NOT NULL,
  `height_cm` decimal(5,2) NOT NULL,
  `template_image` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default card types
INSERT INTO `card_types` (`name`, `display_name`, `width_cm`, `height_cm`, `template_image`, `description`) VALUES
('all_links', 'All Links Card', 13.00, 18.00, 'all links.png', 'Standard all-in-one links card'),
('facebook', 'Facebook Card', 15.00, 15.00, 'facebookcard.png', 'Direct Facebook redirect card'),
('instagram', 'Instagram Card', 15.00, 15.00, 'instagramcard.png', 'Direct Instagram redirect card'),
('tiktok', 'TikTok Card', 15.00, 15.00, 'tiktokcard.png', 'Direct TikTok redirect card'),
('review', 'Review Card', 15.00, 15.00, 'reviewusgoogle.png', 'Direct Google review redirect card');

-- Add new columns to tokens table
ALTER TABLE `tokens` 
ADD COLUMN `card_type_id` int(11) DEFAULT 1 AFTER `is_active`,
ADD COLUMN `parent_token_id` int(11) DEFAULT NULL AFTER `card_type_id`,
ADD COLUMN `redirect_url` varchar(500) DEFAULT NULL AFTER `parent_token_id`,
ADD COLUMN `activation_date` timestamp NULL DEFAULT NULL AFTER `redirect_url`,
ADD COLUMN `sale_date` date NULL DEFAULT NULL AFTER `activation_date`,
ADD CONSTRAINT `fk_tokens_card_type` FOREIGN KEY (`card_type_id`) REFERENCES `card_types` (`id`),
ADD CONSTRAINT `fk_tokens_parent` FOREIGN KEY (`parent_token_id`) REFERENCES `tokens` (`id`) ON DELETE CASCADE;

-- Add indexes for better performance
ALTER TABLE `tokens`
ADD KEY `idx_card_type` (`card_type_id`),
ADD KEY `idx_parent_token` (`parent_token_id`),
ADD KEY `idx_activation_date` (`activation_date`);

-- Update qr_cards table to reference card type
ALTER TABLE `qr_cards`
ADD COLUMN `card_type_id` int(11) DEFAULT 1 AFTER `template_name`,
ADD CONSTRAINT `fk_qr_cards_card_type` FOREIGN KEY (`card_type_id`) REFERENCES `card_types` (`id`);

-- Update existing tokens to use 'all_links' card type (id = 1)
UPDATE `tokens` SET `card_type_id` = 1 WHERE `card_type_id` IS NULL;

-- Update existing qr_cards to use 'all_links' card type
UPDATE `qr_cards` SET `card_type_id` = 1 WHERE `card_type_id` IS NULL;

COMMIT;
