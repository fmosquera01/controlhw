# Control HW – Auditoría de Hardware

Plugin independiente para GLPI 11 que audita cambios de memoria RAM y unidades de almacenamiento HDD/SSD/NVMe después de inventarios exitosos de GLPI Agent.

- **Identificador:** `controlhw`
- **Nombre:** Control HW – Auditoría de Hardware
- **Versión:** 0.3.1
- **Repositorio:** https://github.com/fmosquera01/controlhw
- **Rama:** `main`
- **Compatibilidad objetivo:** GLPI 11.0.11
- **Integración de avisos:** FML Telegram (plugin separado)

## Panel de auditoría

La versión 0.3.1 incorpora una página de consulta y registra el acceso al menú sin depender de la disponibilidad de permisos durante la inicialización del plugin en **Herramientas → Control HW - Auditoría**. Requiere que el usuario tenga permiso de lectura de equipos en GLPI.

- Resumen de equipos auditados, inventarios, cambios y notificaciones pendientes.
- Historial de los últimos 100 inventarios.
- Detalle de los últimos 200 cambios, con componente anterior y nuevo.
- Consulta del último inventario de componentes por equipo.
- Filtros por equipo, serie, modelo, fabricante, fechas, tipo de cambio y estado de Telegram.
- Enlace al formulario del equipo en GLPI.

La pantalla consulta las tablas de auditoría existentes; no elimina ni reinicia el historial almacenado.

## Detección

- Primer inventario completo: crea línea base sin alertar.
- Detecta RAM y discos agregados, retirados, sustituidos y modificados.
- Identifica preferentemente por número de serie; si no existe, usa ubicación/slot y una huella de atributos.
- Compara módulos/unidades individualmente, no solo la capacidad total.
- Ignora inventarios marcados como parciales para evitar falsas alertas de retirada.
- No vuelve a generar alertas si el inventario no presenta cambios.
- Persiste snapshot y auditoría antes de intentar notificar.

## Telegram

Control HW no almacena tokens ni implementa un bot propio. Consume la API pública de FML Telegram:

`GlpiPlugin\\Telegram\\NotificationService::enqueue()`

El evento emitido es:
- Origen: `controlhw`
- Evento: `hardware.changed`

En FML Telegram debe existir una suscripción activa para ese origen/evento y al menos un destino habilitado. FML Telegram conserva su propia cola, deduplicación y reintentos de entrega. Si una alerta queda pendiente porque el servicio o la suscripción no están disponibles, Control HW vuelve a intentar en el siguiente inventario exitoso de ese mismo equipo.

## Instalación de desarrollo

1. Copiar la carpeta del plugin como `plugins/controlhw`.
2. Instalar y habilitar **FML Telegram** antes de activar las suscripciones de avisos.
3. Instalar y habilitar Control HW desde la administración de plugins de GLPI.
4. Otorgar al perfil correspondiente permiso de lectura de equipos.
5. Entrar en **Herramientas → Control HW - Auditoría**. Si el menú ya estaba cargado en la sesión, cerrar y volver a iniciar sesión para regenerarlo.
6. Configurar en FML Telegram la suscripción `controlhw / hardware.changed`.
7. Ejecutar inventarios de prueba y revisar los registros de auditoría.

## Datos persistentes

Control HW crea únicamente tablas propias:
- `glpi_plugin_controlhw_snapshots`
- `glpi_plugin_controlhw_components`
- `glpi_plugin_controlhw_alerts`

Desinstalar el plugin no elimina el historial.

## Pruebas

Ejecutar el test independiente del comparador:

`php tests/ComponentComparatorTest.php`

La pantalla debe validarse en el GLPI de pruebas con perfiles de solo lectura y administración, datos vacíos y equipos con alertas antes de producción. No se ha ejecutado una prueba de instalación ni de interfaz en el GLPI del usuario.

## Estado

Versión 0.3.1: primera interfaz de consulta añadida al repositorio. La captura sigue conectada al hook `POST_INVENTORY` de GLPI 11.0.11. Debe validarse en el GLPI de pruebas con inventarios reales antes de producción.
