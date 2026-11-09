<?php

require_once __DIR__ . '/../src/ComponentRepository.php';

final class ControlhwMockDB
{
    public array $queries = [];

    public function request(array $criteria): array
    {
        $this->queries[] = $criteria;
        $table = (string) ($criteria['FROM'] ?? '');

        if (str_contains($table, 'glpi_items_devicememories')) {
            return [[
                'serial' => 'RAM-001',
                'otherserial' => null,
                'capacity' => '16384',
                'slot' => 'DIMM_A1',
                'model' => 'ThinkPad DDR4',
                'manufacturer' => 'Lenovo',
                'frequency' => '3200',
                'memory_type' => 'DDR4',
                'component_type_id' => 2,
            ]];
        }

        if (str_contains($table, 'glpi_items_deviceharddrives')) {
            return [[
                'serial' => 'SSD-001',
                'otherserial' => null,
                'capacity' => '512000',
                'slot' => 'NVMe 1',
                'model' => 'NVMe SSD',
                'manufacturer' => 'Samsung',
                'interface_type' => 'NVMe',
                'disk_type' => 'SSD',
                'interface_type_id' => 3,
                'component_type_id' => 1,
            ]];
        }

        if (str_contains($table, 'glpi_networkports')) {
            return [[
                'mac' => 'aa-bb-cc-dd-ee-ff',
                'port_name' => 'Wi-Fi',
                'logical_number' => 1,
                'instantiation_type' => 'NetworkPortWifi',
                'is_dynamic' => 0,
            ]];
        }

        return [];
    }
}

function controlhwRepositoryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$DB = new ControlhwMockDB();
$components = \GlpiPlugin\Controlhw\ComponentRepository::collect(42);

controlhwRepositoryAssert(count($components) === 3, 'Repository must return RAM, disk, and network MAC components.');
$ram = array_values(array_filter($components, static fn(array $c): bool => $c['component_type'] === 'ram'))[0];
$disk = array_values(array_filter($components, static fn(array $c): bool => $c['component_type'] === 'disk'))[0];
$mac = array_values(array_filter($components, static fn(array $c): bool => $c['component_type'] === 'mac'))[0];

controlhwRepositoryAssert($ram['details']['frequency_mhz'] === '3200', 'RAM frequency must be retained.');
controlhwRepositoryAssert($ram['details']['memory_type'] === 'DDR4', 'RAM type must be retained.');
controlhwRepositoryAssert($disk['details']['interface_type'] === 'NVMe', 'Disk interface must be retained.');
controlhwRepositoryAssert($disk['details']['disk_type'] === 'SSD', 'Disk type must be retained.');
controlhwRepositoryAssert($mac['serial'] === 'AA:BB:CC:DD:EE:FF', 'Network MAC must be normalized.');
controlhwRepositoryAssert($mac['component_type'] === 'mac' && $mac['slot'] === 'Puerto 1 - Wi-Fi', 'Network MAC must retain its stable interface identity.');
controlhwRepositoryAssert(
    str_contains((string) ($DB->queries[0]['FROM'] ?? ''), 'glpi_items_devicememories')
        && str_contains((string) ($DB->queries[1]['FROM'] ?? ''), 'glpi_items_deviceharddrives')
        && str_contains((string) ($DB->queries[2]['FROM'] ?? ''), 'glpi_networkports'),
    'Repository queries must join catalog models.'
);

echo "Control HW component repository tests passed.\n";
