<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Stripe return page after successful checkout.
 *
 * @package    paygw_stripe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_stripe\stripe_helper;

require_login();

$component = required_param('component', PARAM_COMPONENT);
$paymentarea = required_param('paymentarea', PARAM_AREA);
$itemid = required_param('itemid', PARAM_INT);
$sessionid = required_param('session_id', PARAM_TEXT);

$config = (object) payment_helper::get_gateway_configuration($component, $paymentarea, $itemid, 'stripe');
$payable = payment_helper::get_payable($component, $paymentarea, $itemid);
$surcharge = payment_helper::get_gateway_surcharge('stripe');
$cost = payment_helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
$currency = $payable->get_currency();

$stripe = new stripe_helper($config->apikey);
$session = $stripe->get_checkout_session($sessionid);

$success = false;
$message = '';

if ($session && $session['payment_status'] === 'paid') {
    // Verify amount.
    $paidamount = $session['amount_total'] / 100; // Convert from cents.
    $paidcurrency = strtoupper($session['currency']);

    if ((float)$paidamount >= (float)$cost && $paidcurrency === $currency) {
        try {
            $paymentid = payment_helper::save_payment(
                $payable->get_account_id(),
                $component,
                $paymentarea,
                $itemid,
                (int) $USER->id,
                $cost,
                $currency,
                'stripe'
            );

            // Store Stripe session ID.
            $record = new \stdClass();
            $record->paymentid = $paymentid;
            $record->stripe_sessionid = $sessionid;
            $DB->insert_record('paygw_stripe', $record);

            payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $USER->id);
            $success = true;
            $message = get_string('paymentsuccessful', 'paygw_stripe');
        } catch (\Exception $e) {
            debugging('Exception while trying to process Stripe payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $message = get_string('internalerror', 'paygw_stripe');
        }
    } else {
        $message = 'Amount mismatch.';
    }
} else {
    $message = get_string('paymentpending', 'paygw_stripe');
}

$PAGE->set_url(new moodle_url('/payment/gateway/stripe/return.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title(get_string('pluginname', 'paygw_stripe'));

echo $OUTPUT->header();

if ($success) {
    echo $OUTPUT->notification($message, 'notifysuccess');
    // Redirect to the course or wherever the user came from.
    $url = payment_helper::get_success_url($component, $paymentarea, $itemid);
    echo '<script>setTimeout(function(){ window.location.href = "' . $url->out(false) . '"; }, 2000);</script>';
} else {
    echo $OUTPUT->notification($message, 'notifyproblem');
    echo '<p><a href="' . $CFG->wwwroot . '">' . get_string('continue') . '</a></p>';
}

echo $OUTPUT->footer();
