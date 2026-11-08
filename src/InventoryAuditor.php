<?php

namespace GlpiPlugin\Controlhw;

final class InventoryAuditor
{
    /**
     * GLPI POST_INVENTORY passes an array with the inventory object in "data".
     * Keep accepting a direct object too, for compatibility with older integrations.
     */
    public static function onInventory($params): void
    {
        try {
            $rawData = self::extractInventoryData($params);
            if ($rawData === null) {
                return;
            }

            // A partial inventory can omit existing components. Ignore it until a complete
            // inventory arrives so it can never create false removal alerts.
            if (self::containsPartialMarker($rawData)) {
                return;
            }

            $deviceId = self::readValue($rawData, 'deviceid');
            if (!is_scalar($deviceId) || trim((string) $deviceId) === '') {
                return;
            }

            $agent = new \Agent();
            if (!$agent->getFromDBByCrit(['deviceid' => (string) $deviceId])) {
                return;
            }

            $agentItemtype = (string) ($agent->fields['itemtype'] ?? '');
            if ($agentItemtype !== \Computer::class && $agentItemtype !== 'Computer') {
                return;
            }

            $computerId = (int) ($agent->fields['items_id'] ?? 0);
            if ($computerId <= 0) {
                return;
            }

            $computer = new \Computer();
            if (!$computer->getFromDB($computerId)) {
                return;
            }

            $computerName = (string) ($computer->fields['name'] ?? ('Computer #' . $computerId));
            SnapshotService::retryPendingNotifications($computerId, $computerName);
            $components = ComponentRepository::collect($computerId);
            SnapshotService::capture($computerId, $computerName, $components);
        } catch (\Throwable $exception) {
            // Never interrupt or roll back a successful GLPI inventory.
            \Toolbox::logError('Control HW inventory audit failed: ' . $exception->getMessage());
        }
    }

    private static function extractInventoryData($params)
    {
        if (is_array($params)) {
            return $params['data'] ?? null;
        }

        if (is_object($params) && isset($params->data)) {
            return $params->data;
        }

        return is_object($params) ? $params : null;
    }

    private static function readValue($value, string $key)
    {
        if (is_array($value)) {
            return $value[$key] ?? null;
        }

        if (is_object($value)) {
            return $value->{$key} ?? null;
        }

        return null;
    }

    private static function containsPartialMarker($value, int $depth = 0): bool
    {
        if ($depth > 16) {
            return false;
        }

        $partial = self::readValue($value, 'partial');
        if ($partial === true || $partial === 1 || $partial === '1') {
            return true;
        }

        if (is_object($value)) {
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
