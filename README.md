# Control HW – Auditoría de Hardware

Plugin independiente para GLPI 11 destinado a detectar cambios de memoria RAM y unidades de almacenamiento en los equipos inventariados por GLPI Agent.

## Alcance inicial

- Plataforma: GLPI 11.
- Identificador técnico: `controlhw`.
- Componentes supervisados: memoria RAM y discos HDD/SSD/NVMe.
- Momento de auditoría: después de cada inventario automático confirmado.
- Notificaciones: Telegram Bot API.
- Idioma de interfaz y notificaciones: español.
- Rama de desarrollo: `main`.

## Reglas funcionales

1. El primer inventario válido de cada equipo establece la línea base y no genera alertas.
2. Cada inventario posterior se compara con la última fotografía aceptada.
3. Se detectan componentes agregados, retirados y sustituidos, además de cambios de capacidad, fabricante o modelo.
4. La comparación prioriza números de serie; cuando no existan, utiliza una huella compuesta y registra el nivel de certeza.
5. Los cambios se guardan en auditoría antes de intentar notificar.
6. Un mismo evento no debe generar notificaciones duplicadas.
7. Los fallos de Telegram no eliminan la alerta; quedan pendientes de reintento.
8. El plugin no modifica tablas ni registros nativos de GLPI.

## Arquitectura prevista

- Captura: integración con el ciclo de inventario automático de GLPI.
- Normalización: conversión de los registros nativos de memoria y discos a una estructura estable.
- Snapshot: fotografía por equipo y fecha de inventario.
- Comparador: diferencias entre fotografías consecutivas.
- Auditoría: eventos inmutables con valores anteriores y nuevos.
- Notificaciones: cola persistente y envío HTTPS a Telegram.
- Interfaz: configuración, tablero, alertas, historial y pestaña por equipo.

## Tablas propias previstas

Todas usarán el prefijo `glpi_plugin_controlhw_`:

- `snapshots`: fotografías de inventario.
- `components`: componentes contenidos en cada fotografía.
- `alerts`: eventos de cambio detectados.
- `notifications`: intentos y estado de envío a Telegram.
- `configs`: configuración del plugin.

El diseño definitivo de columnas e índices se cerrará después de validar el esquema real de GLPI 11 y el orden de ejecución de los hooks durante una importación de GLPI Agent.

## Seguridad

- El token del bot nunca se mostrará en claro después de guardarlo.
- Se utilizará el mecanismo de cifrado compatible con la versión instalada de GLPI.
- Las solicitudes a Telegram usarán HTTPS y tiempos de espera.
- No se enviarán credenciales ni datos innecesarios del usuario final.
- Los permisos de configuración y consulta estarán separados.

## Plan de implementación

1. Estructura del plugin, compatibilidad y migraciones.
2. Captura de RAM y discos después de inventarios automáticos.
3. Línea base, normalización y comparación.
4. Registro de alertas y deduplicación.
5. Configuración y envío a Telegram con reintentos.
6. Tablero, pestañas e historial.
7. Pruebas con inventarios reales y casos de sustitución.

## Estado

Arquitectura inicial definida. La captura automática y el comparador se implementarán después de validar el flujo real de importación en la instalación GLPI 11 de destino.
