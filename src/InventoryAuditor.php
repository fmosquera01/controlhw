<?php

namespace GlpiPlugin\Controlhw;

use Agent;
use Computer;
use Glpi\Plugin\Hooks;
use Throwable;
use Toolbox;

final class InventoryAuditor
{
    public static function onInventory($rawData): void
    {
        try {
            if (!is_object($rawData) || empty($rawData->deviceid)) {
                return;
            }

            // A partial inventory can omit existing components. Ignore it until a complete
            // inventory arrives so it can never create false removal alerts.
            if (self::containsPartialMarker($rawData)) {
                return;
            }

            $agent = new Agent();
            if (!$agent->getFromDBByCrit(['deviceid' => (string) $rawData->deviceid])) {
                return;
            }

            if (($agent->fields['itemtype'] ?? '') !== Computer::class
                && ($agent->fields['itemtype'] ?? '') !== 'Computer'
            ) {
                return;
            }

            $computerId = (int) ($agent->fields['items_id'] ?? 0);
            if ($computerId <= 0) {
                return;
            }

            $computer = new Computer();
            if (!$computer->getFromDB($computerId)) {
                return;
            }

            $components = ComponentRepository::collect($computerId);
            SnapshotService::capture(
                $computerId,
                (string) ($computer->fields['name'] ?? ('Computer #' . $computerId)),
                $components
            );
        } catch (Throwable $exception) {
            // Never interrupt or roll back a successful GLPI inventory.
            Toolbox::logError('Control HW inventory audit failed: ' . $exception->getMessage());
        }
    }

    private static function containsPartialMarker($value, int $depth = 0): bool
    {
        if ($depth > 12) {
            return false;
        }

        if (is_object($value)) {
            if (($value->partial ?? false) === true || ($value->partial ?? null) === 1) {
                return true;
            }
            foreach (get_object_vars($value) as $child) {
                if (self::containsPartialMarker($child, $depth + 1)) {
                    return true;
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $child) {
                if (self::containsPartialMarker($child, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }
}
