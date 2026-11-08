<?php

namespace GlpiPlugin\Controlhw;

final class InventoryAuditor
{
    /**
     * Called by GLPI after a Computer has been added or updated.
     * GLPI passes the Computer CommonDBTM instance, not the inventory XML payload.
     */
    public static function onComputerChange($item): void
    {
        if (!$item instanceof \Computer) {
            return;
        }

        try {
            $computerId = (int) $item->getID();
            if ($computerId <= 0) {
                return;
            }

            // Reload the record to avoid relying on a partially populated hook object.
            $computer = new \Computer();
            if (!$computer->getFromDB($computerId)) {
                return;
            }

            $computerName = (string) (
                $computer->fields['name'] ?? ('Computer #' . $computerId)
            );

            SnapshotService::retryPendingNotifications($computerId, $computerName);
            $components = ComponentRepository::collect($computerId);
            SnapshotService::capture($computerId, $computerName, $components);
        } catch (\Throwable $exception) {
            // Never interrupt or roll back a successful GLPI inventory.
            \Toolbox::logError(
                'Control HW computer audit failed (Computer ID '
                . (int) ($item->fields['id'] ?? 0)
                . '): ' . $exception->getMessage()
            );
        }
    }
}
