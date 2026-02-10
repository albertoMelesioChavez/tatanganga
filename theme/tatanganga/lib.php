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
 * Theme functions.
 *
 * @package    theme_tatanganga
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_tatanganga_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';

    // Use boost default preset as base.
    $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');

    // Append our custom SCSS.
    $scss .= file_get_contents($CFG->dirroot . '/theme/tatanganga/scss/custom.scss');

    return $scss;
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_tatanganga_get_extra_scss($theme) {
    return '';
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_tatanganga_get_pre_scss($theme) {
    // Override Boost's default blue variables BEFORE compilation.
    $pre = '
        $primary:       #333333 !default;
        $secondary:     #f0f0f0 !default;
        $success:       #4caf50 !default;
        $info:          #666666 !default;
        $warning:       #c4a265 !default;
        $danger:        #a83232 !default;
        $light:         #f8f8f8 !default;
        $dark:          #1a1a1a !default;

        $body-bg:       #f5f5f5;
        $body-color:    #333333;
        $link-color:    #333333;
        $navbar-light-color: #333333;
    ';
    return $pre;
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_tatanganga_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage' ||
        $filearea === 'loginbackgroundimage')) {
        $theme = theme_config::load('tatanganga');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}

/**
 * Render subscription status in the navbar.
 *
 * @param renderer_base $renderer
 * @return string
 */
function theme_tatanganga_render_navbar_output(renderer_base $renderer): string {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $config = theme_tatanganga_get_subscription_config();
    if (empty($config['subscriptionroleid'])) {
        return '';
    }

    $roleid = (int) $config['subscriptionroleid'];
    $syscontext = \context_system::instance();
    $subscribed = user_has_role_assignment($USER->id, $roleid, $syscontext->id);

    $dot = $subscribed
        ? \html_writer::tag('span', '', ['class' => 'tt-subscription-dot', 'title' => get_string('subscribed', 'theme_tatanganga')])
        : '';

    $cta = '';
    if (!$subscribed && !empty($config['subscriptioncourseid'])) {
        $url = new \moodle_url('/enrol/index.php', ['id' => (int) $config['subscriptioncourseid']]);
        $cta = \html_writer::link($url, get_string('subscribe', 'theme_tatanganga'), ['class' => 'btn tt-subscribe-btn']);
    }

    return \html_writer::div($dot . $cta, 'tt-subscription-status d-flex align-items-center gap-2 me-2');
}

/**
 * Fetch Stripe gateway config for subscription role/course.
 *
 * @return array
 */
function theme_tatanganga_get_subscription_config(): array {
    $records = \core_payment\account_gateway::get_records(['gateway' => 'stripe', 'enabled' => 1]);
    foreach ($records as $record) {
        $config = $record->get_configuration();
        if (!empty($config['subscriptionroleid'])) {
            return $config;
        }
    }
    return [];
}
