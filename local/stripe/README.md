# Pagos presenciales

Administración del sitio → Plugins → Plugins locales → **Accesos por pago presencial**.
También se puede entrar desde **Stripe Dashboard**.

1. Busca el correo completo o nombre de usuario de una cuenta confirmada y no suspendida.
2. Revisa el nombre, correo y fechas; confirma **Otorgar un mes**.
3. Para renovar, usa **Agregar un mes**. Si queda tiempo presencial vigente, el mes se suma al vencimiento.
4. **Cancelar acceso presencial** pide confirmación y permite registrar un motivo. Cancela todas las
   mensualidades presenciales vigentes y anticipadas del usuario. El historial conserva al responsable y la fecha.

Un mes es un mes calendario en la zona horaria del sitio, conservando la hora local. Si el día no existe
en el mes siguiente, se usa su último día (31 de enero → 28/29 de febrero).

Se usa el mismo rol `student_suscriptor` y las mismas matrículas del plugin. Los bloqueos de Stripe
no anulan un pago presencial vigente. Cancelar o vencer el acceso presencial conserva una suscripción
de Stripe válida y las matrículas ajenas al plugin. No se generan cobros, reembolsos ni cancelaciones en Stripe.
Si Stripe no se puede verificar, se conserva temporalmente el acceso y la revisión queda pendiente para reintentar.

## Instalación y verificación

- Desplegar la actualización completa del plugin y ejecutar la actualización de Moodle
  (Administración del sitio → Notificaciones, o `php admin/cli/upgrade.php --non-interactive`).
- La versión `2026100900` crea `local_stripe_manual` y registra la tarea de vencimiento.
- Verificar que el cron de Moodle esté funcionando. La tarea **Vencer accesos por pagos presenciales**
  corre cada 15 minutos; el retiro por vencimiento puede tardar ese intervalo, más cualquier retraso del cron.
- Solo usuarios con `moodle/site:config` pueden registrar y cancelar pagos presenciales.
- Las pruebas se ejecutan en una instalación de pruebas de Moodle, nunca contra la base de producción:
  `vendor/bin/phpunit local/stripe/tests/`.
- Probar alta, renovación, cancelación con motivo, vencimiento, usuario con Stripe activo y fallo temporal
  de Stripe. El menú **Suscripción** del alumno muestra el vencimiento de su acceso presencial.

El registro es de acceso por pago presencial; no sustituye un comprobante fiscal ni registra importes.
