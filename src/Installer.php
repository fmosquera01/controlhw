<?php

namespace GlpiPlugin\Controlhw;

final class Installer
{
    public static function install(): bool
    {
        global $DB;

        $queries = [
            "CREATE TABLE IF NOT EXISTS glpi_plugin_controlhw_snapshots (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                computers_id INT UNSIGNED NOT NULL,
                inventory_hash CHAR(64) NOT NULL,
                components_count INT UNSIGNED NOT NULL DEFAULT 0,
                is_complete TINYINT NOT NULL DEFAULT 1,
                date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY computers_id (computers_id),
                KEY inventory_hash (inventory_hash),
                KEY date_creation (date_creation)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS glpi_plugin_controlhw_components (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                snapshots_id INT UNSIGNED NOT NULL,
                component_type VARCHAR(16) NOT NULL,
                identity_key VARCHAR(191) NOT NULL,
                serial VARCHAR(255) DEFAULT NULL,
                manufacturer VARCHAR(255) DEFAULT NULL,
                model VARCHAR(255) DEFAULT NULL,
                capacity VARCHAR(80) DEFAULT NULL,
                slot VARCHAR(255) DEFAULT NULL,
                details_json LONGTEXT DEFAULT NULL,
                PRIMARY KEY (id),
                KEY snapshots_id (snapshots_id),
                KEY identity_key (identity_key),
                KEY component_type (component_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS glpi_plugin_controlhw_alerts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                computers_id INT UNSIGNED NOT NULL,
                snapshots_id INT UNSIGNED NOT NULL,
                component_type VARCHAR(16) NOT NULL,
                change_type VARCHAR(16) NOT NULL,
                old_data LONGTEXT DEFAULT NULL,
                new_data LONGTEXT DEFAULT NULL,
                event_hash CHAR(64) NOT NULL,
                notification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notification_error TEXT DEFAULT NULL,
                date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY event_hash (event_hash),
                KEY computers_id (computers_id),
                KEY snapshots_id (snapshots_id),
                KEY notification_status (notification_status),
                KEY date_creation (date_creation)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $query) {
            if (!$DB->doQuery($query)) {
                return false;
            }
        }

        return true;
    }
}
