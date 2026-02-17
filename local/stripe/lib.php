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
        error_log("Stripe: Successfully assigned student_suscriptor role to user $userid");
        return true;
    } catch (Exception $e) {
        error_log("Stripe: Failed to assign role to user $userid: " . $e->getMessage());
        return false;
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
    $pref = $DB->get_record('user_preferences', [
        'name' => 'local_stripe_customer_id',
        'value' => $customerid,
    ]);
    if ($pref && !empty($pref->userid)) {
        return (int) $pref->userid;
    }
    return null;
}
