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
    position: relative;
    scroll-behavior: smooth;
}

.scroll-indicator {
    position: fixed;
    right: 30px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 32px;
    color: #c4a265;
    background: white;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 0 3px rgba(196, 162, 101, 0.3);
    animation: scrollHint 2s ease-in-out infinite;
    cursor: pointer;
    z-index: 1050;
    transition: all 0.3s ease;
}

.scroll-indicator:hover {
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4), 0 0 0 5px rgba(196, 162, 101, 0.5);
}

.scroll-indicator.hidden {
    opacity: 0;
    pointer-events: none;
}

@keyframes scrollHint {
    0%, 100% { 
        transform: translateY(-50%) translateX(0); 
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 0 3px rgba(196, 162, 101, 0.3);
    }
    50% { 
        transform: translateY(-50%) translateX(10px); 
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4), 0 0 0 5px rgba(196, 162, 101, 0.5);
    }
}

.pricing-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #c4a265;
    border-radius: 15px;
    padding: 30px 20px 20px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    min-width: 280px;
    flex: 1;
    box-shadow: 0 8px 20px rgba(196, 162, 101, 0.2);
    display: flex;
    flex-direction: column;
}

.pricing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(196, 162, 101, 0.4);
    border-color: #c4a265;
}

.pricing-card.popular {
    border-color: #c4a265;
    border-width: 3px;
    background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%);
}

.pricing-card.popular::before {
    content: "MÁS POPULAR";
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: #c4a265;
    color: #1a1a2e;
    padding: 5px 20px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    box-shadow: 0 4px 10px rgba(196, 162, 101, 0.3);
}

.plan-name {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 10px;
    color: #c4a265;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.plan-price {
    font-size: 48px;
    font-weight: bold;
    color: #c4a265;
    margin: 20px 0;
    text-shadow: 0 2px 8px rgba(196, 162, 101, 0.5);
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
    flex-grow: 1;
}

.plan-features li {
    padding: 10px 0;
    border-bottom: 1px solid rgba(196, 162, 101, 0.2);
    color: #e0e0e0;
}

.plan-features li:before {
    content: "✓";
    color: #c4a265;
    font-weight: bold;
    margin-right: 10px;
}

.plan-btn {
    display: inline-block;
    background: #c4a265;
    color: #1a1a2e;
    padding: 15px 40px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(196, 162, 101, 0.3);
    margin-top: auto;
}

.plan-btn:hover {
    background: #b89555;
    color: #1a1a2e;
    text-decoration: none;
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(196, 162, 101, 0.5);
}

.plan-savings {
    background: #c4a265;
    color: #1a1a2e;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 14px;
    display: inline-block;
    margin-top: 10px;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(196, 162, 101, 0.3);
}
</style>

<div class="container mt-4">
    <div class="text-center mb-4">
        <h2 style="color: #c4a265; text-shadow: 0 2px 8px rgba(196, 162, 101, 0.5); font-weight: bold;">Elige tu plan de suscripción</h2>
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

<div class="scroll-indicator" id="scrollIndicator">→</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const plansContainer = document.querySelector('.pricing-plans');
    const scrollIndicator = document.getElementById('scrollIndicator');
    
    if (!plansContainer || !scrollIndicator) return;
    
    // Check if scroll is needed
    function checkScroll() {
        const hasScroll = plansContainer.scrollWidth > plansContainer.clientWidth;
        const isAtEnd = plansContainer.scrollLeft + plansContainer.clientWidth >= plansContainer.scrollWidth - 10;
        
        if (!hasScroll || isAtEnd) {
            scrollIndicator.classList.add('hidden');
        } else {
            scrollIndicator.classList.remove('hidden');
        }
    }
    
    // Scroll to next card on click
    scrollIndicator.addEventListener('click', function() {
        const cardWidth = plansContainer.querySelector('.pricing-card').offsetWidth;
        plansContainer.scrollBy({
            left: cardWidth + 20,
            behavior: 'smooth'
        });
    });
    
    // Check scroll on load and scroll events
    checkScroll();
    plansContainer.addEventListener('scroll', checkScroll);
    window.addEventListener('resize', checkScroll);
});
</script>

<?php

echo $OUTPUT->footer();
