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
use core\hook\navigation\primary_extend;
use core\hook\output\before_standard_top_of_body_html_generation;
use core\hook\before_http_headers;
use navigation_node;
use moodle_url;
use context_course;
use context_system;

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
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = \context_system::instance();
        $hassuscriptorcap = false;
        if (function_exists('capability_exists') && \capability_exists('local/stripe:issuscriptor')) {
            $hassuscriptorcap = \has_capability('local/stripe:issuscriptor', $context);
        }

        $hassuscriptorrole = false;
        if (!$hassuscriptorcap) {
            $suscriptorroleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
            if ($suscriptorroleid) {
                $hassuscriptorrole = $DB->record_exists('role_assignments', [
                    'roleid' => $suscriptorroleid,
                    'userid' => $USER->id,
                ]);
            }
        }

        if (!$hassuscriptorcap && !$hassuscriptorrole) {
            return;
        }

        $secondary = $hook->get_secondaryview();
        $url = new moodle_url('/local/calendario/usermap.php');
        $node = navigation_node::create(
            get_string('usermap', 'local_calendario'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_calendario_usermap'
        );
        $secondary->add_node($node);
    }

    /**
     * Add "Map" to the primary navigation (header) for suscriptor users.
     *
     * @param primary_extend $hook
     */
    public static function add_primary_nav(primary_extend $hook): void {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = \context_system::instance();
        $hassuscriptorcap = false;
        if (function_exists('capability_exists') && \capability_exists('local/stripe:issuscriptor')) {
            $hassuscriptorcap = \has_capability('local/stripe:issuscriptor', $context);
        }

        $hassuscriptorrole = false;
        if (!$hassuscriptorcap) {
            $suscriptorroleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
            if ($suscriptorroleid) {
                $hassuscriptorrole = $DB->record_exists('role_assignments', [
                    'roleid' => $suscriptorroleid,
                    'userid' => $USER->id,
                ]);
            }
        }

        if (!$hassuscriptorcap && !$hassuscriptorrole) {
            return;
        }

        $primary = $hook->get_primaryview();
        $url = new moodle_url('/local/calendario/usermap.php');
        $node = navigation_node::create(
            get_string('map', 'local_calendario'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_calendario_usermap_primary'
        );
        $primary->add_node($node);
    }

    /**
     * Inject subscription banner for non-subscribers on course pages.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function inject_subscription_banner(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $USER, $DB, $COURSE;

        // Do not interfere with course editing UI/saving.
        if ($PAGE->user_is_editing() || is_siteadmin() || has_capability('moodle/course:update', \context_system::instance())) {
            return;
        }

        // First course (Empieza aquí): progressive unlocking for non-suscriptors should always apply on course view.
        // This must run before any early returns below.
        $pagecourseid = 0;
        if (isset($PAGE->course) && !empty($PAGE->course->id)) {
            $pagecourseid = (int) $PAGE->course->id;
        } else if (isset($COURSE->id)) {
            $pagecourseid = (int) $COURSE->id;
        } else {
            $pagecourseid = (int) optional_param('id', 0, PARAM_INT);
        }
        if ($pagecourseid === 4) {
            self::inject_course4_progressive_lock($hook);
        }

        // Skip banner for admins.
        if (is_siteadmin()) {
            // Still inject course navigation buttons on course pages.
            if (isset($COURSE->id) && $COURSE->id > 1) {
                self::inject_course_nav_buttons($hook);
            }
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
                    if (isset($COURSE->id) && $COURSE->id > 1) {
                        self::inject_course_nav_buttons($hook);
                    }
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
            . 'var islogin=document.body&&document.body.classList&&document.body.classList.contains("pagelayout-login");'
            . 'if(islogin){b.classList.add("collapsed");}'
            . 'var btn=document.getElementById("suscripcion-toggle");'
            . 'if(btn){btn.textContent=islogin?"▼":"▲";btn.title=islogin?"Expandir":"Minimizar";'
            . 'btn.addEventListener("click",function(){'
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

        // First course progressive unlocking is injected earlier to avoid early returns.
    }

    /**
     * Inject progressive activity locking on the course view for course 4.
     *
     * This is intentionally independent from inject_course_nav_buttons() because that method
     * may return early when there are no prev/next buttons for the current section.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    private static function inject_course4_progressive_lock(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $DB, $COURSE, $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Do not interfere with course editing UI/saving.
        if ($PAGE->user_is_editing() || is_siteadmin() || has_capability('moodle/course:update', \context_system::instance())) {
            return;
        }

        $courseid = 0;
        if (isset($PAGE->course) && !empty($PAGE->course->id)) {
            $courseid = (int) $PAGE->course->id;
        } else if (!empty($COURSE->id)) {
            $courseid = (int) $COURSE->id;
        } else {
            $courseid = (int) optional_param('id', 0, PARAM_INT);
        }

        if ($courseid !== 4) {
            return;
        }

        $path = $PAGE->url ? $PAGE->url->get_path() : '';
        if ($path !== '/course/view.php') {
            return;
        }

        $cmids = [];
        $allcms = $DB->get_records_sql(
            'SELECT cm.id
               FROM {course_modules} cm
               JOIN {course_sections} cs ON cs.id = cm.section
              WHERE cm.course = :courseid
                AND cm.visible = 1
           ORDER BY cs.section ASC, cm.id ASC',
            ['courseid' => 4]
        );
        if (!empty($allcms)) {
            $cmids = array_values(array_keys($allcms));
        }
        if (empty($cmids)) {
            return;
        }

        $completed = $DB->get_records_sql(
            'SELECT cmc.coursemoduleid
               FROM {course_modules_completion} cmc
              WHERE cmc.userid = :userid
                AND cmc.completionstate > 0',
            ['userid' => $USER->id]
        );
        $completedset = array_fill_keys(array_map(static function($r) {
            return (int) $r->coursemoduleid;
        }, $completed), true);

        $lastunlockedindex = 0;
        for ($i = 1; $i < count($cmids); $i++) {
            $prevcmid = (int) $cmids[$i - 1];
            if (!isset($completedset[$prevcmid])) {
                break;
            }
            $lastunlockedindex = $i;
        }

        $html = '<script>document.addEventListener("DOMContentLoaded",function(){'
            . 'if(window.__lc_course4_lock_applied){return;}window.__lc_course4_lock_applied=true;'
            . 'var lockfrom=' . ((int) $lastunlockedindex) . ';'
            . 'var applying=false;'
            . 'var applyLock=function(){'
            . 'if(applying){return;}applying=true;'
            . 'var container=document.querySelector("#region-main [data-for=\\"cmlist\\"]")||document.querySelector("[role=main] [data-for=\\"cmlist\\"]")||document.querySelector("[data-for=\\"cmlist\\"]");'
            . 'if(!container){applying=false;return;}'
            . 'var activities=container.querySelectorAll(".activity, .activity-item");'
            . 'var lockedcmids={};'
            . 'activities.forEach(function(act, index){'
            . 'if(index<=lockfrom){'
            . 'act.classList.remove("activity-locked");'
            . 'var old=act.querySelector(".locked-message");'
            . 'if(old){old.remove();}'
            . 'return;'
            . '}'
            . 'if(index>lockfrom){'
            . 'act.classList.add("activity-locked");'
            . 'var cmid=act.getAttribute("data-id")||act.dataset.id||"";'
            . 'if(cmid){lockedcmids[cmid]=true;}'
            . 'if(!act.querySelector(".locked-message")){' 
            . 'var msg=document.createElement("div");'
            . 'msg.className="locked-message";'
            . 'msg.innerHTML="🔒 Completa la clase anterior para desbloquear esta.";'
            . 'act.appendChild(msg);'
            . '}'
            . '}'
            . '});'
            . 'var courseindex=document.getElementById("courseindex")||document.querySelector("[data-region=\\"courseindex\\"]");'
            . 'if(courseindex){'
            . 'var items=courseindex.querySelectorAll("li.courseindex-item[data-for=\\"cm\\"][data-id]");'
            . 'items.forEach(function(item){'
            . 'var id=item.getAttribute("data-id");'
            . 'if(id&&lockedcmids[id]){item.classList.add("restrictions","dimmed");}else{item.classList.remove("restrictions","dimmed");}'
            . '});'
            . '}'
            . 'applying=false;'
            . '};'
            . 'applyLock();'
            . 'setTimeout(applyLock,400);'
            . 'try{'
            . 'var target=document.querySelector("[data-region=course-content]")||document.getElementById("page");'
            . 'if(target&&window.MutationObserver){'
            . 'var scheduled=false;'
            . 'var schedule=function(){if(scheduled){return;}scheduled=true;setTimeout(function(){scheduled=false;applyLock();},250);};'
            . 'var obs=new MutationObserver(function(){schedule();});'
            . 'obs.observe(target,{subtree:true,childList:true});'
            . '}'
            . '}catch(e){}'
            . '});</script>';

        $hook->add_html($html);
    }

    /**
     * Inject previous/next course navigation buttons at the bottom of course pages.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    private static function inject_course_nav_buttons(before_standard_top_of_body_html_generation $hook): void {
        global $DB, $COURSE, $PAGE;

        if ($PAGE->user_is_editing() || is_siteadmin() || has_capability('moodle/course:update', \context_system::instance())) {
            return;
        }

        $section = optional_param('section', 0, PARAM_INT);
        $firstsection = (int) $DB->get_field_sql(
            'SELECT MIN(section) FROM {course_sections} WHERE course = ? AND visible = 1 AND section > 0',
            [$COURSE->id]
        );
        $lastsection = (int) $DB->get_field_sql(
            'SELECT MAX(section) FROM {course_sections} WHERE course = ? AND visible = 1 AND section > 0',
            [$COURSE->id]
        );
        if ($section === 0 && $firstsection > 0) {
            $section = $firstsection;
        }
        $isfirstsection = ($firstsection > 0 && $section === $firstsection);
        $islastsection = ($lastsection > 0 && $section === $lastsection);

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

        // Back button: show on first section if not the first course in chain
        if ($pos > 0 && $isfirstsection) {
            $previd = $chain[$pos - 1];
            $prevname = $DB->get_field('course', 'fullname', ['id' => $previd]);
            $prevurl = new moodle_url('/course/view.php', ['id' => $previd]);
            $prevhtml = '<a href="' . $prevurl->out(false) . '" class="btn course-nav-btn course-nav-prev">'
                . '← ' . format_string($prevname) . '</a>';
        }

        // Next button: show on last section if not the last course in chain
        if ($pos < count($chain) - 1 && $islastsection) {
            $nextid = $chain[$pos + 1];
            $nextname = $DB->get_field('course', 'fullname', ['id' => $nextid]);
            $nexturl = new moodle_url('/course/view.php', ['id' => $nextid]);
            $nexthtml = '<a href="' . $nexturl->out(false) . '" class="btn course-nav-btn course-nav-next">'
                . format_string($nextname) . ' →</a>';
        }

        if (empty($prevhtml) && empty($nexthtml)) {
            return;
        }

        $hassuscriptorcap = false;
        if (function_exists('capability_exists') && \capability_exists('local/stripe:issuscriptor')) {
            $hassuscriptorcap = \has_capability('local/stripe:issuscriptor', \context_system::instance());
        }
        $shouldlock = $hassuscriptorcap ? 'false' : 'true';

        $courseid = (int) ($COURSE->id ?? 0);
        $lastunlockedindex = 0;
        if ($courseid === 4) {
            $cmids = [];
            $allcms = $DB->get_records_sql(
                'SELECT cm.id
                   FROM {course_modules} cm
                   JOIN {course_sections} cs ON cs.id = cm.section
                  WHERE cm.course = :courseid
                    AND cm.visible = 1
               ORDER BY cs.section ASC, cm.id ASC',
                ['courseid' => 4]
            );
            if (!empty($allcms)) {
                $cmids = array_values(array_keys($allcms));
            }

            if (!empty($cmids) && isloggedin() && !isguestuser()) {
                $completed = $DB->get_records_sql(
                    'SELECT cmc.coursemoduleid
                       FROM {course_modules_completion} cmc
                      WHERE cmc.userid = :userid
                        AND cmc.completionstate > 0',
                    ['userid' => $USER->id]
                );
                $completedset = array_fill_keys(array_map(static function($r) {
                    return (int) $r->coursemoduleid;
                }, $completed), true);

                for ($i = 1; $i < count($cmids); $i++) {
                    $prevcmid = (int) $cmids[$i - 1];
                    if (!isset($completedset[$prevcmid])) {
                        break;
                    }
                    $lastunlockedindex = $i;
                }
            }
        }

        $html = '<div class="course-nav-buttons" id="course-nav-buttons" style="display:none">'
            . $prevhtml . $nexthtml
            . '</div>'
            . '<script>document.addEventListener("DOMContentLoaded",function(){'
            . 'var n=document.getElementById("course-nav-buttons");'
            . 'var c=document.getElementById("region-main")||document.querySelector("[role=main]");'
            . 'if(n&&c){c.appendChild(n);n.style.display="";}'
            . 'if(' . $shouldlock . '){'
            . 'var activities=document.querySelectorAll(".activity, .activity-item");'
            . 'var courseid=' . $courseid . ';'
            . 'var isFirstCourse = (courseid === 4);'
            . 'var lockfrom = isFirstCourse ? ' . ((int) $lastunlockedindex) . ' : 0;'
            . 'activities.forEach(function(act, index){'
            . 'if(index > lockfrom){'
            . 'act.classList.add("activity-locked");'
            . 'var msg=document.createElement("div");'
            . 'msg.className="locked-message";'
            . 'if(isFirstCourse){'
            . 'msg.innerHTML="🔒 Completa la clase anterior para desbloquear esta.";'
            . '}else{'
            . 'msg.innerHTML="🔒 Esta clase requiere suscripción. <a href=\\"/local/stripe/index.php\\">Suscríbete aquí</a> para desbloquear todo el contenido.";'
            . '}'
            . 'act.appendChild(msg);'
            . '}'
            . '});'
            . '}'
            . '});</script>';

        $hook->add_html($html);
    }

    /**
     * Restrict access to activities for non-suscriptors.
     *
     * @param core\hook\before_http_headers $hook
     */
    public static function restrict_activity_access(core\hook\before_http_headers $hook): void {
        global $CFG, $DB;
        
        // Only check activity pages.
        $script = (string) $hook->get_script();
        if (!empty($CFG->wwwroot) && str_starts_with($script, $CFG->wwwroot)) {
            $script = substr($script, strlen($CFG->wwwroot));
        }
        $path = parse_url($script, PHP_URL_PATH);
        if ($path === null) {
            $path = $script;
        }
        $path = ltrim((string) $path, '/');
        if (!preg_match('#^mod/[^/]+/view\.php$#', $path)) {
            return;
        }

        // Get course module ID.
        $cmid = required_param('id', PARAM_INT);
        $cm = get_coursemodule_from_id('', $cmid, 0, true);

        // First course (Empieza aquí): allow sequential access based on completion for ALL users (except guest/admin).
        if ($cm->course == 4) {
            global $USER;
            if (isguestuser() || is_siteadmin()) {
                return;
            }

            $allcms = $DB->get_records_sql(
                'SELECT cm.id
                   FROM {course_modules} cm
                   JOIN {course_sections} cs ON cs.id = cm.section
                  WHERE cm.course = 4
                    AND cm.visible = 1
               ORDER BY cs.section ASC, cm.id ASC'
            );
            $cmids = array_keys($allcms);

            // Always allow first activity.
            if (empty($cmids) || $cmid == $cmids[0]) {
                return;
            }

            $pos = array_search($cmid, $cmids);
            if ($pos === false) {
                return;
            }

            $prevcmid = (int) $cmids[$pos - 1];
            $completed = $DB->record_exists_sql(
                'SELECT 1
                   FROM {course_modules_completion}
                  WHERE userid = :userid
                    AND coursemoduleid = :cmid
                    AND completionstate > 0',
                ['userid' => $USER->id, 'cmid' => $prevcmid]
            );

            if ($completed) {
                return;
            }

            $courseurl = new moodle_url('/course/view.php', ['id' => 4]);
            redirect($courseurl, '🔒 Completa la clase anterior para desbloquear esta.');
        }
        
        // Skip if user is suscriptor or guest.
        if (has_capability('local/stripe:issuscriptor', context_system::instance()) || isguestuser()) {
            return;
        }
        
        // Other courses: redirect to subscription page.
        $courseurl = new moodle_url('/course/view.php', ['id' => $cm->course]);
        redirect($courseurl, '🔒 Esta clase requiere suscripción. <a href="/local/stripe/index.php">Suscríbete aquí</a> para desbloquear todo el contenido.');
    }
}
