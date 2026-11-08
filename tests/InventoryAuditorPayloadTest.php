<?php

require_once __DIR__ . '/../src/InventoryAuditor.php';

function controlhwPayloadAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$inventory = (object) ['deviceid' => 'agent-test-001'];
$extract = new ReflectionMethod(\GlpiPlugin\Controlhw\InventoryAuditor::class, 'extractInventoryData');

$fromHook = $extract->invoke(null, ['data' => $inventory]);
controlhwPayloadAssert($fromHook === $inventory, 'POST_INVENTORY data must be extracted from the hook parameter array.');

$direct = $extract->invoke(null, $inventory);
controlhwPayloadAssert($direct === $inventory, 'A direct inventory object must remain supported.');

$partial = new ReflectionMethod(\GlpiPlugin\Controlhw\InventoryAuditor::class, 'containsPartialMarker');
controlhwPayloadAssert(
    $partial->invoke(null, ['data' => ['hardware' => ['partial' => true]]]) === true,
    'A nested partial marker must be detected.'
);
controlhwPayloadAssert(
    $partial->invoke(null, ['data' => ['hardware' => ['partial' => false]]]) === false,
    'A complete inventory must not be marked partial.'
);

echo "Control HW inventory hook payload tests passed.\n";
