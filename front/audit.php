<?php

include('../../../inc/includes.php');

Session::checkRight('computer', READ);

global $DB;

function controlhw_h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function controlhw_date_filter(string $value): string
{
    return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value) ? $value : '';
}

function controlhw_component_details(?string $json): string
{
    $details = json_decode((string) $json, true);
    if (!is_array($details)) {
        return '—';
    }

    $parts = array_filter([
        !empty($details['memory_type']) ? 'Tipo RAM: ' . $details['memory_type'] : null,
        !empty($details['frequency_mhz']) ? $details['frequency_mhz'] . ' MHz' : null,
        !empty($details['disk_type']) ? 'Tipo disco: ' . $details['disk_type'] : null,
        !empty($details['interface_type']) ? 'Interfaz: ' . $details['interface_type'] : null,
    ]);

    return $parts === [] ? '—' : implode(' · ', $parts);
}

$search = trim((string) ($_GET['search'] ?? ''));
$changeType = (string) ($_GET['change_type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$dateFrom = controlhw_date_filter((string) ($_GET['date_from'] ?? ''));
$dateTo = controlhw_date_filter((string) ($_GET['date_to'] ?? ''));
$computerId = max(0, (int) ($_GET['computer_id'] ?? 0));

$allowedChanges = ['added', 'removed', 'modified', 'replaced'];
$allowedStatuses = ['queued', 'pending'];
if (!in_array($changeType, $allowedChanges, true)) {
    $changeType = '';
}
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$snapshotWhere = [];
if ($computerId > 0) {
    $snapshotWhere['s.computers_id'] = $computerId;
}
if ($dateFrom !== '') {
    $snapshotWhere['s.date_creation'] = ['>=', $dateFrom . ' 00:00:00'];
}
if ($dateTo !== '') {
    $snapshotWhere[] = ['s.date_creation' => ['<=', $dateTo . ' 23:59:59']];
}

$alertWhere = [];
if ($computerId > 0) {
    $alertWhere['a.computers_id'] = $computerId;
}
if ($changeType !== '') {
    $alertWhere['a.change_type'] = $changeType;
}
if ($status !== '') {
    $alertWhere['a.notification_status'] = $status;
}
if ($dateFrom !== '') {
    $alertWhere['a.date_creation'] = ['>=', $dateFrom . ' 00:00:00'];
}
if ($dateTo !== '') {
    $alertWhere[] = ['a.date_creation' => ['<=', $dateTo . ' 23:59:59']];
}

$matchingComputerIds = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    foreach ($DB->request([
        'SELECT' => ['sx.computers_id'],
        'DISTINCT' => true,
        'FROM' => 'glpi_plugin_controlhw_snapshots AS sx',
        'INNER JOIN' => [
            'glpi_plugin_controlhw_components AS cx' => [
                'ON' => ['sx' => 'id', 'cx' => 'snapshots_id'],
            ],
        ],
        'WHERE' => [
            ['OR' => [
                'cx.serial' => ['LIKE', $like],
                'cx.model' => ['LIKE', $like],
                'cx.manufacturer' => ['LIKE', $like],
            ]],
        ],
    ]) as $match) {
        $matchingComputerIds[] = (int) $match['computers_id'];
    }

    $searchComputerCriteria = ['c.name' => ['LIKE', $like]];
    if ($matchingComputerIds !== []) {
        $searchComputerCriteria['s.computers_id'] = $matchingComputerIds;
    }
    $snapshotWhere[] = ['OR' => $searchComputerCriteria];

    $matchingAlertComputerIds = [];
    foreach ($DB->request([
        'SELECT' => ['a.computers_id'],
        'DISTINCT' => true,
        'FROM' => 'glpi_plugin_controlhw_alerts AS a',
        'WHERE' => [
            ['OR' => [
                'a.old_data' => ['LIKE', $like],
                'a.new_data' => ['LIKE', $like],
            ]],
        ],
    ]) as $match) {
        $matchingAlertComputerIds[] = (int) $match['computers_id'];
    }
    $alertSearchCriteria = ['c.name' => ['LIKE', $like]];
    if ($matchingAlertComputerIds !== []) {
        $alertSearchCriteria['a.computers_id'] = $matchingAlertComputerIds;
    }
    $alertWhere[] = ['OR' => $alertSearchCriteria];
}

$countSnapshots = static function (string $table, array $where = []) use ($DB): int {
    $criteria = ['FROM' => $table, 'COUNT' => 'total'];
    if ($where !== []) {
        $criteria['WHERE'] = $where;
    }
    $iterator = $DB->request($criteria);
    $row = $iterator->current();
    return (int) ($row['total'] ?? 0);
};

$stats = [
    'computers' => 0,
    'snapshots' => $countSnapshots('glpi_plugin_controlhw_snapshots'),
    'alerts' => $countSnapshots('glpi_plugin_controlhw_alerts'),
    'pending' => $countSnapshots('glpi_plugin_controlhw_alerts', ['notification_status' => 'pending']),
];
foreach ($DB->request([
    'SELECT' => ['computers_id'],
    'DISTINCT' => true,
    'FROM' => 'glpi_plugin_controlhw_snapshots',
]) as $computerRow) {
    $stats['computers']++;
}

$changesCountQuery = new QuerySubQuery([
    'SELECT' => ['COUNT' => 'total'],
    'FROM' => 'glpi_plugin_controlhw_alerts AS alert_count',
    'WHERE' => ['alert_count.snapshots_id' => new QueryExpression('s.id')],
], 'changes_count');

$historyCriteria = [
    'SELECT' => [
        's.id',
        's.computers_id',
        'c.name AS computer_name',
        's.components_count',
        's.date_creation',
        $changesCountQuery,
    ],
    'FROM' => 'glpi_plugin_controlhw_snapshots AS s',
    'INNER JOIN' => [
        'glpi_computers AS c' => [
            'ON' => ['s' => 'computers_id', 'c' => 'id'],
        ],
    ],
    'WHERE' => $snapshotWhere,
    'ORDER' => ['s.date_creation DESC', 's.id DESC'],
    'LIMIT' => 100,
];
$historyRows = [];
foreach ($DB->request($historyCriteria) as $row) {
    $historyRows[] = $row;
}

$alertsCriteria = [
    'SELECT' => [
        'a.id',
        'a.computers_id',
        'c.name AS computer_name',
        'a.component_type',
        'a.change_type',
        'a.old_data',
        'a.new_data',
        'a.notification_status',
        'a.notification_error',
        'a.date_creation',
    ],
    'FROM' => 'glpi_plugin_controlhw_alerts AS a',
    'INNER JOIN' => [
        'glpi_computers AS c' => [
            'ON' => ['a' => 'computers_id', 'c' => 'id'],
        ],
    ],
    'WHERE' => $alertWhere,
    'ORDER' => ['a.date_creation DESC', 'a.id DESC'],
    'LIMIT' => 200,
];
$alerts = [];
foreach ($DB->request($alertsCriteria) as $row) {
    $alerts[] = $row;
}

$selectedComputer = null;
$latestComponents = [];
if ($computerId > 0) {
    $computer = new Computer();
    if ($computer->getFromDB($computerId)) {
        $selectedComputer = $computer->fields;
        $latestSnapshotId = 0;
        $latestSnapshot = $DB->request([
            'SELECT' => ['id'],
            'FROM' => 'glpi_plugin_controlhw_snapshots',
            'WHERE' => ['computers_id' => $computerId, 'is_complete' => 1],
            'ORDER' => 'id DESC',
            'LIMIT' => 1,
        ])->current();
        if ($latestSnapshot !== null) {
            $latestSnapshotId = (int) $latestSnapshot['id'];
        }
        if ($latestSnapshotId > 0) {
            foreach ($DB->request([
                'SELECT' => [
                    'component_type',
                    'serial',
                    'manufacturer',
                    'model',
                    'capacity',
                    'slot',
                    'details_json',
                ],
                'FROM' => 'glpi_plugin_controlhw_components',
                'WHERE' => ['snapshots_id' => $latestSnapshotId],
                'ORDER' => ['component_type', 'slot', 'model'],
            ]) as $component) {
                $latestComponents[] = $component;
            }
        }
    }
}

Html::header(__('Control HW - Auditoría', 'controlhw'), $_SERVER['PHP_SELF'], 'plugins');
?>
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1><i class="ti ti-cpu"></i> Control HW - Auditoría de Hardware</h1>
            <p class="text-muted mb-0">Historial de memoria RAM y unidades de almacenamiento detectadas por GLPI Agent.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Equipos auditados</div><div class="fs-2 fw-bold"><?= controlhw_h($stats['computers']) ?></div></div></div></div>
        <div class="col-12 col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Inventarios guardados</div><div class="fs-2 fw-bold"><?= controlhw_h($stats['snapshots']) ?></div></div></div></div>
        <div class="col-12 col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Cambios detectados</div><div class="fs-2 fw-bold"><?= controlhw_h($stats['alerts']) ?></div></div></div></div>
        <div class="col-12 col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Avisos pendientes</div><div class="fs-2 fw-bold"><?= controlhw_h($stats['pending']) ?></div></div></div></div>
    </div>

    <form method="get" class="card card-body mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="search">Equipo, serie, modelo o fabricante</label>
                <input class="form-control" id="search" name="search" value="<?= controlhw_h($search) ?>" placeholder="Buscar...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date_from">Desde</label>
                <input class="form-control" type="date" id="date_from" name="date_from" value="<?= controlhw_h($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="date_to">Hasta</label>
                <input class="form-control" type="date" id="date_to" name="date_to" value="<?= controlhw_h($dateTo) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="change_type">Tipo de cambio</label>
                <select class="form-select" id="change_type" name="change_type">
                    <option value="">Todos</option>
                    <?php foreach (['added' => 'Agregado', 'removed' => 'Retirado', 'modified' => 'Modificado', 'replaced' => 'Reemplazado'] as $key => $label): ?>
                        <option value="<?= controlhw_h($key) ?>" <?= $changeType === $key ? 'selected' : '' ?>><?= controlhw_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="status">Estado Telegram</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <?php foreach (['queued' => 'Encolado en FML Telegram', 'pending' => 'Pendiente de envío'] as $key => $label): ?>
                        <option value="<?= controlhw_h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= controlhw_h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="ti ti-filter"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?= controlhw_h($_SERVER['PHP_SELF']) ?>">Limpiar</a>
            </div>
        </div>
    </form>

    <?php if ($selectedComputer !== null): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Componentes del último inventario: <?= controlhw_h($selectedComputer['name'] ?? ('Equipo #' . $computerId)) ?></strong>
                <a href="<?= controlhw_h($CFG_GLPI['root_doc'] . '/front/computer.form.php?id=' . $computerId) ?>">Abrir equipo en GLPI</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Tipo</th><th>Fabricante</th><th>Modelo</th><th>Capacidad</th><th>Serie</th><th>Detalles</th><th>Slot / conexión</th></tr></thead>
                    <tbody>
                    <?php if ($latestComponents === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No hay componentes registrados en el último inventario.</td></tr>
                    <?php else: foreach ($latestComponents as $component): ?>
                        <tr>
                            <td><?= controlhw_h($component['component_type'] === 'ram' ? 'Memoria RAM' : 'Disco') ?></td>
                            <td><?= controlhw_h($component['manufacturer']) ?></td>
                            <td><?= controlhw_h($component['model']) ?></td>
                            <td><?= controlhw_h($component['capacity']) ?></td>
                            <td><?= controlhw_h($component['serial']) ?></td>
                            <td><?= controlhw_h(controlhw_component_details($component['details_json'] ?? null)) ?></td>
                            <td><?= controlhw_h($component['slot']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong>Historial de inventarios</strong> <span class="text-muted">(últimos 100)</span></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Fecha</th><th>Equipo</th><th>Componentes</th><th>Cambios en este inventario</th><th></th></tr></thead>
                <tbody>
                <?php if ($historyRows === []): ?>
                    <tr><td colspan="5" class="text-center text-muted p-4">Todavía no hay inventarios auditados. El primer inventario completo de cada equipo crea su línea base.</td></tr>
                <?php else: foreach ($historyRows as $row): ?>
                    <tr>
                        <td><?= controlhw_h($row['date_creation']) ?></td>
                        <td><?= controlhw_h($row['computer_name']) ?></td>
                        <td><?= controlhw_h($row['components_count']) ?></td>
                        <td><?= controlhw_h($row['changes_count']) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="?computer_id=<?= (int) $row['computers_id'] ?>">Ver equipo</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Detalle de cambios detectados</strong> <span class="text-muted">(últimos 200)</span></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Fecha</th><th>Equipo</th><th>Componente</th><th>Cambio</th><th>Antes</th><th>Después</th><th>Telegram</th></tr></thead>
                <tbody>
                <?php if ($alerts === []): ?>
                    <tr><td colspan="7" class="text-center text-muted p-4">No hay cambios que coincidan con los filtros seleccionados.</td></tr>
                <?php else: foreach ($alerts as $alert):
                    $old = json_decode((string) $alert['old_data'], true) ?: [];
                    $new = json_decode((string) $alert['new_data'], true) ?: [];
                    $describe = static function (array $data): string {
                        $details = is_array($data['details'] ?? null) ? $data['details'] : [];
                        return implode(' · ', array_filter([
                            $data['manufacturer'] ?? null,
                            $data['model'] ?? null,
                            $data['capacity'] ?? null,
                            !empty($data['serial']) ? 'S/N ' . $data['serial'] : null,
                            !empty($data['slot']) ? 'Ubicación ' . $data['slot'] : null,
                            !empty($details['memory_type']) ? 'Tipo ' . $details['memory_type'] : null,
                            !empty($details['frequency_mhz']) ? $details['frequency_mhz'] . ' MHz' : null,
                            !empty($details['disk_type']) ? $details['disk_type'] : null,
                            !empty($details['interface_type']) ? $details['interface_type'] : null,
                        ]));
                    };
                    $changeLabels = ['added' => 'Agregado', 'removed' => 'Retirado', 'modified' => 'Modificado', 'replaced' => 'Reemplazado'];
                    $statusLabels = ['queued' => 'Encolado en FML Telegram', 'pending' => 'Pendiente de envío'];
                ?>
                    <tr>
                        <td><?= controlhw_h($alert['date_creation']) ?></td>
                        <td><a href="?computer_id=<?= (int) $alert['computers_id'] ?>"><?= controlhw_h($alert['computer_name']) ?></a></td>
                        <td><?= controlhw_h($alert['component_type'] === 'ram' ? 'RAM' : 'Disco') ?></td>
                        <td><?= controlhw_h($changeLabels[$alert['change_type']] ?? $alert['change_type']) ?></td>
                        <td><?= controlhw_h($describe($old) ?: '—') ?></td>
                        <td><?= controlhw_h($describe($new) ?: '—') ?></td>
                        <td>
                            <?= controlhw_h($statusLabels[$alert['notification_status']] ?? $alert['notification_status']) ?>
                            <?php if (!empty($alert['notification_error'])): ?>
                                <div class="small text-danger"><?= controlhw_h($alert['notification_error']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
Html::footer();
