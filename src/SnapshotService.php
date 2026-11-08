<?php

namespace GlpiPlugin\Controlhw;

final class SnapshotService
{
    public static function capture(int $computerId, string $computerName, array $components): void
    {
        global $DB;

        $canonical = array_map(static function (array $component): array {
            return [
                'component_type' => $component['component_type'],
                'identity_key' => $component['identity_key'],
                'serial' => $component['serial'],
                'manufacturer' => $component['manufacturer'],
                'model' => $component['model'],
                'capacity' => $component['capacity'],
                'slot' => $component['slot'],
                'details' => $component['details'],
            ];
        }, $components);
        $inventoryHash = hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        $latest = self::latestSnapshot($computerId);
        if ($latest !== null && hash_equals((string) $latest['inventory_hash'], $inventoryHash)) {
            return;
        }

        $previousComponents = $latest === null ? [] : self::loadComponents((int) $latest['id']);

        if (!$DB->insert('glpi_plugin_controlhw_snapshots', [
            'computers_id' => $computerId,
            'inventory_hash' => $inventoryHash,
            'components_count' => count($components),
            'is_complete' => 1,
        ])) {
            throw new \RuntimeException('Could not persist hardware snapshot.');
        }
        $snapshotId = (int) $DB->insertId();

        foreach ($components as $component) {
            if (!$DB->insert('glpi_plugin_controlhw_components', [
                'snapshots_id' => $snapshotId,
                'component_type' => $component['component_type'],
                'identity_key' => $component['identity_key'],
                'serial' => $component['serial'],
                'manufacturer' => $component['manufacturer'],
                'model' => $component['model'],
                'capacity' => $component['capacity'],
                'slot' => $component['slot'],
                'details_json' => $component['details_json'],
            ])) {
                throw new \RuntimeException('Could not persist a hardware component snapshot.');
            }
        }

        // First inventory is the baseline: persist it, but do not alert.
        if ($latest === null) {
            return;
        }

        foreach (ComponentComparator::compare($previousComponents, $components) as $change) {
            self::createAlert($computerId, $snapshotId, $computerName, $latest, $change);
        }
    }

    /**
     * Retry alerts that could not be handed to FML Telegram earlier.
     * Retrying is opportunistic: it runs on the next successful inventory for this computer.
     */
    public static function retryPendingNotifications(int $computerId, string $computerName): void
    {
        global $DB;

        foreach ($DB->request(
            "SELECT id, component_type, change_type, old_data, new_data
             FROM glpi_plugin_controlhw_alerts
             WHERE computers_id = {$computerId}
               AND notification_status = 'pending'
             ORDER BY id ASC"
        ) as $row) {
            $old = $row['old_data'] !== null ? json_decode((string) $row['old_data'], true) : null;
            $new = $row['new_data'] !== null ? json_decode((string) $row['new_data'], true) : null;
            $change = [
                'component_type' => $row['component_type'],
                'change_type' => $row['change_type'],
                'old' => is_array($old) ? $old : null,
                'new' => is_array($new) ? $new : null,
            ];

            $result = TelegramNotifier::notify((int) $row['id'], $computerName, $computerId, $change);
            $queued = (int) ($result['queued'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $errors = $result['errors'] ?? [];

            $DB->update('glpi_plugin_controlhw_alerts', [
                'notification_status' => ($queued > 0 || $skipped > 0) ? 'queued' : 'pending',
                'notification_error' => $errors === [] ? null : implode('; ', array_map('strval', $errors)),
            ], ['id' => (int) $row['id']]);
        }
    }

    private static function latestSnapshot(int $computerId): ?array
    {
        global $DB;

        foreach ($DB->request(
            "SELECT id, inventory_hash
             FROM glpi_plugin_controlhw_snapshots
             WHERE computers_id = {$computerId} AND is_complete = 1
             ORDER BY id DESC LIMIT 1"
        ) as $row) {
            return $row;
        }
        return null;
    }

    private static function loadComponents(int $snapshotId): array
    {
        global $DB;

        $components = [];
        foreach ($DB->request(
            "SELECT component_type, identity_key, serial, manufacturer, model,
                    capacity, slot, details_json
             FROM glpi_plugin_controlhw_components
             WHERE snapshots_id = {$snapshotId}
             ORDER BY id ASC"
        ) as $row) {
            $details = json_decode((string) ($row['details_json'] ?? '{}'), true);
            $components[] = [
                'component_type' => $row['component_type'],
                'identity_key' => $row['identity_key'],
                'serial' => $row['serial'],
                'manufacturer' => $row['manufacturer'],
                'model' => $row['model'],
                'capacity' => $row['capacity'],
                'slot' => $row['slot'],
                'details' => is_array($details) ? $details : [],
                'details_json' => (string) ($row['details_json'] ?? '{}'),
            ];
        }
        return $components;
    }

    private static function createAlert(
        int $computerId,
        int $snapshotId,
        string $computerName,
        array $previousSnapshot,
        array $change
    ): void {
        global $DB;

        $oldJson = $change['old'] === null
            ? null
            : (json_encode($change['old'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        $newJson = $change['new'] === null
            ? null
            : (json_encode($change['new'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        $eventHash = hash('sha256', json_encode([
            $computerId,
            (int) $previousSnapshot['id'],
            $change['change_type'],
            $oldJson,
            $newJson,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        if (!$DB->insert('glpi_plugin_controlhw_alerts', [
            'computers_id' => $computerId,
            'snapshots_id' => $snapshotId,
            'component_type' => $change['component_type'],
            'change_type' => $change['change_type'],
            'old_data' => $oldJson,
            'new_data' => $newJson,
            'event_hash' => $eventHash,
            'notification_status' => 'pending',
        ])) {
            // A duplicate event is ignored by the unique event_hash index.
            return;
        }

        $alertId = (int) $DB->insertId();
        $result = TelegramNotifier::notify($alertId, $computerName, $computerId, $change);
        $queued = (int) ($result['queued'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $errors = $result['errors'] ?? [];

        $status = ($queued > 0 || $skipped > 0) ? 'queued' : 'pending';
        $errorText = $errors === [] ? null : implode('; ', array_map('strval', $errors));

        $DB->update('glpi_plugin_controlhw_alerts', [
            'notification_status' => $status,
            'notification_error' => $errorText,
        ], ['id' => $alertId]);
    }
}
