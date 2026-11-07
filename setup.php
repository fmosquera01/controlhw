<?php

/**
 * Control HW - Hardware Audit for GLPI 11
 *
 * @copyright 2026
 * @license   GPL-3.0-or-later
 */

function plugin_version_controlhw(): array
{
    return [
        'name'         => 'Control HW - Auditoría de Hardware',
        'version'      => '0.1.0',
        'author'       => 'Songa - IT',
        'license'      => 'GPL-3.0-or-later',
        'requirements' => [
            'glpi' => [
                'min' => '11.0.0',
            ],
        ],
    ];
}

/**
 * Initialize plugin hooks.
 *
 * Inventory hooks will be registered after validating their execution
 * order against the target GLPI 11 inventory import process.
 */
function plugin_init_controlhw(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['controlhw'] = true;
}
