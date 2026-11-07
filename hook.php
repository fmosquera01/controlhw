<?php

/**
 * Control HW plugin lifecycle.
 *
 * @copyright 2026
 * @license   GPL-3.0-or-later
 */

function plugin_controlhw_install(): bool
{
    // Database migrations will be added with the first functional release.
    return true;
}

function plugin_controlhw_uninstall(): bool
{
    // Uninstallation will preserve audit data unless an explicit,
    // documented data-removal option is implemented.
    return true;
}
