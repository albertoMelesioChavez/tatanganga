<?php
defined('MOODLE_INTERNAL') || die();

class local_stripe_observer {

    /**
     * When a new user is created, assign the student role and enrol in the first course.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event): void {
        global $DB;

        $userid = $event->objectid;

        // Skip guest and admin users.
        if ($userid <= 2) {
            return;
        }

        // Assign student role at system level.
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if ($studentroleid) {
            $context = context_system::instance();
            if (!user_has_role_assignment($userid, $studentroleid, $context->id)) {
                role_assign($studentroleid, $userid, $context->id);
            }
        }

        // Enrol in the first course (Empieza aquí) which is course id 4.
        $firstcourse = $DB->get_record('course', ['id' => 4], 'id', IGNORE_MISSING);
        if (!$firstcourse) {
            return;
        }

        $enrolplugin = enrol_get_plugin('manual');
        if (!$enrolplugin) {
            return;
        }

        $instance = $DB->get_record('enrol', ['courseid' => $firstcourse->id, 'enrol' => 'manual']);
        if (!$instance) {
            $instanceid = $enrolplugin->add_instance($firstcourse);
            $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        }

        if (!is_enrolled(context_course::instance($firstcourse->id), $userid)) {
            $enrolplugin->enrol_user($instance, $userid, $studentroleid);
        }

        // Check if there is an active Stripe subscription for this user on registration.
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if ($user && !empty($user->email)) {
            require_once(__DIR__ . '/../lib.php');
            $secretkey = get_config('local_stripe', 'secretkey');
            if (!empty($secretkey)) {
                $customerid = local_stripe_find_customer_id_by_email($user->email, $secretkey);
                if ($customerid) {
                    if (local_stripe_customer_has_active_subscription($customerid, $secretkey)) {
                        local_stripe_assign_suscriptor_role($userid);
                        local_stripe_store_customer_id($userid, $customerid);
                        error_log("Stripe observer: Assigned student_suscriptor role and stored customer ID {$customerid} on registration for user {$userid}");
                    }
                }
            }
        }
    }
}
