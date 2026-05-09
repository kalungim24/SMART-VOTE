-- SmartVote Database Migration
-- Add role-based access control to admins table
-- Date: May 6, 2026 
-- Going to find out whether i should have used create table instead of alter


-- Add 'role' column to admins table to support role-based access control
ALTER TABLE `admins` 
ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'admin' AFTER `fullname`,
ADD INDEX `idx_role` ("role");

-- Update the system administrator (ID: 1) to be a super_admin
UPDATE `admins` 
SET `role` = 'super_admin' 
WHERE `id` = 1;

-- Optional: Create a comment/trigger to log role changes
-- ALTER TABLE `admins` ADD COLUMN `role_updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

COMMIT;
