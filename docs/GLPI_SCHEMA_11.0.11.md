# Referencia de esquema GLPI 11.0.11

Fuente de referencia proporcionada para el proyecto:
https://github.com/glpi-project/glpi/blob/12.0/bugfixes/install/mysql/glpi-11.0.11-empty.sql

Este documento se basa en las definiciones SQL de GLPI 11.0.11 incluidas en esa ruta. Antes de desplegar en producción, comparar con la versión exacta instalada en Songa.

## Relación entre equipo y componente

Las tablas de relación son la fuente principal de los componentes instalados en un activo:

### Memoria RAM

`glpi_items_devicememories`

Campos relevantes:
- `id`: identificador de la relación.
- `items_id`: ID del activo.
- `itemtype`: tipo del activo; para equipos será `Computer`.
- `devicememories_id`: referencia al catálogo de memorias.
- `size`: tamaño reportado para ese módulo.
- `serial`: número de serie del módulo.
- `otherserial`: identificador alternativo.
- `busID`: ubicación/slot reportado.
- `is_deleted`: marca de borrado lógico.
- `is_dynamic`: indica relación dinámica procedente del inventario.

Catálogo: `glpi_devicememories`
- `designation`: designación/modelo.
- `frequence`: frecuencia.
- `manufacturers_id`: fabricante.
- `size_default`: capacidad predeterminada.
- `devicememorytypes_id`: tipo de memoria.
- `devicememorymodels_id`: modelo.

### Discos

`glpi_items_deviceharddrives`

Campos relevantes:
- `id`: identificador de la relación.
- `items_id`: ID del activo.
- `itemtype`: tipo del activo; para equipos será `Computer`.
- `deviceharddrives_id`: referencia al catálogo de discos.
- `capacity`: capacidad reportada para ese disco.
- `serial`: número de serie.
- `otherserial`: identificador alternativo.
- `busID`: ubicación/puerto reportado.
- `is_deleted`: marca de borrado lógico.
- `is_dynamic`: indica relación dinámica procedente del inventario.

Catálogo: `glpi_deviceharddrives`
- `designation`: designación/modelo.
- `rpm`: velocidad de rotación cuando aplica.
- `interfacetypes_id`: tipo de interfaz.
- `manufacturers_id`: fabricante.
- `capacity_default`: capacidad predeterminada.
- `deviceharddrivemodels_id`: modelo.
- `deviceharddrivetypes_id`: tipo de disco.

## Identificación del activo

La tabla `glpi_computers` contiene:
- `id`, `name`, `serial`, `otherserial`.
- `users_id`: usuario asignado.
- `users_id_tech`: técnico asignado.
- `last_inventory_update`: fecha de última actualización de inventario.
- `is_deleted`, `is_dynamic`.

## Consultas de inspección

Ejemplo de RAM de un equipo:

```sql
SELECT
    im.items_id,
    im.itemtype,
    im.id AS relation_id,
    im.size,
    im.serial,
    im.otherserial,
    im.busID,
    im.is_deleted,
    im.is_dynamic,
    dm.designation,
    dm.frequence,
    dm.manufacturers_id
FROM glpi_items_devicememories im
LEFT JOIN glpi_devicememories dm
    ON dm.id = im.devicememories_id
WHERE im.itemtype = 'Computer'
  AND im.items_id = 1
  AND im.is_deleted = 0;
```

Ejemplo de discos de un equipo:

```sql
SELECT
    ih.items_id,
    ih.itemtype,
    ih.id AS relation_id,
    ih.capacity,
    ih.serial,
    ih.otherserial,
    ih.busID,
    ih.is_deleted,
    ih.is_dynamic,
    dh.designation,
    dh.rpm,
    dh.manufacturers_id,
    dh.interfacetypes_id,
    dh.deviceharddrivetypes_id
FROM glpi_items_deviceharddrives ih
LEFT JOIN glpi_deviceharddrives dh
    ON dh.id = ih.deviceharddrives_id
WHERE ih.itemtype = 'Computer'
  AND ih.items_id = 1
  AND ih.is_deleted = 0;
```

Sustituir `1` por el ID real del equipo al ejecutar la inspección.

## Reglas de lectura para Control HW

1. Filtrar siempre por `itemtype = 'Computer'` y `items_id = <ID>`.
2. Excluir relaciones con `is_deleted = 1`.
3. No asumir que el ID de catálogo (`devicememories_id` o `deviceharddrives_id`) es una identidad física estable: el inventario puede crear o reutilizar registros de catálogo.
4. Priorizar número de serie físico normalizado cuando sea válido.
5. Si el número de serie no existe, formar una huella compuesta con fabricante, designación, capacidad y slot/interfaz disponibles.
6. Comparar multiconjuntos de componentes, no solo la suma de RAM ni la capacidad total de discos. Así se detectan reemplazos de igual capacidad.
7. Registrar por separado la capacidad por módulo/unidad y el total del equipo.
8. No crear la fotografía posterior hasta confirmar que el inventario terminó de actualizar las relaciones. El hook de captura debe validarse en el flujo real de GLPI 11 antes de activarlo.

## Nota sobre versiones

El archivo de referencia se denomina `glpi-11.0.11-empty.sql`. La ruta está alojada en la rama `12.0/bugfixes`, pero el esquema descrito corresponde al dump de GLPI 11.0.11. No asumir que todos los parches 11.x o instalaciones con migraciones locales tienen idéntico esquema.
