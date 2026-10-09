<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Suscripciones de Stripe';
$string['stripe'] = 'Stripe';
$string['stripe:manage'] = 'Gestionar suscripciones de Stripe';
$string['publishablekey'] = 'Clave Pública';
$string['publishablekey_desc'] = 'Tu clave pública de Stripe (comienza con pk_)';
$string['secretkey'] = 'Clave Secreta';
$string['secretkey_desc'] = 'Tu clave secreta de Stripe (comienza con sk_)';
$string['webhooksecret'] = 'Secreto de Firma del Webhook';
$string['webhooksecret_desc'] = 'Tu secreto de firma del webhook de Stripe (comienza con whsec_)';
$string['priceid'] = 'ID de Precio';
$string['priceid_desc'] = 'ID de precio de Stripe por defecto para suscripciones (comienza con price_)';
$string['settings'] = 'Configuración de Stripe';
$string['privacy:metadata'] = 'El plugin de Stripe no almacena ningún dato personal.';

$string['managebilling'] = 'Gestionar Suscripción';
$string['subscriptionmenu'] = 'Suscripción';
$string['taskreconcilesubscriptions'] = 'Conciliar suscripciones de Stripe';
$string['taskexpiremanualaccess'] = 'Vencer accesos por pagos presenciales';
$string['viewplans'] = 'Planes de Suscripción';
$string['subscribenow'] = 'Suscribirme';
$string['manualaccesstitle'] = 'Accesos por pago presencial';
$string['manualaccessdescription'] = 'Otorga, renueva o cancela un mes de acceso sin generar un cobro en Stripe.';
$string['manualgrantheading'] = 'Registrar pago presencial';
$string['manualuseridentifier'] = 'Correo o nombre de usuario';
$string['manualuserplaceholder'] = 'Correo o nombre de usuario';
$string['manualgrantbutton'] = 'Otorgar un mes';
$string['manualrenewalhelp'] = 'Si ya tiene acceso presencial vigente, el nuevo mes se agrega después del vencimiento actual.';
$string['manualusernotfound'] = 'Introduce el correo completo o nombre de usuario de una cuenta confirmada y no suspendida. Si varias cuentas comparten correo, usa el nombre de usuario.';
$string['manualgrantsuccess'] = 'Se otorgó acceso a {$a->user} hasta {$a->date}.';
$string['manualgrantfailed'] = 'No fue posible otorgar el acceso. Comprueba que la cuenta esté confirmada, no esté suspendida y que exista el rol de suscriptor. Puedes reintentar este formulario sin duplicar el pago.';
$string['manualcancelbutton'] = 'Cancelar acceso presencial';
$string['manualcancelconfirmtitle'] = 'Confirmar cancelación';
$string['manualcancelconfirm'] = 'Se cancelarán todas las mensualidades presenciales vigentes y anticipadas de esta persona. Si tiene una suscripción activa en Stripe, conservará el acceso. Esta acción no realiza reembolsos ni cancela cobros de Stripe.';
$string['manualcancelreason'] = 'Motivo (opcional)';
$string['manualcancelsuccess'] = 'El acceso presencial fue cancelado.';
$string['manualnothingtocancel'] = 'El usuario ya no tiene acceso presencial vigente.';
$string['manualhistory'] = 'Historial de accesos presenciales';
$string['manualnohistory'] = 'Todavía no hay pagos presenciales registrados.';
$string['manualstart'] = 'Inicio';
$string['manualend'] = 'Vencimiento';
$string['manualcreatedby'] = 'Registrado por';
$string['manualstatusactive'] = 'Activo';
$string['manualstatusexpired'] = 'Vencido';
$string['manualstatuscancelled'] = 'Cancelado';
$string['manualstatusscheduled'] = 'Mes anticipado';
$string['manualsearchbutton'] = 'Buscar usuario';
$string['manualrenewbutton'] = 'Agregar un mes';
$string['manualperiod'] = 'Inicio y vencimiento';
$string['manualcancellation'] = 'Cancelación';
$string['manualpending'] = 'Revisión de acceso pendiente';
$string['manualcancelpending'] = 'Se registró la cancelación presencial. No se pudo verificar Stripe; el acceso se conserva temporalmente y se revisará automáticamente de nuevo.';
$string['manualcancelretained'] = 'Se canceló el acceso presencial. La persona conserva el acceso por su suscripción de Stripe.';
$string['manualcancelfailed'] = 'No se pudo completar la revisión de la cancelación. Comprueba el historial antes de reintentar; las cancelaciones pendientes se revisan automáticamente.';
$string['manualalreadyprocessed'] = 'Este pago ya se había registrado. Consulta su estado en el historial.';
$string['manualbusy'] = 'Se está actualizando el acceso de esta persona. Reintenta en unos segundos.';
$string['manualyouraccess'] = 'Tu acceso por pago presencial';
$string['manualyouraccessuntil'] = 'Tu acceso presencial está vigente hasta {$a}. Para renovarlo, contacta a la administración.';
$string['manualstripebilling'] = 'Gestionar facturación de Stripe';
