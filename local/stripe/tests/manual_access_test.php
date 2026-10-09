<?php
namespace local_stripe;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../lib.php');
require_once($CFG->libdir . '/filelib.php');

/** Offline payments must grant access independently and never revoke paid Stripe access. */
final class manual_access_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        create_role('Subscriber', 'student_suscriptor', 'Subscriber role');
    }

    private function grant(int $userid, ?string $requestid = null): \stdClass {
        return local_stripe_grant_manual_month($userid, get_admin()->id, $requestid ?? bin2hex(random_bytes(32)));
    }

    public function test_calendar_month_uses_local_date_and_clamps_leap_year(): void {
        $zone = new \DateTimeZone('America/Mazatlan');
        $start = new \DateTimeImmutable('2028-01-31 23:30:00', $zone);
        $end = local_stripe_add_calendar_month($start->getTimestamp(), $zone);
        $this->assertSame('2028-02-29 23:30:00', (new \DateTimeImmutable('@' . $end))->setTimezone($zone)->format('Y-m-d H:i:s'));
        $zone = new \DateTimeZone('America/New_York');
        $start = new \DateTimeImmutable('2027-02-15 12:00:00', $zone);
        $end = local_stripe_add_calendar_month($start->getTimestamp(), $zone);
        $this->assertSame('2027-03-15 12:00:00', (new \DateTimeImmutable('@' . $end))->setTimezone($zone)->format('Y-m-d H:i:s'));
    }

    public function test_replayed_form_does_not_add_another_month_or_restore_cancelled_access(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $token = bin2hex(random_bytes(32));
        $grant = $this->grant($user->id, $token);
        $replay = $this->grant($user->id, $token);
        $this->assertEquals($grant->id, $replay->id);
        $this->assertEquals(1, $DB->count_records('local_stripe_manual'));
        local_stripe_cancel_manual_access($user->id, get_admin()->id, 'Duplicate payment');
        $replay = $this->grant($user->id, $token);
        $this->assertSame('cancelled', $replay->status);
        $this->assertFalse(local_stripe_user_has_suscriptor_role($user->id));
    }

    public function test_billing_blocks_do_not_override_offline_access(): void {
        $user = $this->getDataGenerator()->create_user();
        local_stripe_block_access($user->id, time(), 'sub_failed');
        $this->grant($user->id);
        local_stripe_remove_suscriptor_role($user->id);
        $this->assertTrue(local_stripe_user_has_suscriptor_role($user->id));
        // No API response is needed for an independently paid manual entitlement.
        $this->assertTrue(local_stripe_reconcile_customer_access($user->id, 'cus_old', 'sk_test_unused'));
        $this->assertTrue(local_stripe_access_is_blocked($user->id, 'sub_failed'));
        local_stripe_cancel_manual_access($user->id, get_admin()->id);
        $this->assertFalse(local_stripe_user_has_suscriptor_role($user->id));
        $this->assertTrue(local_stripe_access_is_blocked($user->id, 'sub_failed'));
    }

    public function test_cancel_retains_stripe_subscription_recovered_by_email(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $grant = $this->grant($user->id);
        set_config('secretkey', 'sk_test_mock', 'local_stripe');
        // Moodle's HTTP mock stack returns the last response first.
        \curl::mock_response(json_encode(['data' => [['id' => 'sub_paid', 'status' => 'active']]]));
        \curl::mock_response(json_encode(['data' => [['id' => 'cus_paid']]]));
        $result = local_stripe_cancel_manual_access($user->id, get_admin()->id, 'Customer request');
        $this->assertTrue($result['access']);
        $this->assertTrue(local_stripe_user_has_suscriptor_role($user->id));
        $record = $DB->get_record('local_stripe_manual', ['id' => $grant->id]);
        $this->assertSame('cancelled', $record->status);
        $this->assertSame('Customer request', $record->cancellationreason);
        $this->assertEquals(get_admin()->id, $record->cancelledby);
        $this->assertGreaterThan(0, (int)$record->timecancelled);
        $this->assertGreaterThan(0, (int)$record->timeprocessed);
    }

    public function test_cancel_api_failure_is_retried_by_cron(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $grant = $this->grant($user->id);
        local_stripe_store_customer_id($user->id, 'cus_old');
        set_config('secretkey', 'sk_test_mock', 'local_stripe');
        \curl::mock_response('temporary outage');
        $result = local_stripe_cancel_manual_access($user->id, get_admin()->id);
        $this->assertNull($result['access']);
        $this->assertTrue(local_stripe_user_has_suscriptor_role($user->id));
        $this->assertEquals(0, $DB->get_field('local_stripe_manual', 'timeprocessed', ['id' => $grant->id]));
        \curl::mock_response(json_encode(['data' => []]));
        \curl::mock_response(json_encode(['data' => []]));
        $this->expectOutputRegex('/pending=0/');
        (new \local_stripe\task\expire_manual_access())->execute();
        $this->assertFalse(local_stripe_user_has_suscriptor_role($user->id));
        $this->assertGreaterThan(0, (int)$DB->get_field('local_stripe_manual', 'timeprocessed', ['id' => $grant->id]));
    }

    public function test_expiry_removes_only_plugin_owned_enrolments(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $existingcourse = $this->getDataGenerator()->create_course();
        $paidcourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $existingcourse->id);
        $grant = $this->grant($user->id);
        $this->assertTrue(is_enrolled(\context_course::instance($paidcourse->id), $user));
        $DB->set_field('local_stripe_manual', 'timeend', time() - 1, ['id' => $grant->id]);
        $this->expectOutputRegex('/revoked=1/');
        (new \local_stripe\task\expire_manual_access())->execute();
        $this->assertFalse(local_stripe_user_has_suscriptor_role($user->id));
        $this->assertTrue(is_enrolled(\context_course::instance($existingcourse->id), $user));
        $this->assertFalse(is_enrolled(\context_course::instance($paidcourse->id), $user));
        $this->assertSame('expired', $DB->get_field('local_stripe_manual', 'status', ['id' => $grant->id]));
    }

    public function test_expiry_does_not_remove_prepaid_renewal(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $first = $this->grant($user->id);
        $second = $this->grant($user->id);
        $DB->set_field('local_stripe_manual', 'timeend', time(), ['id' => $first->id]);
        $DB->set_field('local_stripe_manual', 'timestart', time(), ['id' => $second->id]);
        $this->expectOutputRegex('/retained=1/');
        (new \local_stripe\task\expire_manual_access())->execute();
        $this->assertTrue(local_stripe_user_has_suscriptor_role($user->id));
        $this->assertSame('expired', $DB->get_field('local_stripe_manual', 'status', ['id' => $first->id]));
    }

    public function test_missing_credentials_keep_potential_stripe_access_pending(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $grant = $this->grant($user->id);
        local_stripe_store_customer_id($user->id, 'cus_paid');
        $result = local_stripe_cancel_manual_access($user->id, get_admin()->id);
        $this->assertNull($result['access']);
        $this->assertEquals(0, $DB->get_field('local_stripe_manual', 'timeprocessed', ['id' => $grant->id]));
        $this->assertTrue(local_stripe_user_has_suscriptor_role($user->id));
    }

    public function test_duplicate_email_and_suspended_users_are_not_selected(): void {
        $user = $this->getDataGenerator()->create_user(['email' => 'offline@example.test']);
        $this->getDataGenerator()->create_user(['email' => $user->email]);
        $this->assertNull(local_stripe_find_manual_user($user->email));
        $this->assertEquals($user->id, local_stripe_find_manual_user($user->username)->id);
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $this->assertNull(local_stripe_find_manual_user($suspended->username));
    }

    public function test_non_administrator_cannot_grant_access(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        $this->grant($user->id);
    }

    public function test_non_administrator_cannot_cancel_access(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->grant($user->id);
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        local_stripe_cancel_manual_access($user->id, $user->id);
    }

    public function test_failed_grant_rolls_back_and_can_be_retried(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $token = bin2hex(random_bytes(32));
        $roleid = local_stripe_get_suscriptor_role_id();
        $DB->set_field('role', 'shortname', 'temporarily_unavailable', ['id' => $roleid]);
        try {
            $this->grant($user->id, $token);
            $this->fail('Grant without a subscriber role should fail.');
        } catch (\moodle_exception $e) {
            $this->assertEquals(0, $DB->count_records('local_stripe_manual'));
        }
        $DB->set_field('role', 'shortname', 'student_suscriptor', ['id' => $roleid]);
        $this->grant($user->id, $token);
        $this->assertEquals(1, $DB->count_records('local_stripe_manual'));
        $this->assertTrue(local_stripe_user_has_suscriptor_role($user->id));
    }

    public function test_manual_entitlement_respects_both_date_boundaries(): void {
        $user = $this->getDataGenerator()->create_user();
        $grant = $this->grant($user->id);
        $this->assertFalse(local_stripe_user_has_active_manual_access($user->id, $grant->timestart - 1));
        $this->assertTrue(local_stripe_user_has_active_manual_access($user->id, $grant->timestart));
        $this->assertTrue(local_stripe_user_has_active_manual_access($user->id, $grant->timeend - 1));
        $this->assertFalse(local_stripe_user_has_active_manual_access($user->id, $grant->timeend));
    }
}
