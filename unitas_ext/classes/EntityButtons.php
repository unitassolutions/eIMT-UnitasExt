<?php
// Main class for Entity Buttons

class PluginUnitasExtEntityButtons
{
    public static $table_name = 'app_ext_unitas_entity_buttons';
    
    public static function tableExists()
    {
        $sql = "SHOW TABLES LIKE '" . self::$table_name . "'";
        $result = db_query($sql);
        return (db_num_rows($result) > 0);
    }
    
    public static function createTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . self::$table_name . "` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `entity_id` INT(11) NOT NULL,
            `button_title` VARCHAR(255) NOT NULL,
            `button_type` VARCHAR(20) NOT NULL DEFAULT 'url',
            `report_id` INT(11) NULL,
            `external_url` TEXT NULL,
            `button_icon` VARCHAR(50) NOT NULL DEFAULT 'fa-external-link',
            `button_color` VARCHAR(50) NOT NULL DEFAULT 'btn-default',
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_entity_id` (`entity_id`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        return db_query($sql);
    }
    
    public static function install()
    {
        if(!self::tableExists()) {
            return self::createTable();
        }
        return true;
    }
}