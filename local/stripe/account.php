<?php
// User-facing subscription account page.

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/stripe/account.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('subscriptionaccount', 'local_stripe'));
$PAGE->set_heading(get_string('subscriptionaccount', 'local_stripe'));

$hasaccess = local_stripe_user_has_suscriptor_role($USER->id);
$customerid = get_user_preferences('local_stripe_customer_id', null, $USER->id);
$hascustomer = !empty($customerid);

if ($hasaccess) {
    $status = get_string('subscriptionactive', 'local_stripe');
    $description = get_string('subscriptionactive_desc', 'local_stripe');
    $statusclass = 'badge-success';
    $statusicon = 'fa-check-circle';
} elseif ($hascustomer) {
    $status = get_string('subscriptionattention', 'local_stripe');
    $description = get_string('subscriptionattention_desc', 'local_stripe');
    $statusclass = 'badge-warning';
    $statusicon = 'fa-exclamation-circle';
} else {
    $status = get_string('subscriptioninactive', 'local_stripe');
    $description = get_string('subscriptioninactive_desc', 'local_stripe');
    $statusclass = 'badge-secondary';
    $statusicon = 'fa-circle-o';
}

$templatecontext = [
    'title' => get_string('subscriptionaccount', 'local_stripe'),
    'status' => $status,
    'description' => $description,
    'statusclass' => $statusclass,
    'statusicon' => $statusicon,
    'hasaccess' => $hasaccess,
    'hascustomer' => $hascustomer,
    'canmanage' => $hascustomer,
    'billingmissing' => $hasaccess && !$hascustomer,
    'billingmissingmessage' => get_string('subscriptionbillingmissing', 'local_stripe'),
    'manageurl' => (new moodle_url('/local/stripe/portal.php'))->out(false),
    'managelabel' => get_string('manageinstripe', 'local_stripe'),
    'managedescription' => get_string('manageinstripe_desc', 'local_stripe'),
    'secureportal' => get_string('securestripeportal', 'local_stripe'),
    'plansurl' => (new moodle_url('/local/stripe/plans.php'))->out(false),
    'planslabel' => get_string('viewplans', 'local_stripe'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_stripe/subscription_account', $templatecontext);
echo $OUTPUT->footer();
