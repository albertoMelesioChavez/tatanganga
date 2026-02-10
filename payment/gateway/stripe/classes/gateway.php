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
 * Contains class for Stripe payment gateway.
 *
 * @package    paygw_stripe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_stripe;

/**
 * The gateway class for Stripe payment gateway.
 *
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Returns the list of currencies supported by Stripe.
     *
     * @return string[]
     */
    public static function get_supported_currencies(): array {
        return [
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'BRL', 'CHF', 'CZK', 'DKK', 'HKD',
            'HUF', 'INR', 'JPY', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK',
            'SGD', 'THB', 'TRY', 'TWD',
        ];
    }

    /**
     * Configuration form for the gateway instance.
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'apikey', get_string('apikey', 'paygw_stripe'));
        $mform->setType('apikey', PARAM_TEXT);
        $mform->addHelpButton('apikey', 'apikey', 'paygw_stripe');

        $mform->addElement('text', 'publishablekey', get_string('publishablekey', 'paygw_stripe'));
        $mform->setType('publishablekey', PARAM_TEXT);
        $mform->addHelpButton('publishablekey', 'publishablekey', 'paygw_stripe');

        $mform->addElement('text', 'subscriptionroleid', get_string('subscriptionroleid', 'paygw_stripe'));
        $mform->setType('subscriptionroleid', PARAM_INT);
        $mform->addHelpButton('subscriptionroleid', 'subscriptionroleid', 'paygw_stripe');

        $mform->addElement('text', 'subscriptioncourseid', get_string('subscriptioncourseid', 'paygw_stripe'));
        $mform->setType('subscriptioncourseid', PARAM_INT);
        $mform->addHelpButton('subscriptioncourseid', 'subscriptioncourseid', 'paygw_stripe');

        $options = [
            'live' => get_string('live', 'paygw_stripe'),
            'test' => get_string('test', 'paygw_stripe'),
        ];
        $mform->addElement('select', 'environment', get_string('environment', 'paygw_stripe'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_stripe');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
                                                 \stdClass $data, array $files, array &$errors): void {
        if ($data->enabled && (empty($data->apikey) || empty($data->publishablekey))) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
    }
}
