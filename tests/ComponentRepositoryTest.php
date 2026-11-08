<?php

require_once __DIR__ . '/../src/ComponentRepository.php';

final class ControlhwMockDB
{
    public array $queries = [];

    public function request(string $sql): array
    {
        $this->queries[] = $sql;

        if (str_contains($sql, 'glpi_items_devicememories')) {
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

        if (str_contains($sql, 'glpi_items_deviceharddrives')) {
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

controlhwRepositoryAssert(count($components) === 2, 'Repository must return RAM and disk components.');
$ram = $components[0]['component_type'] === 'ram' ? $components[0] : $components[1];
$disk = $components[0]['component_type'] === 'disk' ? $components[0] : $components[1];

controlhwRepositoryAssert($ram['details']['frequency_mhz'] === '3200', 'RAM frequency must be retained.');
controlhwRepositoryAssert($ram['details']['memory_type'] === 'DDR4', 'RAM type must be retained.');
controlhwRepositoryAssert($disk['details']['interface_type'] === 'NVMe', 'Disk interface must be retained.');
controlhwRepositoryAssert($disk['details']['disk_type'] === 'SSD', 'Disk type must be retained.');
controlhwRepositoryAssert(
    str_contains($DB->queries[0], 'glpi_devicememorymodels')
        && str_contains($DB->queries[1], 'glpi_deviceharddrivemodels'),
    'Repository queries must join catalog models.'
);

echo "Control HW component repository tests passed.\n";
