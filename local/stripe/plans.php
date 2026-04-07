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
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin: 40px 0;
    max-width: 1200px;
}

.pricing-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.pricing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border-color: #0f6cbf;
}

.pricing-card.popular {
    border-color: #0f6cbf;
    border-width: 3px;
}

.pricing-card.popular::before {
    content: "MÁS POPULAR";
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: #0f6cbf;
    color: white;
    padding: 5px 20px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.plan-name {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #333;
}

.plan-price {
    font-size: 48px;
    font-weight: bold;
    color: #0f6cbf;
    margin: 20px 0;
}

.plan-currency {
    font-size: 24px;
    vertical-align: super;
}

.plan-period {
    font-size: 16px;
    color: #666;
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
    border-bottom: 1px solid #f0f0f0;
}

.plan-features li:before {
    content: "✓";
    color: #4caf50;
    font-weight: bold;
    margin-right: 10px;
}

.plan-btn {
    display: inline-block;
    background: #0f6cbf;
    color: white;
    padding: 15px 40px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: background 0.3s ease;
}

.plan-btn:hover {
    background: #0a5a9e;
    color: white;
    text-decoration: none;
}

.plan-savings {
    background: #4caf50;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    display: inline-block;
    margin-top: 10px;
}
</style>

<div class="container mt-4">
    <div class="text-center mb-4">
        <h2>Elige tu plan de suscripción</h2>
        <p class="lead">Accede a todo el contenido premium de Tatanganga</p>
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
