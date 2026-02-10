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
 * Stripe cancel page.
 *
 * @package    paygw_stripe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/payment/gateway/stripe/cancel.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title(get_string('pluginname', 'paygw_stripe'));

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('paymentcancelled', 'paygw_stripe'), 'notifywarning');
echo '<p><a href="' . $CFG->wwwroot . '">' . get_string('continue') . '</a></p>';
echo $OUTPUT->footer();
