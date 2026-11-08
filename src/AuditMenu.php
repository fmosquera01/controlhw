<?php

namespace GlpiPlugin\\Controlhw;

use Session;

/**
 * GLPI menu entry for the hardware audit console.
 */
final class AuditMenu
{
    public static function getMenuName($nb = 0): string
    {
        return __('Control HW - Auditoría', 'controlhw');
    }

    public static function getIcon(): string
    {
        return 'ti ti-cpu';
    }

    public static function canView(): bool
    {
        return Session::haveRight('computer', READ);
    }

    public static function getMenuContent(): array
    {
        if (!self::canView()) {
            return [];
        }

        $url = '/plugins/controlhw/front/audit.php';

        return [
            'title' => self::getMenuName(),
            'page'  => $url,
            'icon'  => self::getIcon(),
            'links' => [
                'search' => $url,
            ],
        ];
    }
}
