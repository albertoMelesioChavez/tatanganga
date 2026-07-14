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
    
    // Keep enrolments in sync even when the role was restored before a new
    // course was created.
    if (user_has_role_assignment($userid, $roleid, $context->id)) {
        local_stripe_enrol_in_all_courses($userid);
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

        // Do not take ownership of enrolments created by administrators or
        // other enrolment methods. Only record and later revoke our own.
        $enrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $userid,
        ]);
        if (!$enrolment) {
            $enrolplugin->enrol_user($instance, $userid, $studentroleid);
            if (!$DB->record_exists('local_stripe_enrolments', [
                'userid' => $userid,
                'courseid' => $course->id,
            ])) {
                $DB->insert_record('local_stripe_enrolments', (object) [
                    'userid' => $userid,
                    'courseid' => $course->id,
                    'enrolid' => $instance->id,
                    'timecreated' => time(),
                ]);
            }
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
    global $DB;

    $roleid = local_stripe_get_suscriptor_role_id();
    if (!$roleid) {
        return false;
    }
    $context = local_stripe_system_context();
    role_unassign($roleid, $userid, $context->id);

    $enrolplugin = enrol_get_plugin('manual');
    if ($enrolplugin) {
        $enrolments = $DB->get_records('local_stripe_enrolments', ['userid' => $userid]);
        foreach ($enrolments as $enrolment) {
            $instance = $DB->get_record('enrol', ['id' => $enrolment->enrolid, 'enrol' => 'manual']);
            if ($instance && $DB->record_exists('user_enrolments', [
                'enrolid' => $instance->id,
                'userid' => $userid,
            ])) {
                $enrolplugin->unenrol_user($instance, $userid);
            }
            $DB->delete_records('local_stripe_enrolments', ['id' => $enrolment->id]);
        }
    }
    return true;
}

/**
 * Check whether a Stripe event was already processed.
 *
 * @param string $eventid
 * @return bool
 */
function local_stripe_event_processed(string $eventid): bool {
    global $DB;
    return $DB->record_exists('local_stripe_events', ['eventid' => $eventid]);
}

/**
 * Record a successfully handled Stripe event to make retries idempotent.
 *
 * @param string $eventid
 * @param string $eventtype
 * @param int|null $userid
 * @return void
 */
function local_stripe_mark_event_processed(string $eventid, string $eventtype, ?int $userid = null): void {
    global $DB;
    $DB->insert_record('local_stripe_events', (object) [
        'eventid' => $eventid,
        'eventtype' => $eventtype,
        'userid' => $userid,
        'timeprocessed' => time(),
    ]);
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
    // Only show on the current user's own profile.
    if (!$iscurrentuser) {
        return false;
    }

    // Only show if the user is a subscriber.
    if (!local_stripe_user_has_suscriptor_role($user->id)) {
        return false;
    }

    // Profile nodes must reference a category that has already been registered.
    // Guard against duplicates in case another callback registered it first.
    if (!isset($tree->categories['local_stripe'])) {
        $category = new core_user\output\myprofile\category(
            'local_stripe',
            get_string('pluginname', 'local_stripe')
        );
        $tree->add_category($category);
    }

    $url = new moodle_url('/local/stripe/portal.php');

    $node = new core_user\output\myprofile\node(
        'local_stripe',          // Category registered above.
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
 * Make a read-only request to the Stripe API without relying on the optional
 * Stripe PHP SDK. This keeps subscription recovery available on deployments
 * where plugin vendor dependencies were not copied.
 *
 * @param string $path API path including its query string.
 * @param string $secretkey Stripe secret API key.
 * @return array|null Decoded Stripe response, or null on failure.
 */
function local_stripe_api_get(string $path, string $secretkey): ?array {
    $ch = curl_init('https://api.stripe.com' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpcode < 200 || $httpcode >= 300) {
        error_log('Stripe API GET failed (' . $httpcode . '): ' . $error);
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log('Stripe API GET returned invalid JSON');
        return null;
    }

    return $data;
}

/**
 * Find a Stripe customer ID by email.
 *
 * @param string $email
 * @param string $secretkey
 * @return string|null
 */
function local_stripe_find_customer_id_by_email(string $email, string $secretkey): ?string {
    $query = rawurlencode('email:"' . str_replace('"', '\\"', $email) . '"');
    $data = local_stripe_api_get('/v1/customers/search?query=' . $query . '&limit=1', $secretkey);
    if (!empty($data['data'][0]['id'])) {
        return (string) $data['data'][0]['id'];
    }
    return null;
}

/**
 * Get a Stripe customer's email address.
 *
 * @param string $customerid
 * @param string $secretkey
 * @return string|null
 */
function local_stripe_get_customer_email(string $customerid, string $secretkey): ?string {
    $data = local_stripe_api_get('/v1/customers/' . rawurlencode($customerid), $secretkey);
    return !empty($data['email']) ? (string) $data['email'] : null;
}

/**
 * Check whether a Stripe customer has an active or trialing subscription.
 *
 * @param string $customerid
 * @param string $secretkey
 * @return bool
 */
function local_stripe_customer_has_active_subscription(string $customerid, string $secretkey): bool {
    $path = '/v1/subscriptions?customer=' . rawurlencode($customerid) . '&status=all&limit=100';
    $data = local_stripe_api_get($path, $secretkey);
    if (empty($data['data']) || !is_array($data['data'])) {
        return false;
    }
    foreach ($data['data'] as $subscription) {
        if (in_array($subscription['status'] ?? '', ['active', 'trialing'], true)) {
            return true;
        }
    }
    return false;
}
