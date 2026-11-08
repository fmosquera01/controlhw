<?php

$setup = file_get_contents(__DIR__ . '/../setup.php');
if (!is_string($setup)) {
    throw new RuntimeException('Could not read setup.php.');
}

if (str_contains($setup, 'Hooks::POST_INVENTORY')) {
    throw new RuntimeException('Unsupported POST_INVENTORY hook must not be registered.');
}

if (!str_contains($setup, 'Hooks::ITEM_ADD') || !str_contains($setup, 'Hooks::ITEM_UPDATE')) {
    throw new RuntimeException('Computer add/update hooks must both be registered.');
}

if (!str_contains($setup, 'InventoryAuditor::class') || !str_contains($setup, "'onComputerChange'")) {
    throw new RuntimeException('Computer audit callback is not registered.');
}

echo "Control HW inventory hook registration tests passed.\n";
