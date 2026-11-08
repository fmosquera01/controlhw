<?php

namespace GlpiPlugin\Controlhw;

final class ComponentRepository
{
    public static function collect(int $computerId): array
    {
        global $DB;

        if ($computerId <= 0) {
            throw new \RuntimeException('Invalid computer ID for hardware audit.');
        }

        $components = [];

        $memorySql = "SELECT
                im.serial,
                im.otherserial,
                im.size AS capacity,
                im.busID AS slot,
                COALESCE(NULLIF(dmm.name, ''), dm.designation) AS model,
                mf.name AS manufacturer,
                dm.frequence AS frequency,
                dmt.name AS memory_type,
                dm.devicememorytypes_id AS component_type_id
            FROM glpi_items_devicememories im
            LEFT JOIN glpi_devicememories dm ON dm.id = im.devicememories_id
            LEFT JOIN glpi_devicememorymodels dmm ON dmm.id = dm.devicememorymodels_id
            LEFT JOIN glpi_devicememorytypes dmt ON dmt.id = dm.devicememorytypes_id
            LEFT JOIN glpi_manufacturers mf ON mf.id = dm.manufacturers_id
            WHERE im.itemtype = 'Computer'
              AND im.items_id = " . $computerId . "
              AND im.is_deleted = 0";

        foreach ($DB->request($memorySql) as $row) {
            $components[] = self::normalize('ram', $row, [
                'frequency_mhz' => $row['frequency'] ?? null,
                'memory_type' => $row['memory_type'] ?? null,
                'memory_type_id' => $row['component_type_id'] ?? null,
            ]);
        }

        $diskSql = "SELECT
                ih.serial,
                ih.otherserial,
                ih.capacity,
                ih.busID AS slot,
                COALESCE(NULLIF(dhm.name, ''), dh.designation) AS model,
                mf.name AS manufacturer,
                it.name AS interface_type,
                dht.name AS disk_type,
                dh.interfacetypes_id AS interface_type_id,
                dh.deviceharddrivetypes_id AS component_type_id
            FROM glpi_items_deviceharddrives ih
            LEFT JOIN glpi_deviceharddrives dh ON dh.id = ih.deviceharddrives_id
            LEFT JOIN glpi_deviceharddrivemodels dhm ON dhm.id = dh.deviceharddrivemodels_id
            LEFT JOIN glpi_deviceharddrivetypes dht ON dht.id = dh.deviceharddrivetypes_id
            LEFT JOIN glpi_interfacetypes it ON it.id = dh.interfacetypes_id
            LEFT JOIN glpi_manufacturers mf ON mf.id = dh.manufacturers_id
            WHERE ih.itemtype = 'Computer'
              AND ih.items_id = " . $computerId . "
              AND ih.is_deleted = 0";

        foreach ($DB->request($diskSql) as $row) {
            $components[] = self::normalize('disk', $row, [
                'interface_type' => $row['interface_type'] ?? null,
                'disk_type' => $row['disk_type'] ?? null,
                'interface_type_id' => $row['interface_type_id'] ?? null,
                'disk_type_id' => $row['component_type_id'] ?? null,
            ]);
        }

        usort($components, static function (array $a, array $b): int {
            return [$a['component_type'], $a['identity_key'], $a['details_json']]
                <=> [$b['component_type'], $b['identity_key'], $b['details_json']];
        });

        // Disambiguate physically indistinguishable components without relying on DB row IDs.
        $occurrences = [];
        foreach ($components as &$component) {
            $baseKey = $component['identity_key'];
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;
            if ($occurrences[$baseKey] > 1) {
                $component['identity_key'] .= '#' . $occurrences[$baseKey];
            }
        }
        unset($component);

        return $components;
    }

    private static function normalize(string $type, array $row, array $extra): array
    {
        $serial = self::clean($row['serial'] ?? null);
        $otherSerial = self::clean($row['otherserial'] ?? null);
        $manufacturer = self::clean($row['manufacturer'] ?? null);
        $model = self::clean($row['model'] ?? null);
        $capacity = self::clean($row['capacity'] ?? null);
        $slot = self::clean($row['slot'] ?? null);

        $serialIdentity = self::validSerial($serial) ? strtoupper($serial) : null;
        if ($serialIdentity !== null) {
            $identity = 'serial:' . hash('sha256', $type . '|' . $serialIdentity);
        } elseif ($slot !== null) {
            $identity = 'slot:' . hash('sha256', $type . '|' . strtoupper($slot));
        } else {
            $identity = 'fingerprint:' . hash('sha256', json_encode([
                $type, $manufacturer, $model, $capacity, $extra,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        $details = array_merge([
            'other_serial' => $otherSerial,
            'frequency_mhz' => null,
            'memory_type' => null,
            'memory_type_id' => null,
            'interface_type' => null,
            'disk_type' => null,
            'interface_type_id' => null,
            'disk_type_id' => null,
        ], $extra);

        return [
            'component_type' => $type,
            'identity_key' => $identity,
            'serial' => $serial,
            'manufacturer' => $manufacturer,
            'model' => $model,
            'capacity' => $capacity,
            'slot' => $slot,
            'details' => $details,
            'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ];
    }

    private static function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function validSerial(?string $serial): bool
    {
        if ($serial === null) {
            return false;
        }
        $normalized = strtoupper(trim($serial));
        return !in_array($normalized, [
            'UNKNOWN', 'NONE', 'N/A', 'NA', 'NOT SPECIFIED',
            'TO BE FILLED BY O.E.M.', 'DEFAULT STRING', 'SYSTEM SERIAL NUMBER',
            '00000000', '0000000000000000', '123456789',
        ], true) && preg_match('/[A-Z0-9]/', $normalized) === 1;
    }
}
