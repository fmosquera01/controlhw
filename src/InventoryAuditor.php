<?php

namespace GlpiPlugin\Controlhw;

final class InventoryAuditor
{
    /**
     * GLPI 11 POST_INVENTORY passes the cleaned raw inventory object directly.
     * This hook is called only after GLPI has successfully processed the inventory.
     */
    public static function onInventory($params): void
    {
        $rawData = self::extractInventoryData($params);
        if ($rawData === null) {
            self::log(
                'POST_INVENTORY received an empty payload. Callback parameter type: '
                . get_debug_type($params)
                . self::describePayloadKeys($params)
            );
            return;
        }

        $deviceId = self::readValue($rawData, 'deviceid');
        if (!is_scalar($deviceId) || trim((string) $deviceId) === '') {
            self::log(
                'POST_INVENTORY payload has no root deviceid. Payload type: '
                . get_debug_type($rawData)
                . self::describePayloadKeys($rawData)
            );
            return;
        }
        $deviceId = trim((string) $deviceId);
        self::log('POST_INVENTORY received for deviceid=' . $deviceId);

        // GLPI marks partial inventories at the root of the submitted inventory object.
        if (self::readValue($rawData, 'partial') === true
            || self::readValue($rawData, 'partial') === 1
            || self::readValue($rawData, 'partial') === '1') {
            self::log('Partial inventory ignored for deviceid=' . $deviceId);
            return;
        }

        try {
            $agent = new \Agent();
            if (!$agent->getFromDBByCrit(['deviceid' => $deviceId])) {
                self::log('Agent row not found after successful inventory. deviceid=' . $deviceId);
                return;
            }

            $agentItemtype = (string) ($agent->fields['itemtype'] ?? '');
            $computerId = (int) ($agent->fields['items_id'] ?? 0);
            self::log(
                'Agent resolved. deviceid=' . $deviceId
                . '; itemtype=' . $agentItemtype
                . '; items_id=' . $computerId
            );

            if ($agentItemtype !== \Computer::class && $agentItemtype !== 'Computer') {
                self::log('Agent is not linked to a Computer. deviceid=' . $deviceId . '; itemtype=' . $agentItemtype);
                return;
            }

            if ($computerId <= 0) {
                self::log('Agent has no linked Computer ID. deviceid=' . $deviceId);
                return;
            }

            $computer = new \Computer();
            if (!$computer->getFromDB($computerId)) {
                self::log('Linked Computer record not found. deviceid=' . $deviceId . '; computer_id=' . $computerId);
                return;
            }

            $computerName = (string) ($computer->fields['name'] ?? ('Computer #' . $computerId));
            SnapshotService::retryPendingNotifications($computerId, $computerName);

            $components = ComponentRepository::collect($computerId);
            self::log(
                'Hardware components collected. computer_id=' . $computerId
                . '; count=' . count($components)
                . '; ram=' . count(array_filter($components, static fn(array $c): bool => $c['component_type'] === 'ram'))
                . '; disks=' . count(array_filter($components, static fn(array $c): bool => $c['component_type'] === 'disk'))
            );

            SnapshotService::capture($computerId, $computerName, $components);
            self::log('Audit snapshot processing completed. computer_id=' . $computerId);
        } catch (\Throwable $exception) {
            self::log(
                'Audit failed for deviceid=' . $deviceId
                . ': ' . $exception->getMessage()
            );
            \Toolbox::logError('Control HW inventory audit failed: ' . $exception->getMessage());
        }
    }

    private static function extractInventoryData($params)
    {
        if (is_array($params)) {
            // Some hook integrations wrap the inventory in "data"; GLPI's native
            // POST_INVENTORY hook normally passes the raw inventory itself.
            return array_key_exists('data', $params) ? $params['data'] : $params;
        }

        if (is_object($params) && property_exists($params, 'data')) {
            return $params->data;
        }

        return is_object($params) ? $params : null;
    }

    /**
     * Return only top-level field names for diagnostics; never log inventory values.
     */
    private static function describePayloadKeys($payload): string
    {
        if (is_array($payload)) {
            return '; top-level keys=' . implode(',', array_map('strval', array_keys($payload)));
        }

        if (is_object($payload)) {
            return '; top-level keys=' . implode(',', array_keys(get_object_vars($payload)));
        }

        return '';
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

    private static function log(string $message): void
    {
        \Toolbox::logInFile('controlhw', '[Control HW] ' . $message . "\n");
    }
}
