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
 * Get the matching Stripe locale for the current Moodle language.
 *
 * Stripe uses IETF language tags and only supports a subset of Moodle's
 * language packs. Unsupported languages deliberately fall back to auto so
 * Stripe can use the customer's browser preference.
 *
 * @param string|null $moodlelang Moodle language code, or the current language.
 * @return string Stripe portal/Checkout locale.
 */
function local_stripe_get_stripe_locale(?string $moodlelang = null): string {
    $language = $moodlelang ?? current_language();
    $language = strtolower(str_replace('_', '-', $language));

    $locales = [
        'es-mx' => 'es-419',
        'es-419' => 'es-419',
        'pt-br' => 'pt-BR',
        'fr-ca' => 'fr-CA',
        'zh-hk' => 'zh-HK',
        'zh-tw' => 'zh-TW',
        'en-au' => 'en-AU',
        'en-ca' => 'en-CA',
        'en-gb' => 'en-GB',
        'en-ie' => 'en-IE',
        'en-in' => 'en-IN',
        'en-nz' => 'en-NZ',
        'en-sg' => 'en-SG',
    ];
    if (isset($locales[$language])) {
        return $locales[$language];
    }

    $supportedlanguages = [
        'bg', 'cs', 'da', 'de', 'el', 'en', 'es', 'et', 'fi', 'fil', 'fr',
        'hr', 'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'ms', 'mt', 'nb',
        'nl', 'pl', 'pt', 'ro', 'ru', 'sk', 'sl', 'sv', 'th', 'tr', 'vi', 'zh',
    ];
    $base = explode('-', $language)[0];
    return in_array($base, $supportedlanguages, true) ? $base : 'auto';
}

/**
 * Get the subscription menu label in the user's Moodle language.
 *
 * Moodle variants such as es_mx can fall back to English when a plugin only
 * provides an es translation, so explicitly use the Spanish base pack.
 *
 * @return string
 */
function local_stripe_get_subscription_menu_label(): string {
    $language = strtolower(str_replace('-', '_', current_language()));
    if (str_starts_with($language, 'es_') || $language === 'es') {
        return get_string_manager()->get_string('subscriptionmenu', 'local_stripe', null, 'es');
    }
    return get_string('subscriptionmenu', 'local_stripe');
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
 * @param string|null $subscriptionid Stripe subscription being used to grant access.
 * @return bool
 */
function local_stripe_assign_suscriptor_role(int $userid, ?string $subscriptionid = null): bool {
    global $DB;

    if (local_stripe_access_is_blocked($userid, $subscriptionid) &&
        !local_stripe_user_has_active_manual_access($userid)) {
        error_log("Stripe: Access is blocked by a later billing event for user $userid");
        return false;
    }
    
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
        $lastsync = (int)get_user_preferences('local_stripe_enrolments_synced_at', 0, $userid);
        if ($lastsync < time() - 21600) {
            local_stripe_enrol_in_all_courses($userid);
        }
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
 * Prevent active-subscription syncs from restoring access after a refund,
 * dispute, cancellation, pause, or failed payment.
 *
 * @param int $userid Moodle user ID.
 * @param int $eventcreated Stripe event creation timestamp.
 * @param string|null $subscriptionid Affected subscription, or null if unknown.
 */
function local_stripe_block_access(int $userid, int $eventcreated, ?string $subscriptionid = null): void {
    $blocks = local_stripe_get_access_blocks($userid);
    $key = $subscriptionid ?: '*';
    $blocks[$key] = max((int)($blocks[$key] ?? 0), $eventcreated);
    arsort($blocks, SORT_NUMERIC);
    $blocks = array_slice($blocks, 0, 25, true);
    set_user_preference('local_stripe_access_blocks', json_encode($blocks), $userid);
    // Remove the temporary preference used by the earlier customer-wide fix.
    unset_user_preference('local_stripe_access_blocked_at', $userid);
}

/**
 * Return subscription access blocks, migrating the earlier customer-wide
 * timestamp preference when present.
 *
 * @param int $userid Moodle user ID.
 * @return array<string,int>
 */
function local_stripe_get_access_blocks(int $userid): array {
    $blocks = json_decode(
        (string) get_user_preferences('local_stripe_access_blocks', '{}', $userid),
        true
    );
    if (!is_array($blocks)) {
        $blocks = [];
    }
    $legacy = (int) get_user_preferences('local_stripe_access_blocked_at', 0, $userid);
    if ($legacy > 0) {
        $blocks['*'] = max((int)($blocks['*'] ?? 0), $legacy);
    }
    return $blocks;
}

/**
 * Persist an adverse event by subscription, even when no Moodle user exists.
 *
 * @param string $customerid Stripe customer ID.
 * @param string $subscriptionid Stripe subscription ID.
 * @param string $eventid Stripe event ID.
 * @param string $reason Stripe event type.
 * @param int $eventcreated Stripe event creation timestamp.
 */
function local_stripe_record_subscription_block(
    string $customerid,
    string $subscriptionid,
    string $eventid,
    string $reason,
    int $eventcreated
): void {
    global $DB;

    $table = new xmldb_table('local_stripe_blocks');
    if (!$DB->get_manager()->table_exists($table) ||
        $DB->record_exists('local_stripe_blocks', ['eventid' => $eventid])) {
        return;
    }
    try {
        $DB->insert_record('local_stripe_blocks', (object)[
            'subscriptionid' => $subscriptionid,
            'customerid' => $customerid,
            'eventid' => $eventid,
            'reason' => $reason,
            'eventcreated' => $eventcreated,
            'timecreated' => time(),
        ]);
    } catch (dml_exception $e) {
        // Concurrent deliveries can race between the existence check and the
        // unique insert. Treat the already-recorded event as idempotent.
        if (!$DB->record_exists('local_stripe_blocks', ['eventid' => $eventid])) {
            throw $e;
        }
    }
}

/**
 * Get the newest global block for a subscription.
 *
 * @param string $subscriptionid Stripe subscription ID.
 * @return int
 */
function local_stripe_get_subscription_block_time(string $subscriptionid): int {
    global $DB;

    $table = new xmldb_table('local_stripe_blocks');
    if (!$DB->get_manager()->table_exists($table)) {
        return 0;
    }
    $sql = "SELECT MAX(eventcreated)
              FROM {local_stripe_blocks}
             WHERE subscriptionid = :subscriptionid";
    return (int)$DB->get_field_sql($sql, ['subscriptionid' => $subscriptionid]);
}

/**
 * Clear global blocks older than a verified recovery event.
 *
 * @param string $subscriptionid Stripe subscription ID.
 * @param int $eventcreated Stripe recovery event timestamp.
 */
function local_stripe_clear_subscription_blocks(string $subscriptionid, int $eventcreated): void {
    global $DB;

    $table = new xmldb_table('local_stripe_blocks');
    if (!$DB->get_manager()->table_exists($table)) {
        return;
    }
    $DB->execute(
        "DELETE FROM {local_stripe_blocks}
               WHERE subscriptionid = :subscriptionid
                 AND eventcreated < :eventcreated",
        ['subscriptionid' => $subscriptionid, 'eventcreated' => $eventcreated]
    );
}

/**
 * Check whether billing currently blocks subscriber access.
 *
 * @param int $userid Moodle user ID.
 * @param string|null $subscriptionid Subscription being evaluated.
 * @return bool
 */
function local_stripe_access_is_blocked(int $userid, ?string $subscriptionid = null): bool {
    $blocks = local_stripe_get_access_blocks($userid);
    if (!empty($blocks['*'])) {
        return true;
    }
    if ($subscriptionid !== null) {
        return !empty($blocks[$subscriptionid]) ||
            local_stripe_get_subscription_block_time($subscriptionid) > 0;
    }
    return !empty($blocks);
}

/**
 * Clear a billing block only when a newer Stripe event restores entitlement.
 * Pass 0 for a newly verified Checkout success page.
 *
 * @param int $userid Moodle user ID.
 * @param int $eventcreated Stripe event creation timestamp.
 * @param string|null $subscriptionid Subscription whose entitlement recovered.
 * @return bool Whether access is now unblocked.
 */
function local_stripe_unblock_access(
    int $userid,
    int $eventcreated,
    ?string $subscriptionid = null
): bool {
    $blocks = local_stripe_get_access_blocks($userid);
    if ($subscriptionid !== null) {
        $globalblockedat = local_stripe_get_subscription_block_time($subscriptionid);
        if ($globalblockedat > 0 && $eventcreated <= $globalblockedat) {
            return false;
        }
        local_stripe_clear_subscription_blocks($subscriptionid, $eventcreated);
    }
    $keys = $subscriptionid === null ? ['*'] : ['*', $subscriptionid];
    foreach ($keys as $key) {
        $blockedat = (int)($blocks[$key] ?? 0);
        if ($blockedat > 0 && $eventcreated <= $blockedat) {
            return false;
        }
    }
    foreach ($keys as $key) {
        unset($blocks[$key]);
    }
    if ($blocks) {
        set_user_preference('local_stripe_access_blocks', json_encode($blocks), $userid);
    } else {
        unset_user_preference('local_stripe_access_blocks', $userid);
    }
    unset_user_preference('local_stripe_access_blocked_at', $userid);
    return true;
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
    if (!$studentroleid) {
        error_log('Stripe: Role student not found; course enrolments were not synchronized');
        return;
    }

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
    set_user_preference('local_stripe_enrolments_synced_at', (string)time(), $userid);
}

/**
 * Add one calendar month while clamping dates such as January 31 to the last
 * valid day of the following month.
 *
 * @param int $timestamp Unix timestamp, interpreted in the site's timezone.
 * @param DateTimeZone|null $timezone Override for deterministic date tests.
 * @return int
 */
function local_stripe_add_calendar_month(int $timestamp, ?DateTimeZone $timezone = null): int {
    $timezone = $timezone ?? core_date::get_server_timezone_object();
    $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    $nextmonth = $date->modify('first day of next month');
    $day = min((int)$date->format('j'), (int)$nextmonth->format('t'));
    return $nextmonth->setDate(
        (int)$nextmonth->format('Y'),
        (int)$nextmonth->format('n'),
        $day
    )->getTimestamp();
}

/**
 * Check whether a user has an unexpired, uncancelled offline-payment grant.
 *
 * @param int $userid Moodle user ID.
 * @param int|null $at Timestamp to check, primarily for tests.
 * @return bool
 */
function local_stripe_user_has_active_manual_access(int $userid, ?int $at = null): bool {
    global $DB;

    $table = new xmldb_table('local_stripe_manual');
    if (!$DB->get_manager()->table_exists($table)) {
        return false;
    }
    $at = $at ?? time();
    return $DB->record_exists_select(
        'local_stripe_manual',
        'userid = :userid AND status = :status AND timestart <= :timestart AND timeend > :timeend',
        ['userid' => $userid, 'status' => 'active', 'timestart' => $at, 'timeend' => $at]
    );
}

/**
 * Grant one calendar month of access for an offline payment. Renewals start
 * after the newest current grant, so paying early never loses paid time.
 *
 * @param int $userid Moodle user ID.
 * @param int $adminid Administrator recording the payment.
 * @param string $requestid Unique form submission token (prevents duplicate payments on resubmit).
 * @return stdClass The stored grant.
 */
function local_stripe_grant_manual_month(int $userid, int $adminid, string $requestid): stdClass {
    global $DB;

    require_capability('moodle/site:config', local_stripe_system_context());
    if (!preg_match('/^[a-f0-9]{64}$/D', $requestid)) {
        throw new invalid_parameter_exception('Invalid payment request token.');
    }
    $lock = local_stripe_manual_access_lock($userid);
    try {
        $existing = $DB->get_record('local_stripe_manual', ['requestid' => $requestid]);
        if ($existing) {
            if ((int)$existing->userid !== $userid || (int)$existing->createdby !== $adminid) {
                throw new invalid_parameter_exception('Payment request belongs to a different user.');
            }
            return $existing;
        }
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1]);
        if (!$user || isguestuser($user)) {
            throw new moodle_exception('manualusernotfound', 'local_stripe');
        }
        $now = time();
        $start = max($now, local_stripe_manual_access_end($userid));
        $transaction = $DB->start_delegated_transaction();
        try {
            $grant = (object)[
                'userid' => $userid,
                'timestart' => $start,
                'timeend' => local_stripe_add_calendar_month($start),
                'status' => 'active',
                'createdby' => $adminid,
                'timecreated' => $now,
                'timeprocessed' => 0,
                'requestid' => $requestid,
            ];
            $grant->id = $DB->insert_record('local_stripe_manual', $grant);
            if (!local_stripe_assign_suscriptor_role($userid)) {
                throw new moodle_exception('manualgrantfailed', 'local_stripe');
            }
            $transaction->allow_commit();
            return $grant;
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
    } finally {
        $lock->release();
    }
}

/** Get the last uncancelled manual expiry, including prepaid months. */
function local_stripe_manual_access_end(int $userid): int {
    global $DB;
    return (int)$DB->get_field_sql(
        'SELECT MAX(timeend) FROM {local_stripe_manual} WHERE userid = :userid AND status = :status',
        ['userid' => $userid, 'status' => 'active']
    );
}

/** Find exactly one eligible user by their full email or username. */
function local_stripe_find_manual_user(string $identifier): ?stdClass {
    global $DB;
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }
    $users = $DB->get_records_select('user',
        'deleted = 0 AND suspended = 0 AND confirmed = 1 AND (email = :email OR username = :username)',
        ['email' => $identifier, 'username' => $identifier], 'id', '*', 0, 2);
    if (count($users) !== 1) {
        return null;
    }
    $user = reset($users);
    return isguestuser($user) ? null : $user;
}

/** Serialize grants, cancellations, and expiry checks for one user. */
function local_stripe_manual_access_lock(int $userid): \core\lock\lock {
    $factory = \core\lock\lock_config::get_lock_factory('local_stripe_manual');
    $lock = $factory->get_lock('user_' . $userid, 10);
    if (!$lock) {
        throw new moodle_exception('manualbusy', 'local_stripe');
    }
    return $lock;
}

/**
 * Re-evaluate access after an offline grant ends. API failures fail safe by
 * retaining access until the next scheduled reconciliation.
 *
 * @param int $userid Moodle user ID.
 * @return bool|null True when entitled, false when removed, null when a retry is required.
 */
function local_stripe_refresh_access_after_manual_end(int $userid): ?bool {
    if (local_stripe_user_has_active_manual_access($userid)) {
        return local_stripe_assign_suscriptor_role($userid) ? true : null;
    }

    $customerid = trim((string)get_user_preferences('local_stripe_customer_id', '', $userid));
    $secretkey = get_config('local_stripe', 'secretkey');
    if ($secretkey) {
        // Email lookup also recovers a paid Stripe subscription without a stored customer ID.
        return local_stripe_reconcile_customer_access($userid, $customerid, $secretkey);
    }
    if ($customerid !== '') {
        // Do not revoke a potentially paid subscription while credentials are unavailable.
        return null;
    }

    local_stripe_remove_suscriptor_role($userid);
    return false;
}

/**
 * Cancel all active offline grants for a user.
 *
 * @param int $userid Moodle user ID.
 * @param int $adminid Administrator cancelling access.
 * @param string $reason Optional audit note.
 * @return array{count:int,access:?bool} Cancelled grants and resulting access (null means pending).
 */
function local_stripe_cancel_manual_access(
    int $userid,
    int $adminid,
    string $reason = ''
): array {
    global $DB;

    require_capability('moodle/site:config', local_stripe_system_context());
    if (core_text::strlen($reason) > 255) {
        throw new invalid_parameter_exception('Cancellation reason must be at most 255 characters.');
    }
    $lock = local_stripe_manual_access_lock($userid);
    try {
        $now = time();
        $records = $DB->get_records_select(
            'local_stripe_manual',
            'userid = :userid AND status = :status AND timeend > :now',
            ['userid' => $userid, 'status' => 'active', 'now' => $now]
        );
        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($records as $record) {
                $DB->update_record('local_stripe_manual', (object)[
                    'id' => $record->id,
                    'status' => 'cancelled',
                    'cancelledby' => $adminid,
                    'timecancelled' => $now,
                    'cancellationreason' => trim($reason),
                ]);
            }
            $transaction->allow_commit();
        } catch (Throwable $e) {
            $transaction->rollback($e);
        }
        // Commit the audit first. Pending rows are retried by cron if Stripe is unavailable.
        $access = $records ? local_stripe_process_manual_end($userid) : null;
        return ['count' => count($records), 'access' => $access];
    } finally {
        $lock->release();
    }
}

/** Reconcile ended grants while holding the user's manual-access lock. */
function local_stripe_process_manual_end(int $userid): ?bool {
    global $DB;
    try {
        $result = local_stripe_refresh_access_after_manual_end($userid);
    } catch (Throwable $e) {
        error_log('Offline access reconciliation failed for user ' . $userid . ': ' . $e->getMessage());
        return null;
    }
    if ($result !== null) {
        $now = time();
        $DB->execute("UPDATE {local_stripe_manual}
                         SET status = :expired, timeprocessed = :processed
                       WHERE userid = :userid AND status = :active AND timeend <= :now", [
            'expired' => 'expired', 'processed' => $now, 'userid' => $userid, 'active' => 'active', 'now' => $now,
        ]);
        $DB->set_field('local_stripe_manual', 'timeprocessed', $now, [
            'userid' => $userid, 'status' => 'cancelled', 'timeprocessed' => 0,
        ]);
    }
    return $result;
}

/**
 * Remove the suscriptor role from a user (system context).
 *
 * @param int $userid
 * @return bool
 */
function local_stripe_remove_suscriptor_role(int $userid): bool {
    global $DB;

    // An adverse Stripe event must not revoke separately paid offline access.
    if (local_stripe_user_has_active_manual_access($userid)) {
        return true;
    }

    $roleid = local_stripe_get_suscriptor_role_id();
    $context = local_stripe_system_context();
    if ($roleid) {
        role_unassign($roleid, $userid, $context->id);
    }

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
    try {
        $DB->insert_record('local_stripe_events', (object) [
            'eventid' => $eventid,
            'eventtype' => $eventtype,
            'userid' => $userid,
            'timeprocessed' => time(),
        ]);
    } catch (dml_exception $e) {
        if (!$DB->record_exists('local_stripe_events', ['eventid' => $eventid])) {
            throw $e;
        }
    }
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
 * Find a Moodle user for Stripe events, with email recovery for older records
 * that do not yet have the Stripe customer preference stored.
 *
 * @param string $customerid Stripe customer ID.
 * @return int|null Moodle user ID.
 */
function local_stripe_find_user_by_customer_or_email(string $customerid): ?int {
    $userid = local_stripe_find_user_by_customer($customerid);
    if ($userid) {
        return $userid;
    }

    $secretkey = get_config('local_stripe', 'secretkey');
    if (!$secretkey) {
        return null;
    }

    $email = local_stripe_get_customer_email($customerid, $secretkey);
    if (!$email) {
        return null;
    }

    $userid = local_stripe_find_unique_user_by_email($email);
    if (!$userid) {
        return null;
    }

    local_stripe_store_customer_id($userid, $customerid);
    return $userid;
}

/**
 * Resolve an email only when it identifies exactly one active Moodle user.
 *
 * @param string $email Email address.
 * @return int|null
 */
function local_stripe_find_unique_user_by_email(string $email): ?int {
    global $DB;

    $users = $DB->get_records('user', ['email' => $email, 'deleted' => 0], 'id ASC', 'id', 0, 2);
    if (count($users) !== 1) {
        if (count($users) > 1) {
            error_log("Stripe: Refusing ambiguous email fallback for {$email}");
        }
        return null;
    }
    return (int)reset($users)->id;
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
        null                     // Icon.
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
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');
    // Moodle's client honours site networking settings and supports isolated HTTP tests.
    $client = new curl();
    $response = $client->get('https://api.stripe.com' . $path, [], [
        'CURLOPT_CONNECTTIMEOUT' => 5,
        'CURLOPT_TIMEOUT' => 15,
        'CURLOPT_FOLLOWLOCATION' => false,
        'CURLOPT_HTTPHEADER' => ['Authorization: Bearer ' . $secretkey],
    ]);
    $error = $client->error;
    $httpcode = (int)($client->get_info()['http_code'] ?? 0);

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
 * Cancel a Stripe subscription immediately so refunded or disputed customers
 * are not billed again on the next cycle.
 *
 * @param string $subscriptionid Stripe subscription ID.
 * @param string $secretkey Stripe secret API key.
 * @return bool
 */
function local_stripe_cancel_subscription(string $subscriptionid, string $secretkey): bool {
    $subscription = local_stripe_api_get(
        '/v1/subscriptions/' . rawurlencode($subscriptionid),
        $secretkey
    );
    if ($subscription && ($subscription['status'] ?? '') === 'canceled') {
        return true;
    }
    if ($subscription === null) {
        return false;
    }

    $ch = curl_init('https://api.stripe.com/v1/subscriptions/' . rawurlencode($subscriptionid));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpcode < 200 || $httpcode >= 300) {
        error_log("Stripe subscription cancellation failed for {$subscriptionid} ({$httpcode}): {$error}");
        return false;
    }
    return true;
}

/**
 * Extract a subscription ID from Invoice objects across Stripe API versions.
 *
 * @param array $invoice Stripe Invoice object.
 * @return string|null
 */
function local_stripe_get_invoice_subscription_id(array $invoice): ?string {
    $subscription = $invoice['subscription'] ??
        ($invoice['parent']['subscription_details']['subscription'] ?? null);
    if (is_array($subscription)) {
        $subscription = $subscription['id'] ?? null;
    }
    return is_string($subscription) && $subscription !== '' ? $subscription : null;
}

/**
 * Resolve the subscription associated with a Stripe Charge object.
 *
 * @param array $charge Stripe Charge object.
 * @param string $secretkey Stripe secret API key.
 * @return string|null
 */
function local_stripe_get_charge_subscription_id(array $charge, string $secretkey): ?string {
    $resolution = local_stripe_resolve_charge_subscription($charge, $secretkey);
    return $resolution['subscriptionid'];
}

/**
 * Resolve a Charge subscription while distinguishing a non-subscription charge
 * from a temporary Invoice API failure.
 *
 * @param array $charge Stripe Charge object.
 * @param string $secretkey Stripe secret API key.
 * @return array{ok:bool,subscriptionid:?string}
 */
function local_stripe_resolve_charge_subscription(array $charge, string $secretkey): array {
    $invoice = $charge['invoice'] ?? null;
    if (is_array($invoice)) {
        return ['ok' => true, 'subscriptionid' => local_stripe_get_invoice_subscription_id($invoice)];
    }
    if (!is_string($invoice) || $invoice === '') {
        return ['ok' => true, 'subscriptionid' => null];
    }
    $invoicedata = local_stripe_api_get('/v1/invoices/' . rawurlencode($invoice), $secretkey);
    if ($invoicedata === null) {
        return ['ok' => false, 'subscriptionid' => null];
    }
    return ['ok' => true, 'subscriptionid' => local_stripe_get_invoice_subscription_id($invoicedata)];
}

/**
 * Find a Stripe customer ID by email.
 *
 * @param string $email
 * @param string $secretkey
 * @return string|null
 */
function local_stripe_find_customer_id_by_email(string $email, string $secretkey): ?string {
    $customerids = local_stripe_find_customer_ids_by_email($email, $secretkey);
    if (!$customerids) {
        return null;
    }
    // Prefer the customer that currently owns an active subscription. This
    // avoids linking Moodle to an older duplicate Stripe customer.
    foreach ($customerids as $customerid) {
        if (local_stripe_customer_has_active_subscription($customerid, $secretkey)) {
            return $customerid;
        }
    }
    return $customerids[0];
}

/**
 * Find all Stripe customer IDs matching an email.
 *
 * @param string $email Customer email.
 * @param string $secretkey Stripe secret API key.
 * @return array|null IDs, an empty array when none exist, or null on API failure.
 */
function local_stripe_find_customer_ids_by_email(string $email, string $secretkey): ?array {
    $query = rawurlencode('email:"' . str_replace('"', '\\"', $email) . '"');
    $data = local_stripe_api_get('/v1/customers/search?query=' . $query . '&limit=100', $secretkey);
    if ($data === null || !isset($data['data']) || !is_array($data['data'])) {
        return null;
    }
    return array_values(array_filter(array_map(static function(array $customer): ?string {
        return !empty($customer['id']) ? (string)$customer['id'] : null;
    }, $data['data'])));
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
    $subscriptions = local_stripe_get_customer_subscriptions($customerid, $secretkey);
    if ($subscriptions === null) {
        return false;
    }
    foreach ($subscriptions as $subscription) {
        if (in_array($subscription['status'] ?? '', ['active', 'trialing'], true)) {
            return true;
        }
    }
    return false;
}

/**
 * Retrieve all subscriptions for a customer.
 *
 * @param string $customerid Stripe customer ID.
 * @param string $secretkey Stripe secret API key.
 * @return array|null Subscription list, or null on API failure.
 */
function local_stripe_get_customer_subscriptions(string $customerid, string $secretkey): ?array {
    $path = '/v1/subscriptions?customer=' . rawurlencode($customerid) . '&status=all&limit=100';
    $data = local_stripe_api_get($path, $secretkey);
    if ($data === null || !isset($data['data']) || !is_array($data['data'])) {
        return null;
    }
    return $data['data'];
}

/**
 * Reconcile Moodle access against all active subscriptions for one customer.
 * Blocked subscriptions (refunds, disputes, and failed invoices) do not count,
 * while a second independently paid subscription can still preserve access.
 *
 * @param int $userid Moodle user ID.
 * @param string $customerid Stripe customer ID.
 * @param string $secretkey Stripe secret API key.
 * @return bool|null True when access is granted, false when revoked, null on API failure.
 */
function local_stripe_reconcile_customer_access(
    int $userid,
    string $customerid,
    string $secretkey
): ?bool {
    global $DB;

    if (local_stripe_user_has_active_manual_access($userid)) {
        return local_stripe_assign_suscriptor_role($userid) ? true : null;
    }

    $customerids = $customerid !== '' ? [$customerid] : [];
    $email = $DB->get_field('user', 'email', ['id' => $userid, 'deleted' => 0]);
    if ($email) {
        $matchingcustomers = local_stripe_find_customer_ids_by_email($email, $secretkey);
        if ($matchingcustomers === null) {
            return null;
        }
        $customerids = array_values(array_unique(array_merge($matchingcustomers, $customerids)));
    }

    $lookupfailed = false;
    foreach ($customerids as $candidatecustomerid) {
        $subscriptions = local_stripe_get_customer_subscriptions($candidatecustomerid, $secretkey);
        if ($subscriptions === null) {
            $lookupfailed = true;
            continue;
        }
        foreach ($subscriptions as $subscription) {
            $subscriptionid = $subscription['id'] ?? null;
            if (!is_string($subscriptionid) ||
                !in_array($subscription['status'] ?? '', ['active', 'trialing'], true) ||
                local_stripe_access_is_blocked($userid, $subscriptionid)) {
                continue;
            }
            local_stripe_store_customer_id($userid, $candidatecustomerid);
            return local_stripe_assign_suscriptor_role($userid, $subscriptionid) ? true : null;
        }
    }

    if ($lookupfailed) {
        return null;
    }

    local_stripe_remove_suscriptor_role($userid);
    return false;
}
