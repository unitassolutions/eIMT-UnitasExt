<?php
// UNITAS Extension - Installation

class plugin_unitas_ext_install
{
    public static function run()
    {
        // Create table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS `app_ext_unitas_entity_buttons` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `entity_id` INT(11) NOT NULL,
            `button_title` VARCHAR(255) NOT NULL,
            `button_type` VARCHAR(20) NOT NULL DEFAULT 'url',
            `report_id` INT(11) NULL,
            `external_url` TEXT NULL,
            `button_icon` VARCHAR(50) NULL, -- Changed to NULL for optional
            `button_color` VARCHAR(50) NOT NULL DEFAULT 'btn-primary', -- Changed to btn-primary
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_entity_id` (`entity_id`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        db_query($sql);
        
        // Update existing records to use blue color
        $update_sql = "UPDATE app_ext_unitas_entity_buttons SET button_color = 'btn-primary' WHERE button_color != 'btn-primary'";
        db_query($update_sql);
        
        return true;
    }
}

// Auto-run installation
plugin_unitas_ext_install::run();