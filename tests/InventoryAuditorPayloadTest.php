<?php

require_once __DIR__ . '/../src/InventoryAuditor.php';

function controlhwAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$setup = file_get_contents(__DIR__ . '/../setup.php');
controlhwAssert(is_string($setup), 'Could not read setup.php.');
controlhwAssert(str_contains($setup, 'Hooks::POST_INVENTORY'), 'POST_INVENTORY hook must be registered.');
controlhwAssert(str_contains($setup, "'onInventory'"), 'POST_INVENTORY callback must be onInventory.');
controlhwAssert(!str_contains($setup, 'Hooks::ITEM_ADD'), 'Computer item_add must not replace POST_INVENTORY.');
controlhwAssert(!str_contains($setup, 'Hooks::ITEM_UPDATE'), 'Computer item_update must not replace POST_INVENTORY.');

$inventory = (object) ['deviceid' => 'agent-test-001'];
$extract = new ReflectionMethod(\GlpiPlugin\Controlhw\InventoryAuditor::class, 'extractInventoryData');
controlhwAssert($extract->invoke(null, $inventory) === $inventory, 'Raw inventory object must be accepted.');
controlhwAssert(
    $extract->invoke(null, ['data' => $inventory]) === $inventory,
    'Wrapped inventory data must remain supported.'
);
$rootArrayInventory = ['deviceid' => 'agent-test-array-001', 'content' => (object) []];
controlhwAssert(
    $extract->invoke(null, $rootArrayInventory) === $rootArrayInventory,
    'Root-array inventory payload must be accepted.'
);
controlhwAssert(
    $extract->invoke(null, ['data' => $rootArrayInventory]) === $rootArrayInventory,
    'Wrapped root-array inventory payload must remain supported.'
);

$auditorSource = file_get_contents(__DIR__ . '/../src/InventoryAuditor.php');
controlhwAssert(is_string($auditorSource), 'Could not read InventoryAuditor.php.');
controlhwAssert(
    str_contains($auditorSource, 'public static function onInventory($params): mixed')
        && str_contains($auditorSource, 'return $params;'),
    'POST_INVENTORY callback must preserve the hook parameter chain.'
);

echo "Control HW POST_INVENTORY hook tests passed.\n";
