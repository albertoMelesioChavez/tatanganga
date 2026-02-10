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
 * External functions and service definitions for the Stripe payment gateway plugin.
 *
 * @package    paygw_stripe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_stripe_get_config_for_js' => [
        'classname'   => 'paygw_stripe\external\get_config_for_js',
        'classpath'   => '',
        'description' => 'Returns the configuration settings to be used in js',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'paygw_stripe_create_checkout' => [
        'classname'   => 'paygw_stripe\external\create_checkout',
        'classpath'   => '',
        'description' => 'Creates a Stripe Checkout Session and returns the redirect URL.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
