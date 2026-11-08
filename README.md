# Control HW – Auditoría de Hardware

Plugin independiente para GLPI 11 que audita cambios de memoria RAM y unidades de almacenamiento HDD/SSD/NVMe después de inventarios exitosos de GLPI Agent.

- **Identificador:** `controlhw`
- **Nombre:** Control HW – Auditoría de Hardware
- **Repositorio:** https://github.com/fmosquera01/controlhw
- **Rama:** `main`
- **Compatibilidad objetivo:** GLPI 11.0.11
- **Integración de avisos:** FML Telegram (plugin separado)

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

En FML Telegram debe existir una suscripción activa para ese origen/evento y al menos un destino habilitado. FML Telegram conserva su propia cola, deduplicación y reintentos de entrega.

## Instalación de desarrollo

1. Copiar la carpeta del plugin como `plugins/controlhw`.
2. Instalar y habilitar **FML Telegram** antes de activar las suscripciones de avisos.
3. Instalar Control HW desde la administración de plugins de GLPI.
4. Configurar en FML Telegram la suscripción `controlhw / hardware.changed`.
5. Ejecutar inventarios de prueba y revisar los registros de auditoría.

## Datos persistentes

Control HW crea únicamente tablas propias:
- `glpi_plugin_controlhw_snapshots`
- `glpi_plugin_controlhw_components`
- `glpi_plugin_controlhw_alerts`

Desinstalar el plugin no elimina el historial.

## Pruebas

Ejecutar el test independiente del comparador:

`php tests/ComponentComparatorTest.php`

## Estado

Primera implementación funcional en desarrollo. La captura se conecta al hook `POST_INVENTORY` de GLPI 11.0.11, resuelve el agente mediante `deviceid` y lee las relaciones nativas de memoria y discos una vez confirmado el inventario. Debe validarse en el GLPI de pruebas con inventarios reales antes de producción.
