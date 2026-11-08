<?php

use GlpiPlugin\Controlhw\Installer;

function plugin_controlhw_install(): bool
{
    return Installer::install();
}

function plugin_controlhw_upgrade($oldVersion): bool
{
    return Installer::install();
}

/**
 * Audit records are intentionally retained on uninstall.
 */
function plugin_controlhw_uninstall(): bool
{
    return true;
}
