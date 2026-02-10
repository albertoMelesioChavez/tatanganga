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
 * Language strings for paygw_stripe.
 *
 * @package    paygw_stripe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Stripe';
$string['pluginname_desc'] = 'The Stripe plugin allows you to receive payments via Stripe.';
$string['gatewayname'] = 'Stripe';
$string['gatewaydescription'] = 'Stripe is an authorised payment gateway provider for processing credit card transactions.';
$string['apikey'] = 'API key';
$string['apikey_help'] = 'The secret API key from your Stripe dashboard.';
$string['publishablekey'] = 'Publishable key';
$string['publishablekey_help'] = 'The publishable key from your Stripe dashboard.';
$string['subscriptionroleid'] = 'Subscription role ID';
$string['subscriptionroleid_help'] = 'Role ID to assign to users after a successful Stripe payment (site-wide subscription).';
$string['subscriptioncourseid'] = 'Subscription course ID';
$string['subscriptioncourseid_help'] = 'Optional course ID to link users to the subscription page.';
$string['live'] = 'Live';
$string['test'] = 'Test';
$string['environment'] = 'Environment';
$string['environment_help'] = 'You can set this to Test if you are using Stripe test keys.';
$string['paymentsuccessful'] = 'Payment was successful.';
$string['paymentcancelled'] = 'Payment was cancelled.';
$string['paymentpending'] = 'Payment is being processed...';
$string['internalerror'] = 'An internal error has occurred. Please contact us.';
$string['errorcreatingcheckout'] = 'Error creating Stripe checkout session.';
$string['privacy:metadata'] = 'The Stripe plugin stores Stripe session IDs linked to Moodle payments.';
