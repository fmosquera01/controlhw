<?php

require_once __DIR__ . '/../src/ComponentComparator.php';

function controlhwAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$ram = [
    'component_type' => 'ram',
    'identity_key' => 'serial:ram-a',
    'serial' => 'RAM-A',
    'manufacturer' => 'Vendor',
    'model' => 'DDR4',
    'capacity' => '16384',
    'slot' => 'DIMM_A1',
    'details' => ['frequency' => '3200'],
];

$noChanges = \GlpiPlugin\Controlhw\ComponentComparator::compare([$ram], [$ram]);
controlhwAssert($noChanges === [], 'An unchanged inventory must not create alerts.');

$added = $ram;
$added['identity_key'] = 'serial:ram-b';
$added['serial'] = 'RAM-B';
$changes = \GlpiPlugin\Controlhw\ComponentComparator::compare([$ram], [$ram, $added]);
controlhwAssert(count($changes) === 1 && $changes[0]['change_type'] === 'added', 'A new RAM module must be detected.');

$replacement = $added;
$replacement['slot'] = 'DIMM_A1';
$changes = \GlpiPlugin\Controlhw\ComponentComparator::compare([$ram], [$replacement]);
controlhwAssert(count($changes) === 1 && $changes[0]['change_type'] === 'replaced', 'A serial change in the same slot must be a replacement.');

$modified = $ram;
$modified['capacity'] = '32768';
$changes = \GlpiPlugin\Controlhw\ComponentComparator::compare([$ram], [$modified]);
controlhwAssert(count($changes) === 1 && $changes[0]['change_type'] === 'modified', 'A capacity change must be detected.');

$changes = \GlpiPlugin\Controlhw\ComponentComparator::compare([$ram], []);
controlhwAssert(count($changes) === 1 && $changes[0]['change_type'] === 'removed', 'A removed component must be detected on a complete inventory.');

echo "Control HW comparator tests passed.\\n";
