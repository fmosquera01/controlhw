<?php
/**
 * Control HW - Auditoría de Hardware para GLPI 11.
 */

use GlpiPlugin\Controlhw\AuditMenu;

define('PLUGIN_CONTROLHW_VERSION', '0.3.2');
define('PLUGIN_CONTROLHW_MIN_GLPI_VERSION', '11.0.11');
define('PLUGIN_CONTROLHW_MAX_GLPI_VERSION', '11.1.0');

function plugin_version_controlhw(): array
{
    return [
        'name'         => 'Control HW - Auditoría de Hardware',
        'version'      => PLUGIN_CONTROLHW_VERSION,
        'author'       => 'Songa - IT',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/fmosquera01/controlhw',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CONTROLHW_MIN_GLPI_VERSION,
                'max' => PLUGIN_CONTROLHW_MAX_GLPI_VERSION,
            ],
            'php' => ['min' => '8.2'],
        ],
    ];
}

function plugin_controlhw_check_prerequisites(): bool
{
    if (!defined('GLPI_VERSION')) {
        return false;
    }

    return version_compare(GLPI_VERSION, PLUGIN_CONTROLHW_MIN_GLPI_VERSION, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_CONTROLHW_MAX_GLPI_VERSION, '<')
        && version_compare(PHP_VERSION, '8.2.0', '>=');
}

function plugin_controlhw_check_config($verbose = false): bool
{
    return true;
}

function plugin_init_controlhw(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['controlhw'] = true;
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::POST_INVENTORY]['controlhw'] = [
        \GlpiPlugin\Controlhw\InventoryAuditor::class,
        'onInventory',
    ];

    // Register the menu unconditionally. Visibility is checked by AuditMenu::getMenuContent()
    // after GLPI has initialized the active user's session and rights.
    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::MENU_TOADD]['controlhw'] = [
        'tools' => AuditMenu::class,
    ];
}
