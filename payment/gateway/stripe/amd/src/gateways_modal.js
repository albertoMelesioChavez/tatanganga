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
 * This module is responsible for Stripe content in the gateways modal.
 *
 * @module     paygw_stripe/gateways_modal
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';
import Notification from 'core/notification';

/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} description Description of the payment
 * @returns {Promise<string>}
 */
export const process = (component, paymentArea, itemId, description) => {
    return Ajax.call([{
        methodname: 'paygw_stripe_create_checkout',
        args: {
            component: component,
            paymentarea: paymentArea,
            itemid: itemId,
            description: description,
        },
    }])[0].then(result => {
        if (result.success && result.url) {
            window.location.href = result.url;
            return new Promise(() => {
                // Never resolves — we are redirecting.
            });
        }
        return getString('errorcreatingcheckout', 'paygw_stripe').then(str => {
            Notification.addNotification({
                message: str,
                type: 'error',
            });
            return Promise.reject(str);
        });
    });
};
