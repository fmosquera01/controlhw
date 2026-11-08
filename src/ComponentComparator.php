<?php

namespace GlpiPlugin\Controlhw;

final class ComponentComparator
{
    /**
     * Compare two normalized component lists.
     *
     * @return array<int, array{change_type:string, component_type:string, old:?array, new:?array}>
     */
    public static function compare(array $oldComponents, array $newComponents): array
    {
        $old = self::index($oldComponents);
        $new = self::index($newComponents);
        $added = [];
        $removed = [];
        $changes = [];

        foreach ($old as $key => $oldComponent) {
            if (!isset($new[$key])) {
                $removed[] = $oldComponent;
                continue;
            }

            $newComponent = $new[$key];
            if (self::comparable($oldComponent) !== self::comparable($newComponent)) {
                $changes[] = [
                    'change_type' => 'modified',
                    'component_type' => $newComponent['component_type'],
                    'old' => $oldComponent,
                    'new' => $newComponent,
                ];
            }
            unset($new[$key]);
        }

        foreach ($new as $newComponent) {
            $added[] = $newComponent;
        }

        // A changed serial in the same physical slot is reported as a replacement.
        foreach ($removed as $oldIndex => $oldComponent) {
            if (empty($oldComponent['slot'])) {
                continue;
            }
            foreach ($added as $newIndex => $newComponent) {
                if (
                    $oldComponent['component_type'] === $newComponent['component_type']
                    && !empty($newComponent['slot'])
                    && strcasecmp((string) $oldComponent['slot'], (string) $newComponent['slot']) === 0
                ) {
                    $changes[] = [
                        'change_type' => 'replaced',
                        'component_type' => $newComponent['component_type'],
                        'old' => $oldComponent,
                        'new' => $newComponent,
                    ];
                    unset($removed[$oldIndex], $added[$newIndex]);
                    break;
                }
            }
        }

        foreach ($added as $component) {
            $changes[] = [
                'change_type' => 'added',
                'component_type' => $component['component_type'],
                'old' => null,
                'new' => $component,
            ];
        }
        foreach ($removed as $component) {
            $changes[] = [
                'change_type' => 'removed',
                'component_type' => $component['component_type'],
                'old' => $component,
                'new' => null,
            ];
        }

        return array_values($changes);
    }

    private static function index(array $components): array
    {
        $indexed = [];
        foreach ($components as $component) {
            $indexed[(string) $component['identity_key']] = $component;
        }
        return $indexed;
    }

    private static function comparable(array $component): array
    {
        return [
            'serial' => $component['serial'] ?? null,
            'manufacturer' => $component['manufacturer'] ?? null,
            'model' => $component['model'] ?? null,
            'capacity' => $component['capacity'] ?? null,
            'slot' => $component['slot'] ?? null,
            'details' => $component['details'] ?? [],
        ];
    }
}
