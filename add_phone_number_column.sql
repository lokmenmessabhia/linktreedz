-- Add phone_number column to link_pages table
ALTER TABLE link_pages ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL AFTER profile_name;
