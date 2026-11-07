# Desarrollo y validación

## Punto de extensión confirmado: POST_INVENTORY

Para GLPI **11.0.11** se verificó el código fuente oficial:

- Glpi\Inventory\Request::inventory() ejecuta Inventory::doInventory() para procesar el inventario.
- Inventory::doInventory() procesa el equipo y sus componentes dentro de una transacción y confirma con $DB->commit().
- Después de que el inventario termina sin errores, Request::inventory() llama a Plugin::doHookFunction(Hooks::POST_INVENTORY, $this->inventory->getRawData()).
- La constante Glpi\Plugin\Hooks::POST_INVENTORY identifica el hook post_inventory. La documentación del núcleo indica que solo se ejecuta si el envío de inventario fue exitoso.

Por tanto, Control HW utilizará **POST_INVENTORY** como punto de entrada de la auditoría. El callback recibe el objeto de inventario original (no un objeto Computer). El plugin deberá resolver el equipo asociado al agente y leer las relaciones de RAM y discos ya persistidas.

Referencias del código oficial, tag 11.0.11:

- https://github.com/glpi-project/glpi/blob/11.0.11/src/Glpi/Inventory/Request.php
- https://github.com/glpi-project/glpi/blob/11.0.11/src/Glpi/Inventory/Inventory.php
- https://github.com/glpi-project/glpi/blob/11.0.11/src/Glpi/Plugin/Hooks.php

No se utilizará AUTOINVENTORY_INFORMATION, porque es un hook de presentación para mostrar información de inventario en formularios y no un evento de finalización.

## Condiciones de captura

1. Procesar solo inventarios de equipos Computer; ignorar descubrimientos de red y otros tipos de activo.
2. Resolver el equipo a partir del identificador del agente (deviceid) y su asociación nativa en GLPI.
3. Leer las relaciones de RAM y discos después de que GLPI haya confirmado el inventario.
4. Ignorar inventarios parciales para efectos de retiros: un inventario parcial no debe borrar componentes de la línea base ni generar alertas de retirada.
5. Ejecutar la captura de forma idempotente para tolerar reenvíos del mismo inventario.
6. No permitir que un error de Telegram revierta ni interrumpa el inventario de GLPI. Primero persistir auditoría; notificar después mediante cola.
7. No asumir que el callback se ejecuta una sola vez; deduplicar por equipo, huella de inventario y evento.

## Criterio crítico: integridad del snapshot

No se debe comparar la RAM o los discos mientras GLPI todavía está actualizando sus relaciones. La captura se realizará desde el hook posterior confirmado y solo después de validar que las relaciones están disponibles.

## Identificación de componentes

### RAM

Prioridad de identificación:
1. Número de serie del módulo, si está disponible y es válido.
2. Huella de fabricante, modelo, capacidad, tipo, velocidad y slot.

### Discos

Prioridad de identificación:
1. Número de serie físico, si está disponible y es válido.
2. Huella de fabricante, modelo, capacidad e interfaz/ubicación disponibles.

Los números de serie vacíos o genéricos (por ejemplo, cadenas de ceros o valores predeterminados del fabricante) no se tratarán como identificadores únicos.

## Casos de prueba mínimos

- Primer inventario completo: crea línea base sin alerta.
- RAM agregada.
- RAM retirada en inventario completo.
- RAM reemplazada por otra de igual capacidad.
- RAM reemplazada por otra de diferente capacidad.
- Disco agregado.
- Disco retirado en inventario completo.
- Disco sustituido por otro de igual capacidad.
- Disco sustituido por otro de diferente capacidad.
- Inventario sin cambios: no genera alerta.
- Inventario parcial: no genera falsas alertas de retiro.
- Reenvío del mismo inventario: no duplica alertas.
- Inventario rechazado o fallido: no crea snapshot.
- Telegram no disponible: conserva alerta y programa reintento.
- Dos inventarios consecutivos cercanos: mantiene el orden y la trazabilidad.
