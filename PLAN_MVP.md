# MVP WhatsApp/Telegram – Plan de Cambios (1–10)

## Objetivo
Preparar el producto para salir al mercado como MVP de citas por WhatsApp/Telegram, con foco en multi‑negocio, seguridad, validaciones y una experiencia operativa mínima.

## Alcance (1–10)
1. **Multi‑negocio real**
   - Resolver `Business` por canal en webhooks.
   - WhatsApp: mapear por `phone_number_id`.
   - Telegram: mapear por `chat_id` o token asociado.
   - Messenger: mapear por `page_id`.

2. **Autorización y roles**
   - Policies para `Business` y `Appointment`.
   - Middleware en rutas del panel para garantizar acceso por dueño.

3. **Validación temporal**
   - Bloquear fechas pasadas.
   - Bloquear horarios anteriores a “ahora” cuando la fecha es hoy.

4. **Seguridad de webhooks**
   - Validar `verify_token` y `hub_mode` en verificación.
   - Validar firma/secret donde aplique (si el proveedor lo soporta).

5. **Entregabilidad y resiliencia de mensajes**
   - Manejo de errores y reintentos en envíos.
   - Log y métricas básicas de fallos por canal.

6. **Horarios configurables por negocio**
   - Guardar horario laboral y duración de slot en DB.
   - Usar configuración en `availableSlots`.

7. **Reprogramar/cancelar por chat**
   - Flujo conversacional para consultar y modificar turnos.
   - Confirmaciones y mensajes de estado.

8. **Plantillas de mensajes por negocio**
   - Textos editables: bienvenida, instrucciones de pago, confirmaciones.

9. **Gestión de pagos más clara**
   - Estado de pago explícito y motivo de rechazo.
   - Opción de reenviar comprobante.

10. **Panel con métricas mínimas**
    - KPIs: hoy, pendientes de pago, confirmados, cancelados.

## Fuera de alcance (por ahora)
- Cola/queue y workers dedicados.
- Observabilidad avanzada y alertas.
- Facturación y planes de suscripción.

## Próximos pasos sugeridos
1. Implementar 1–3 primero (multi‑negocio, autorización, validaciones).
2. Añadir tests para los nuevos flujos críticos.


 Operación y confiabilidad
  medios.
  13. Logs y alertas visibles para el admin (cuando fallan envíos o webhooks).
  14. Backups y migraciones controladas.

  15. Mensajes de consentimiento y políticas de uso (sobre todo si vas a usar
  WhatsApp Cloud API).
  16. Idioma por negocio y detección más robusta (hoy solo keywords).
