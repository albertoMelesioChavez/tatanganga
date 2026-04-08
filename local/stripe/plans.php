<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/stripe/plans.php'));
$PAGE->set_title('Planes de Suscripción');
$PAGE->set_heading('Planes de Suscripción');

// Check if user already has subscription
$roleid = local_stripe_get_suscriptor_role_id();
$context = context_system::instance();
$has_subscription = user_has_role_assignment($USER->id, $roleid, $context->id);

if ($has_subscription) {
    redirect(new moodle_url('/'), 'Ya tienes una suscripción activa', null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

?>

<style>
.pricing-plans {
    display: flex;
    gap: 20px;
    margin: 40px auto;
    max-width: 1400px;
    overflow-x: auto;
    padding: 10px;
}

.pricing-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #d4af37;
    border-radius: 15px;
    padding: 30px 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    min-width: 280px;
    flex: 1;
    box-shadow: 0 8px 20px rgba(212, 175, 55, 0.2);
}

.pricing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(212, 175, 55, 0.4);
    border-color: #ffd700;
}

.pricing-card.popular {
    border-color: #ffd700;
    border-width: 3px;
    background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%);
}

.pricing-card.popular::before {
    content: "MÁS POPULAR";
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(90deg, #d4af37 0%, #ffd700 100%);
    color: #1a1a2e;
    padding: 5px 20px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    box-shadow: 0 4px 10px rgba(212, 175, 55, 0.3);
}

.plan-name {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #ffd700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.plan-price {
    font-size: 48px;
    font-weight: bold;
    color: #d4af37;
    margin: 20px 0;
    text-shadow: 0 2px 8px rgba(212, 175, 55, 0.5);
}

.plan-currency {
    font-size: 24px;
    vertical-align: super;
}

.plan-period {
    font-size: 16px;
    color: #c0c0c0;
    display: block;
    margin-top: 5px;
}

.plan-features {
    list-style: none;
    padding: 0;
    margin: 30px 0;
    text-align: left;
}

.plan-features li {
    padding: 10px 0;
    border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    color: #e0e0e0;
}

.plan-features li:before {
    content: "✓";
    color: #d4af37;
    font-weight: bold;
    margin-right: 10px;
}

.plan-btn {
    display: inline-block;
    background: linear-gradient(90deg, #d4af37 0%, #ffd700 100%);
    color: #1a1a2e;
    padding: 15px 40px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
}

.plan-btn:hover {
    background: linear-gradient(90deg, #ffd700 0%, #d4af37 100%);
    color: #1a1a2e;
    text-decoration: none;
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.5);
}

.plan-savings {
    background: linear-gradient(90deg, #d4af37 0%, #ffd700 100%);
    color: #1a1a2e;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    display: inline-block;
    margin-top: 10px;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
}
</style>

<div class="container mt-4">
    <div class="text-center mb-4">
        <h2 style="color: #d4af37; text-shadow: 0 2px 8px rgba(212, 175, 55, 0.5); font-weight: bold;">Elige tu plan de suscripción</h2>
        <p class="lead" style="color: #c0c0c0;">Accede a todo el contenido premium de Tatanganga</p>
    </div>

    <div class="pricing-plans">
        
        <!-- Plan Mensual MXN -->
        <div class="pricing-card popular">
            <div class="plan-name">Mensual</div>
            <div class="plan-price">
                <span class="plan-currency">$</span>850
                <span class="plan-period">MXN / mes</span>
            </div>
            <ul class="plan-features">
                <li>Acceso completo a todos los cursos</li>
                <li>Grabaciones de clases en vivo</li>
                <li>Recursos descargables</li>
                <li>Acceso a la comunidad</li>
                <li>Soporte prioritario</li>
            </ul>
            <a href="<?php echo new moodle_url('/local/stripe/subscribe.php', ['plan' => 'mxn_monthly']); ?>" class="plan-btn">
                Suscribirme
            </a>
        </div>

        <!-- Plan Anual MXN -->
        <div class="pricing-card">
            <div class="plan-name">Anual</div>
            <div class="plan-price">
                <span class="plan-currency">$</span>8,500
                <span class="plan-period">MXN / año</span>
            </div>
            <div class="plan-savings">Ahorra $1,700 MXN</div>
            <ul class="plan-features">
                <li>Acceso completo a todos los cursos</li>
                <li>Grabaciones de clases en vivo</li>
                <li>Recursos descargables</li>
                <li>Acceso a la comunidad</li>
                <li>Soporte prioritario</li>
            </ul>
            <a href="<?php echo new moodle_url('/local/stripe/subscribe.php', ['plan' => 'mxn_yearly']); ?>" class="plan-btn">
                Suscribirme
            </a>
        </div>

        <!-- Plan Mensual USD -->
        <div class="pricing-card">
            <div class="plan-name">Mensual USD</div>
            <div class="plan-price">
                <span class="plan-currency">$</span>48
                <span class="plan-period">USD / mes</span>
            </div>
            <ul class="plan-features">
                <li>Acceso completo a todos los cursos</li>
                <li>Grabaciones de clases en vivo</li>
                <li>Recursos descargables</li>
                <li>Acceso a la comunidad</li>
                <li>Soporte prioritario</li>
            </ul>
            <a href="<?php echo new moodle_url('/local/stripe/subscribe.php', ['plan' => 'usd_monthly']); ?>" class="plan-btn">
                Suscribirme
            </a>
        </div>

        <!-- Plan Anual USD -->
        <div class="pricing-card">
            <div class="plan-name">Anual USD</div>
            <div class="plan-price">
                <span class="plan-currency">$</span>500
                <span class="plan-period">USD / año</span>
            </div>
            <div class="plan-savings">Ahorra $76 USD</div>
            <ul class="plan-features">
                <li>Acceso completo a todos los cursos</li>
                <li>Grabaciones de clases en vivo</li>
                <li>Recursos descargables</li>
                <li>Acceso a la comunidad</li>
                <li>Soporte prioritario</li>
            </ul>
            <a href="<?php echo new moodle_url('/local/stripe/subscribe.php', ['plan' => 'usd_yearly']); ?>" class="plan-btn">
                Suscribirme
            </a>
        </div>

    </div>

    <div class="text-center mt-5">
        <p class="text-muted">
            <small>Todos los planes incluyen acceso completo a la plataforma. Puedes cancelar en cualquier momento.</small>
        </p>
    </div>
</div>

<?php

echo $OUTPUT->footer();
