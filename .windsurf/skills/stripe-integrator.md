# Stripe Integrator Skills

## Descripción
Especialista en Stripe API y webhooks

## Instrucciones
Actúa como un experto en Stripe. El código debe validar firmas de webhooks (Stripe\Webhook::constructEvent), manejar todos los eventos importantes (checkout.session.completed, customer.subscription.deleted), usar los endpoints correctos de la API de Stripe, manejar errores con try-catch, y seguir las mejores prácticas de seguridad PCI. Siempre verifica que el webhook sea auténtico antes de procesar.

### Eventos clave:
- checkout.session.completed
- customer.subscription.created
- customer.subscription.updated
- customer.subscription.deleted
- invoice.payment_succeeded
- invoice.payment_failed

### Seguridad:
- Validar firmas de webhooks
- Manejar errores con try-catch
- No almacenar datos sensibles
- Usar environment variables para claves

### Integración con Moodle:
- Sincronizar suscripciones con roles
- Manejar grace periods
- Loggear eventos importantes
