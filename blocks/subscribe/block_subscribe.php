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
 * Block subscribe.
 *
 * @package    block_subscribe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_subscribe extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_subscribe');
    }

    public function get_content() {
        global $USER, $DB, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $config = block_subscribe_get_subscription_config();
        if (empty($config['subscriptionroleid']) || empty($config['subscriptioncourseid'])) {
            return $this->content;
        }

        $roleid = (int) $config['subscriptionroleid'];
        $syscontext = context_system::instance();
        $subscribed = user_has_role_assignment($USER->id, $roleid, $syscontext->id);

        if ($subscribed) {
            $this->content->text = get_string('subscribed', 'block_subscribe');
            return $this->content;
        }

        $courseid = (int) $config['subscriptioncourseid'];
        $instance = $DB->get_record('enrol', ['enrol' => 'fee', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED]);
        if (!$instance) {
            $url = new moodle_url('/enrol/index.php', ['id' => $courseid]);
            $this->content->text = html_writer::div(
                html_writer::tag('p', get_string('subscribe_prompt', 'block_subscribe')) .
                html_writer::link($url, get_string('subscribe', 'block_subscribe'), ['class' => 'btn tt-subscribe-btn']),
                'tt-subscribe-block'
            );
            return $this->content;
        }

        $PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');
        $description = get_string('subscribe_prompt', 'block_subscribe');
        $params = core_payment\helper::gateways_modal_link_params('enrol_fee', 'fee', (int) $instance->id, $description);
        $params['class'] = 'btn tt-subscribe-btn';
        $button = html_writer::tag('a', get_string('subscribe', 'block_subscribe'), $params);

        $this->content->text = html_writer::div(
            html_writer::tag('p', get_string('subscribe_prompt', 'block_subscribe')) . $button,
            'tt-subscribe-block'
        );

        return $this->content;
    }
}

/**
 * Fetch Stripe gateway config for subscription role/course.
 *
 * @return array
 */
function block_subscribe_get_subscription_config(): array {
    $records = core_payment\account_gateway::get_records(['gateway' => 'stripe', 'enabled' => 1]);
    foreach ($records as $record) {
        $config = $record->get_configuration();
        if (!empty($config['subscriptionroleid'])) {
            return $config;
        }
    }
    return [];
}
