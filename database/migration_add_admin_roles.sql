-- SmartVote Database Migration
-- Add role-based access control with super admin capabilities
-- Date: May 6, 2026 
-- Super admins can dismiss original admins and manage user access

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS=0;

-- Add 'role' column to admins table to support role-based access control
ALTER TABLE `admins` 
ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'admin' AFTER `fullname`,
ADD COLUMN `is_active` BOOLEAN NOT NULL DEFAULT 1 AFTER `role`,
ADD COLUMN `dismissed_by` INT NULL AFTER `is_active`,
ADD COLUMN `dismissed_at` TIMESTAMP NULL DEFAULT NULL AFTER `dismissed_by`,
ADD COLUMN `dismissal_reason` TEXT NULL AFTER `dismissed_at`,
ADD INDEX `idx_role` (`role`),
ADD INDEX `idx_is_active` (`is_active`),
ADD INDEX `idx_dismissed_by` (`dismissed_by`);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- Update the system administrator (ID: 1) to be a super_admin
UPDATE `admins` 
SET `role` = 'super_admin' 
WHERE `id` = 1;

-- Create a trigger to log admin dismissals
DELIMITER $$

CREATE TRIGGER `log_admin_dismissal` 
BEFORE UPDATE ON `admins` 
FOR EACH ROW 
BEGIN
    IF OLD.is_active = 1 AND NEW.is_active = 0 THEN
        IF NEW.dismissed_at IS NULL THEN
            SET NEW.dismissed_at = NOW();
        END IF;
    END IF;
END$$

DELIMITER ;

COMMIT;
