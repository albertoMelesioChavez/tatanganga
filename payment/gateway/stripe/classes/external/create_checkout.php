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
 * External function to create a Stripe Checkout Session.
 *
 * @package    paygw_stripe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_stripe\external;

use core_payment\helper;
use core_payment\helper as payment_helper;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use paygw_stripe\stripe_helper;

class create_checkout extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
            'description' => new external_value(PARAM_TEXT, 'Description of the payment'),
        ]);
    }

    /**
     * Creates a Stripe Checkout Session and returns the URL.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param string $description
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid, string $description): array {
        global $CFG, $USER;

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'description' => $description,
        ]);

        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'stripe');
        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = helper::get_gateway_surcharge('stripe');
        $cost = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
        $currency = $payable->get_currency();

        $successurl = $CFG->wwwroot . '/payment/gateway/stripe/return.php?component=' . $component .
            '&paymentarea=' . $paymentarea . '&itemid=' . $itemid . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelurl = $CFG->wwwroot . '/payment/gateway/stripe/cancel.php';

        $stripe = new stripe_helper($config->apikey);
        $session = $stripe->create_checkout_session(
            $cost,
            $currency,
            $description,
            $successurl,
            $cancelurl,
            [
                'component' => $component,
                'paymentarea' => $paymentarea,
                'itemid' => $itemid,
                'userid' => $USER->id,
            ]
        );

        if ($session && !empty($session['url'])) {
            return [
                'url' => $session['url'],
                'success' => true,
            ];
        }

        return [
            'url' => '',
            'success' => false,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_URL, 'Stripe Checkout URL'),
            'success' => new external_value(PARAM_BOOL, 'Whether the session was created successfully'),
        ]);
    }
}
