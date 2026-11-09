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

        $memoryCriteria = [
            'SELECT' => [
                'im.serial',
                'im.otherserial',
                'im.size AS capacity',
                'im.busID AS slot',
                'dmm.name AS model_name',
                'dm.designation',
                'mf.name AS manufacturer',
                'dm.frequence AS frequency',
                'dmt.name AS memory_type',
                'dm.devicememorytypes_id AS component_type_id',
            ],
            'FROM' => 'glpi_items_devicememories AS im',
            'LEFT JOIN' => [
                'glpi_devicememories AS dm' => [
                    'ON' => ['im' => 'devicememories_id', 'dm' => 'id'],
                ],
                'glpi_devicememorymodels AS dmm' => [
                    'ON' => ['dm' => 'devicememorymodels_id', 'dmm' => 'id'],
                ],
                'glpi_devicememorytypes AS dmt' => [
                    'ON' => ['dm' => 'devicememorytypes_id', 'dmt' => 'id'],
                ],
                'glpi_manufacturers AS mf' => [
                    'ON' => ['dm' => 'manufacturers_id', 'mf' => 'id'],
                ],
            ],
            'WHERE' => [
                'im.itemtype' => 'Computer',
                'im.items_id' => $computerId,
                'im.is_deleted' => 0,
            ],
        ];

        foreach ($DB->request($memoryCriteria) as $row) {
            $row['model'] = self::clean($row['model_name'] ?? null)
                ?? self::clean($row['designation'] ?? null);
            $components[] = self::normalize('ram', $row, [
                'frequency_mhz' => $row['frequency'] ?? null,
                'memory_type' => $row['memory_type'] ?? null,
                'memory_type_id' => $row['component_type_id'] ?? null,
            ]);
        }

        $diskCriteria = [
            'SELECT' => [
                'ih.serial',
                'ih.otherserial',
                'ih.capacity',
                'ih.busID AS slot',
                'dhm.name AS model_name',
                'dh.designation',
                'mf.name AS manufacturer',
                'it.name AS interface_type',
                'dht.name AS disk_type',
                'dh.interfacetypes_id AS interface_type_id',
                'dh.deviceharddrivetypes_id AS component_type_id',
            ],
            'FROM' => 'glpi_items_deviceharddrives AS ih',
            'LEFT JOIN' => [
                'glpi_deviceharddrives AS dh' => [
                    'ON' => ['ih' => 'deviceharddrives_id', 'dh' => 'id'],
                ],
                'glpi_deviceharddrivemodels AS dhm' => [
                    'ON' => ['dh' => 'deviceharddrivemodels_id', 'dhm' => 'id'],
                ],
                'glpi_deviceharddrivetypes AS dht' => [
                    'ON' => ['dh' => 'deviceharddrivetypes_id', 'dht' => 'id'],
                ],
                'glpi_interfacetypes AS it' => [
                    'ON' => ['dh' => 'interfacetypes_id', 'it' => 'id'],
                ],
                'glpi_manufacturers AS mf' => [
                    'ON' => ['dh' => 'manufacturers_id', 'mf' => 'id'],
                ],
            ],
            'WHERE' => [
                'ih.itemtype' => 'Computer',
                'ih.items_id' => $computerId,
                'ih.is_deleted' => 0,
            ],
        ];

        foreach ($DB->request($diskCriteria) as $row) {
            $row['model'] = self::clean($row['model_name'] ?? null)
                ?? self::clean($row['designation'] ?? null);
            $components[] = self::normalize('disk', $row, [
                'interface_type' => $row['interface_type'] ?? null,
                'disk_type' => $row['disk_type'] ?? null,
                'interface_type_id' => $row['interface_type_id'] ?? null,
                'disk_type_id' => $row['component_type_id'] ?? null,
            ]);
        }

        // Audit every reported network interface MAC, including Wi-Fi adapters.
        // A stable port key lets the comparator identify a MAC replacement on the same interface.
        $networkCriteria = [
            'SELECT' => [
                'np.mac',
                'np.name AS port_name',
                'np.logical_number',
                'np.instantiation_type',
                'np.is_dynamic',
            ],
            'FROM' => 'glpi_networkports AS np',
            'WHERE' => [
                'np.itemtype' => 'Computer',
                'np.items_id' => $computerId,
                'np.is_deleted' => 0,
            ],
        ];

        foreach ($DB->request($networkCriteria) as $row) {
            $mac = self::normalizeMac($row['mac'] ?? null);
            if ($mac === null) {
                continue;
            }

            $portName = self::clean($row['port_name'] ?? null) ?? 'Interfaz';
            $logicalNumber = self::clean($row['logical_number'] ?? null);
            $slot = ($logicalNumber !== null ? 'Puerto ' . $logicalNumber . ' - ' : '') . $portName;
            $row['serial'] = $mac;
            $row['model'] = $portName;
            $row['slot'] = $slot;
            $components[] = self::normalize('mac', $row, [
                'network_port_type' => $row['instantiation_type'] ?? null,
                'is_dynamic' => $row['is_dynamic'] ?? null,
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

    private static function normalizeMac($value): ?string
    {
        $mac = self::clean($value);
        if ($mac === null) {
            return null;
        }

        $hex = strtoupper((string) preg_replace('/[^0-9A-F]/i', '', $mac));
        if (strlen($hex) !== 12 || preg_match('/^[0-9A-F]{12}$/', $hex) !== 1
            || $hex === '000000000000' || $hex === 'FFFFFFFFFFFF') {
            return null;
        }

        return implode(':', str_split($hex, 2));
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
