# Desarrollo y validación

## Criterio crítico: momento de captura

No se debe asumir que el evento de actualización del objeto Computer ocurre después de que GLPI haya terminado de importar todas sus relaciones de hardware.

Antes de habilitar auditoría en producción se debe verificar en GLPI 11:

1. Qué hook se ejecuta al terminar una importación automática.
2. Si las relaciones de RAM y discos ya están actualizadas cuando se ejecuta.
3. Si el hook se ejecuta una sola vez por inventario.
4. Cómo distinguir una importación de agente de una edición manual del equipo.
5. Cómo evitar registrar estados parciales durante una importación.

La implementación debe usar un punto de extensión posterior a la importación confirmado mediante prueba, no una consulta periódica que pueda capturar estados intermedios.

## Identificación de componentes

### RAM

Prioridad de identificación:
1. Número de serie del módulo, si está disponible.
2. Huella de fabricante, modelo, capacidad, tipo, velocidad y slot.

### Discos

Prioridad de identificación:
1. Número de serie.
2. Huella de fabricante, modelo, capacidad e interfaz.

Los números de serie vacíos o genéricos (por ejemplo, cadenas de ceros o valores predeterminados del fabricante) no se tratarán como identificadores únicos.

## Casos de prueba mínimos

- Primer inventario: crea línea base sin alerta.
- RAM agregada.
- RAM retirada.
- RAM reemplazada por otra de igual capacidad.
- RAM reemplazada por otra de diferente capacidad.
- Disco agregado.
- Disco retirado.
- Disco sustituido por otro de igual capacidad.
- Disco sustituido por otro de diferente capacidad.
- Inventario sin cambios: no genera alerta.
- Reenvío del mismo inventario: no duplica alertas.
- Telegram no disponible: conserva alerta y programa reintento.
- Dos inventarios consecutivos cercanos: mantiene el orden y la trazabilidad.
