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

namespace local_calendario;

use core\hook\navigation\secondary_extend;
use core\hook\output\before_standard_top_of_body_html_generation;
use navigation_node;
use moodle_url;
use context_course;

/**
 * Hook callbacks for local_calendario.
 *
 * @package    local_calendario
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Add "Calendario" to the secondary navigation.
     *
     * @param secondary_extend $hook
     */
    public static function add_calendario_nav(secondary_extend $hook): void {
        // Disabled - calendar is now a dashboard block.
    }

    /**
     * Inject subscription banner for non-subscribers on course pages.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function inject_subscription_banner(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $USER, $DB, $COURSE;

        // Skip for admins.
        if (is_siteadmin()) {
            return;
        }

        // Check if user has student_suscriptor role in ANY course context.
        if (isloggedin() && !isguestuser()) {
            $suscriptorroleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
            if ($suscriptorroleid) {
                $hassuscriptor = $DB->record_exists('role_assignments', [
                    'roleid' => $suscriptorroleid,
                    'userid' => $USER->id,
                ]);
                if ($hassuscriptor) {
                    return;
                }
            }
        }

        // Custom Stripe subscription page.
        $payurl = new moodle_url('/local/stripe/index.php', ['start' => 1]);

        $html = '<div class="suscripcion-banner" id="suscripcion-banner" style="display:none">'
            . '<div class="suscripcion-banner-content">'
            . '<div class="suscripcion-banner-icon">🔓</div>'
            . '<div class="suscripcion-banner-text">'
            . '<strong>Desbloquea todo el contenido</strong>'
            . '<span>Suscríbete por $100 MXN/mes y accede a todas las clases, grabaciones y recursos exclusivos.</span>'
            . '</div>'
            . '<a href="' . $payurl->out(false) . '" class="btn suscripcion-banner-btn">Suscribirme ahora</a>'
            . '</div>'
            . '<button class="suscripcion-banner-toggle" id="suscripcion-toggle" title="Minimizar">▲</button>'
            . '</div>'
            . '<script>document.addEventListener("DOMContentLoaded",function(){'
            . 'var b=document.getElementById("suscripcion-banner");'
            . 'var t=document.getElementById("topofscroll")||document.getElementById("page");'
            . 'if(b&&t){t.prepend(b);b.style.display="";}'
            . 'var btn=document.getElementById("suscripcion-toggle");'
            . 'if(btn){btn.addEventListener("click",function(){'
            . 'b.classList.toggle("collapsed");'
            . 'btn.textContent=b.classList.contains("collapsed")?"▼":"▲";'
            . 'btn.title=b.classList.contains("collapsed")?"Expandir":"Minimizar";'
            . '});}'
            . '});</script>';

        $hook->add_html($html);

        // Inject course navigation buttons (only on course pages).
        if (isset($COURSE->id) && $COURSE->id > 1) {
            self::inject_course_nav_buttons($hook);
        }
    }

    /**
     * Inject previous/next course navigation buttons at the bottom of course pages.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    private static function inject_course_nav_buttons(before_standard_top_of_body_html_generation $hook): void {
        global $DB, $COURSE;

        // Course chain order.
        $chain = [];
        $enrols = $DB->get_records('enrol', ['enrol' => 'coursecompleted'], 'courseid');
        // Build chain from coursecompleted enrolments: customint1 (prereq) -> courseid (unlocks).
        $prereqmap = []; // prereq => unlocks
        foreach ($enrols as $e) {
            if (!empty($e->customint1)) {
                $prereqmap[$e->customint1] = $e->courseid;
            }
        }

        if (empty($prereqmap)) {
            return;
        }

        // Find the first course (not a prerequisite target of anything, but is a prerequisite).
        $allprereqs = array_keys($prereqmap);
        $alltargets = array_values($prereqmap);
        $firstcourses = array_diff($allprereqs, $alltargets);
        if (empty($firstcourses)) {
            return;
        }
        $first = reset($firstcourses);
        $chain[] = $first;
        $current = $first;
        while (isset($prereqmap[$current])) {
            $chain[] = $prereqmap[$current];
            $current = $prereqmap[$current];
        }

        $pos = array_search($COURSE->id, $chain);
        if ($pos === false) {
            return;
        }

        $prevhtml = '';
        $nexthtml = '';

        if ($pos > 0) {
            $previd = $chain[$pos - 1];
            $prevname = $DB->get_field('course', 'fullname', ['id' => $previd]);
            $prevurl = new moodle_url('/course/view.php', ['id' => $previd]);
            $prevhtml = '<a href="' . $prevurl->out(false) . '" class="btn course-nav-btn course-nav-prev">'
                . '← ' . format_string($prevname) . '</a>';
        }

        if ($pos < count($chain) - 1) {
            $nextid = $chain[$pos + 1];
            $nextname = $DB->get_field('course', 'fullname', ['id' => $nextid]);
            $nexturl = new moodle_url('/course/view.php', ['id' => $nextid]);
            $nexthtml = '<a href="' . $nexturl->out(false) . '" class="btn course-nav-btn course-nav-next">'
                . format_string($nextname) . ' →</a>';
        }

        if (empty($prevhtml) && empty($nexthtml)) {
            return;
        }

        $html = '<div class="course-nav-buttons" id="course-nav-buttons" style="display:none">'
            . $prevhtml . $nexthtml
            . '</div>'
            . '<script>document.addEventListener("DOMContentLoaded",function(){'
            . 'var n=document.getElementById("course-nav-buttons");'
            . 'var c=document.getElementById("region-main")||document.querySelector("[role=main]");'
            . 'if(n&&c){c.appendChild(n);n.style.display="";}'
            . '});</script>';

        $hook->add_html($html);
    }
}
