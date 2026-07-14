<?php
// Local Stripe helper functions.

defined('MOODLE_INTERNAL') || die();

/**
 * Get the system context.
 *
 * @return context_system
 */
function local_stripe_system_context(): context_system {
    return context_system::instance();
}

/**
 * Get the suscriptor role id.
 *
 * @return int|null
 */
function local_stripe_get_suscriptor_role_id(): ?int {
    global $DB;
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
    return $roleid ? (int) $roleid : null;
}

/**
 * Assign the suscriptor role to a user (system context).
 *
 * @param int $userid
 * @return bool
 */
function local_stripe_assign_suscriptor_role(int $userid): bool {
    global $DB;
    
    $roleid = local_stripe_get_suscriptor_role_id();
    if (!$roleid) {
        error_log("Stripe: Role student_suscriptor not found");
        return false;
    }
    
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        error_log("Stripe: User $userid not found or deleted");
        return false;
    }
    
    $context = local_stripe_system_context();
    
    // Check if user already has the role
    if (user_has_role_assignment($userid, $roleid, $context->id)) {
        error_log("Stripe: User $userid already has student_suscriptor role");
        return true;
    }
    
    try {
        role_assign($roleid, $userid, $context->id);
        // Enrol user in all courses automatically.
        local_stripe_enrol_in_all_courses($userid);
        error_log("Stripe: Successfully assigned student_suscriptor role to user $userid");
        return true;
    } catch (Exception $e) {
        error_log("Stripe: Failed to assign role to user $userid: " . $e->getMessage());
        return false;
    }
}

/**
 * Enrol a user in all courses using the manual enrolment plugin.
 *
 * @param int $userid
 */
function local_stripe_enrol_in_all_courses(int $userid): void {
    global $DB;

    $courses = $DB->get_records('course', null, '', 'id');
    $enrolplugin = enrol_get_plugin('manual');
    if (!$enrolplugin) {
        return;
    }

    $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);

    foreach ($courses as $course) {
        if ($course->id == 1) {
            continue; // Skip site course.
        }

        // Get or create manual enrol instance for this course.
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        if (!$instance) {
            $instanceid = $enrolplugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        }

        // Check if user is already enrolled.
        if (!is_enrolled(context_course::instance($course->id), $userid)) {
            $enrolplugin->enrol_user($instance, $userid, $studentroleid);
        }
    }
}

/**
 * Remove the suscriptor role from a user (system context).
 *
 * @param int $userid
 * @return bool
 */
function local_stripe_remove_suscriptor_role(int $userid): bool {
    $roleid = local_stripe_get_suscriptor_role_id();
    if (!$roleid) {
        return false;
    }
    $context = local_stripe_system_context();
    role_unassign($roleid, $userid, $context->id);
    return true;
}

/**
 * Store Stripe customer id for a user.
 *
 * @param int $userid
 * @param string $customerid
 */
function local_stripe_store_customer_id(int $userid, string $customerid): void {
    set_user_preference('local_stripe_customer_id', $customerid, $userid);
}

/**
 * Find a user id by Stripe customer id.
 *
 * @param string $customerid
 * @return int|null
 */
function local_stripe_find_user_by_customer(string $customerid): ?int {
    global $DB;
    $sql = "SELECT userid FROM {user_preferences} 
            WHERE name = :name 
            AND " . $DB->sql_compare_text('value') . " = " . $DB->sql_compare_text(':value');
    $params = [
        'name' => 'local_stripe_customer_id',
        'value' => $customerid,
    ];
    $userid = $DB->get_field_sql($sql, $params);
    if ($userid) {
        return (int) $userid;
    }
    return null;
}

/**
 * Check if a user has the student_suscriptor role.
 *
 * @param int $userid
 * @return bool
 */
function local_stripe_user_has_suscriptor_role(int $userid): bool {
    global $DB;
    $context = context_system::instance();
    $role = $DB->get_record('role', ['shortname' => 'student_suscriptor']);
    if (!$role) {
        return false;
    }
    return user_has_role_assignment($userid, $role->id, $context->id);
}

/**
 * Inject a "Gestionar Suscripción" link into the user profile navigation tree.
 * Only shown to users who have an active suscriptor role.
 *
 * @param \core_user\output\myprofile\tree $tree  The profile navigation tree.
 * @param stdClass                         $user  The user whose profile is being viewed.
 * @param bool                             $iscurrentuser True when viewing own profile.
 * @param stdClass|null                    $course Current course (null on site level).
 * @return bool
 */
function local_stripe_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $USER;

    // Only show on the current user's own profile.
    if (!$iscurrentuser) {
        return false;
    }

    // Only show if the user is a subscriber.
    if (!local_stripe_user_has_suscriptor_role($user->id)) {
        return false;
    }

    $url = new moodle_url('/local/stripe/portal.php');

    $node = new core_user\output\myprofile\node(
        'local_stripe',          // Category (creates a new section if it doesn't exist).
        'stripe_portal',         // Unique node name.
        get_string('managebilling', 'local_stripe'), // Link text.
        null,                    // Parent node (null = top-level in category).
        $url,                    // URL.
        null,                    // Title attribute.
        'fa fa-credit-card'      // Icon.
    );

    $tree->add_node($node);
    return true;
}

/**
 * Find a Stripe customer ID by email using Stripe SDK.
 *
 * @param string $email
 * @param string $secretkey
 * @return string|null
 */
function local_stripe_find_customer_id_by_email(string $email, string $secretkey): ?string {
    global $CFG;
    require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
    \Stripe\Stripe::setApiKey($secretkey);
    try {
        $customers = \Stripe\Customer::search([
            'query' => 'email:"' . $email . '"',
            'limit' => 1,
        ]);
        if (!empty($customers->data)) {
            return $customers->data[0]->id;
        }
    } catch (Exception $e) {
        error_log('Stripe error in find_customer_id_by_email: ' . $e->getMessage());
    }
    return null;
}

/**
 * Check if a Stripe customer has an active subscription using Stripe SDK.
 *
 * @param string $customerid
 * @param string $secretkey
 * @return bool
 */
function local_stripe_customer_has_active_subscription(string $customerid, string $secretkey): bool {
    global $CFG;
    require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
    \Stripe\Stripe::setApiKey($secretkey);
    try {
        $subscriptions = \Stripe\Subscription::all([
            'customer' => $customerid,
            'status' => 'active',
            'limit' => 1,
        ]);
        return !empty($subscriptions->data);
    } catch (Exception $e) {
        error_log('Stripe error in customer_has_active_subscription: ' . $e->getMessage());
    }
    return false;
}

