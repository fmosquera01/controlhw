<?php

namespace GlpiPlugin\Controlhw;

use GlpiPlugin\Telegram\NotificationService;
use Throwable;

final class TelegramNotifier
{
    public static function notify(int $alertId, string $computerName, int $computerId, array $change): array
    {
        if (!class_exists(NotificationService::class)) {
            return ['queued' => 0, 'skipped' => 0, 'errors' => ['FML Telegram is not available or enabled.']];
        }

        try {
            $type = $change['component_type'] === 'ram' ? 'Memoria RAM' : 'Disco';
            $labels = [
                'added' => 'Agregado',
                'removed' => 'Retirado',
                'replaced' => 'Sustituido',
                'modified' => 'Modificado',
            ];
            $changeLabel = $labels[$change['change_type']] ?? $change['change_type'];

            $message = "Control HW - Cambio de hardware\n"
                . "Equipo: {$computerName} (ID {$computerId})\n"
                . "Componente: {$type}\n"
                . "Cambio: {$changeLabel}\n";

            if ($change['old'] !== null) {
                $message .= "Antes: " . self::describe($change['old']) . "\n";
            }
            if ($change['new'] !== null) {
                $message .= "Ahora: " . self::describe($change['new']);
            }

            return NotificationService::enqueue([
                'source' => 'controlhw',
                'event' => 'hardware.changed',
                'subject' => "Control HW: {$changeLabel} - {$computerName}",
                'message' => trim($message),
                'recipients' => [],
                'deduplication_key' => 'controlhw-alert-' . $alertId,
            ]);
        } catch (Throwable $exception) {
            return ['queued' => 0, 'skipped' => 0, 'errors' => [$exception->getMessage()]];
        }
    }

    private static function describe(array $component): string
    {
        $parts = array_filter([
            $component['manufacturer'] ?? null,
            $component['model'] ?? null,
            $component['capacity'] ?? null,
            !empty($component['serial']) ? 'Serie ' . $component['serial'] : null,
            !empty($component['slot']) ? 'Ubicación ' . $component['slot'] : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return $parts === [] ? 'Datos no disponibles' : implode(' / ', $parts);
    }
}
