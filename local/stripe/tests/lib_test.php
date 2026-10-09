<?php
namespace local_stripe;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Tests for Stripe entitlement state helpers.
 *
 * @package local_stripe
 */
final class lib_test extends \advanced_testcase {

    public function test_subscription_blocks_are_isolated_and_ordered(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        local_stripe_block_access($user->id, 200, 'sub_refunded');

        $this->assertTrue(local_stripe_access_is_blocked($user->id, 'sub_refunded'));
        $this->assertFalse(local_stripe_access_is_blocked($user->id, 'sub_other'));
        $this->assertFalse(local_stripe_unblock_access($user->id, 199, 'sub_refunded'));
        $this->assertTrue(local_stripe_access_is_blocked($user->id, 'sub_refunded'));
        $this->assertTrue(local_stripe_unblock_access($user->id, 201, 'sub_refunded'));
        $this->assertFalse(local_stripe_access_is_blocked($user->id, 'sub_refunded'));
    }

    public function test_customer_wide_block_requires_a_newer_event(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        local_stripe_block_access($user->id, 500);

        $this->assertTrue(local_stripe_access_is_blocked($user->id, 'sub_new'));
        $this->assertFalse(local_stripe_unblock_access($user->id, 500, 'sub_new'));
        $this->assertTrue(local_stripe_unblock_access($user->id, 501, 'sub_new'));
        $this->assertFalse(local_stripe_access_is_blocked($user->id, 'sub_new'));
    }

    public function test_invoice_subscription_id_supports_both_api_shapes(): void {
        $this->assertSame('sub_legacy', local_stripe_get_invoice_subscription_id([
            'subscription' => 'sub_legacy',
        ]));
        $this->assertSame('sub_current', local_stripe_get_invoice_subscription_id([
            'parent' => [
                'subscription_details' => [
                    'subscription' => 'sub_current',
                ],
            ],
        ]));
        $this->assertNull(local_stripe_get_invoice_subscription_id(['object' => 'invoice']));
    }

    public function test_global_block_survives_until_newer_recovery_event(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        local_stripe_record_subscription_block(
            'cus_test',
            'sub_test',
            'evt_refund',
            'charge.refunded',
            700
        );

        $this->assertTrue(local_stripe_access_is_blocked($user->id, 'sub_test'));
        $this->assertFalse(local_stripe_unblock_access($user->id, 699, 'sub_test'));
        $this->assertTrue(local_stripe_unblock_access($user->id, 701, 'sub_test'));
        $this->assertFalse(local_stripe_access_is_blocked($user->id, 'sub_test'));
    }

    public function test_calendar_month_clamps_to_end_of_month(): void {
        $start = gmmktime(12, 30, 0, 1, 31, 2027);
        $this->assertSame(
            '2027-02-28 12:30:00',
            gmdate('Y-m-d H:i:s', local_stripe_add_calendar_month($start, new \DateTimeZone('UTC')))
        );
    }

    public function test_manual_renewal_extends_and_cancellation_removes_access(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setAdminUser();
        $admin = get_admin();
        $roleid = create_role('Subscriber', 'student_suscriptor', 'Subscriber role');
        $now = time();

        $first = local_stripe_grant_manual_month($user->id, $admin->id, bin2hex(random_bytes(32)));
        $second = local_stripe_grant_manual_month($user->id, $admin->id, bin2hex(random_bytes(32)));

        $this->assertSame($first->timeend, $second->timestart);
        $this->assertTrue(local_stripe_user_has_active_manual_access($user->id));
        $this->assertTrue(user_has_role_assignment(
            $user->id,
            $roleid,
            \context_system::instance()->id
        ));
        $result = local_stripe_cancel_manual_access(
            $user->id,
            $admin->id,
            'Customer request'
        );
        $this->assertSame(2, $result['count']);
        $this->assertFalse($result['access']);
        $this->assertFalse(local_stripe_user_has_active_manual_access($user->id, $now + HOURSECS));
        $this->assertFalse(user_has_role_assignment(
            $user->id,
            $roleid,
            \context_system::instance()->id
        ));
        $this->assertEquals(2, $DB->count_records('local_stripe_manual', ['status' => 'cancelled']));
    }
}
