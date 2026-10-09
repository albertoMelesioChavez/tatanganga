<?php
namespace local_stripe\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Expire access granted for offline payments.
 *
 * @package local_stripe
 */
class expire_manual_access extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('taskexpiremanualaccess', 'local_stripe');
    }

    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/local/stripe/lib.php');
        $users = $DB->get_recordset_sql(
            'SELECT DISTINCT userid FROM {local_stripe_manual}
              WHERE status = :active OR (status = :cancelled AND timeprocessed = 0)',
            ['active' => 'active', 'cancelled' => 'cancelled']
        );
        $revoked = 0;
        $retained = 0;
        $pending = 0;
        try {
            foreach ($users as $user) {
                $lock = null;
                $userid = (int)$user->userid;
                try {
                    $lock = local_stripe_manual_access_lock($userid);
                    // Also keeps course enrolments current for offline-only subscribers.
                    $result = local_stripe_process_manual_end($userid);
                    if ($result === null) {
                        $pending++;
                    } else if ($result) {
                        $retained++;
                    } else {
                        $revoked++;
                    }
                } catch (\Throwable $e) {
                    $pending++;
                    mtrace("Manual access check failed for user {$userid}: {$e->getMessage()}");
                } finally {
                    if ($lock) {
                        $lock->release();
                    }
                }
            }
        } finally {
            $users->close();
        }
        mtrace("Manual access check complete: revoked={$revoked}, retained={$retained}, pending={$pending}");
    }
}
