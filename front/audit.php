<?php

use GlpiPlugin\\Controlhw\\AuditMenu;
use Html;

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

$search = trim((string) ($_GET['search'] ?? ''));
$changeType = (string) ($_GET['change_type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$dateFrom = controlhw_date_filter((string) ($_GET['date_from'] ?? ''));
$dateTo = controlhw_date_filter((string) ($_GET['date_to'] ?? ''));
$computerId = max(0, (int) ($_GET['computer_id'] ?? 0));

$allowedChanges = ['added', 'removed', 'modified', 'replaced'];
$allowedStatuses = ['queued', 'pending', 'sent', 'error'];
if (!in_array($changeType, $allowedChanges, true)) {
    $changeType = '';
}
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$where = ['1 = 1'];
if ($search !== '') {
    $needle = addslashes($search);
    $where[] = "(c.name LIKE '%{$needle}%'
        OR EXISTS (
            SELECT 1 FROM glpi_plugin_controlhw_components cx
            INNER JOIN glpi_plugin_controlhw_snapshots sx ON sx.id = cx.snapshots_id
            WHERE sx.computers_id = c.id
              AND (cx.serial LIKE '%{$needle}%' OR cx.model LIKE '%{$needle}%'
                   OR cx.manufacturer LIKE '%{$needle}%')
        ))";
}
if ($dateFrom !== '') {
    $where[] = "s.date_creation >= '" . addslashes($dateFrom) . " 00:00:00'";
}
if ($dateTo !== '') {
    $where[] = "s.date_creation <= '" . addslashes($dateTo) . " 23:59:59'";
}
$whereSql = implode(' AND ', $where);

$stats = [
    'computers' => 0,
    'snapshots' => 0,
    'alerts' => 0,
    'pending' => 0,
];
foreach ($DB->request(
    "SELECT
        (SELECT COUNT(DISTINCT computers_id) FROM glpi_plugin_controlhw_snapshots) AS computers,
        (SELECT COUNT(*) FROM glpi_plugin_controlhw_snapshots) AS snapshots,
        (SELECT COUNT(*) FROM glpi_plugin_controlhw_alerts) AS alerts,
        (SELECT COUNT(*) FROM glpi_plugin_controlhw_alerts WHERE notification_status = 'pending') AS pending"
) as $row) {
    $stats = array_map('intval', $row);
}

$historyRows = [];
foreach ($DB->request(
    "SELECT s.id, s.computers_id, c.name AS computer_name, s.components_count,
            s.date_creation,
            (SELECT COUNT(*) FROM glpi_plugin_controlhw_alerts a
             WHERE a.snapshots_id = s.id) AS changes_count
     FROM glpi_plugin_controlhw_snapshots s
     INNER JOIN glpi_computers c ON c.id = s.computers_id
     WHERE {$whereSql}
     ORDER BY s.date_creation DESC, s.id DESC
     LIMIT 100"
) as $row) {
    $historyRows[] = $row;
}

$alertWhere = ['1 = 1'];
if ($computerId > 0) {
    $alertWhere[] = 'a.computers_id = ' . $computerId;
}
if ($search !== '') {
    $needle = addslashes($search);
    $alertWhere[] = "(c.name LIKE '%{$needle}%'
        OR a.old_data LIKE '%{$needle}%'
        OR a.new_data LIKE '%{$needle}%')";
}
if ($changeType !== '') {
    $alertWhere[] = "a.change_type = '" . addslashes($changeType) . "'";
}
if ($status !== '') {
    $alertWhere[] = "a.notification_status = '" . addslashes($status) . "'";
}
if ($dateFrom !== '') {
    $alertWhere[] = "a.date_creation >= '" . addslashes($dateFrom) . " 00:00:00'";
}
if ($dateTo !== '') {
    $alertWhere[] = "a.date_creation <= '" . addslashes($dateTo) . " 23:59:59'";
}
$alertWhereSql = implode(' AND ', $alertWhere);

$alerts = [];
foreach ($DB->request(
    "SELECT a.id, a.computers_id, c.name AS computer_name, a.component_type,
            a.change_type, a.old_data, a.new_data, a.notification_status,
            a.notification_error, a.date_creation
     FROM glpi_plugin_controlhw_alerts a
     INNER JOIN glpi_computers c ON c.id = a.computers_id
     WHERE {$alertWhereSql}
     ORDER BY a.date_creation DESC, a.id DESC
     LIMIT 200"
) as $row) {
    $alerts[] = $row;
}

$selectedComputer = null;
$latestComponents = [];
if ($computerId > 0) {
    $computer = new Computer();
    if ($computer->getFromDB($computerId)) {
        $selectedComputer = $computer->fields;
        $latestSnapshotId = 0;
        foreach ($DB->request(
            "SELECT id FROM glpi_plugin_controlhw_snapshots
             WHERE computers_id = {$computerId} AND is_complete = 1
             ORDER BY id DESC LIMIT 1"
        ) as $snapshot) {
            $latestSnapshotId = (int) $snapshot['id'];
        }
        if ($latestSnapshotId > 0) {
            foreach ($DB->request(
                "SELECT component_type, serial, manufacturer, model, capacity, slot, details_json
                 FROM glpi_plugin_controlhw_components
                 WHERE snapshots_id = {$latestSnapshotId}
                 ORDER BY component_type, slot, model"
            ) as $component) {
                $latestComponents[] = $component;
            }
        }
    }
}

Html::header(__('Control HW - Auditoría', 'controlhw'), $_SERVER['PHP_SELF'], 'tools', 'controlhw');
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
                    <?php foreach (['queued' => 'Encolado', 'pending' => 'Pendiente', 'sent' => 'Enviado', 'error' => 'Error'] as $key => $label): ?>
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
                    <thead><tr><th>Tipo</th><th>Fabricante</th><th>Modelo</th><th>Capacidad</th><th>Serie</th><th>Slot / conexión</th></tr></thead>
                    <tbody>
                    <?php if ($latestComponents === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No hay componentes registrados en el último inventario.</td></tr>
                    <?php else: foreach ($latestComponents as $component): ?>
                        <tr>
                            <td><?= controlhw_h($component['component_type'] === 'ram' ? 'Memoria RAM' : 'Disco') ?></td>
                            <td><?= controlhw_h($component['manufacturer']) ?></td>
                            <td><?= controlhw_h($component['model']) ?></td>
                            <td><?= controlhw_h($component['capacity']) ?></td>
                            <td><?= controlhw_h($component['serial']) ?></td>
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
                        return implode(' · ', array_filter([
                            $data['manufacturer'] ?? null,
                            $data['model'] ?? null,
                            $data['capacity'] ?? null,
                            !empty($data['serial']) ? 'S/N ' . $data['serial'] : null,
                        ]));
                    };
                    $changeLabels = ['added' => 'Agregado', 'removed' => 'Retirado', 'modified' => 'Modificado', 'replaced' => 'Reemplazado'];
                    $statusLabels = ['queued' => 'Encolado', 'pending' => 'Pendiente', 'sent' => 'Enviado', 'error' => 'Error'];
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
