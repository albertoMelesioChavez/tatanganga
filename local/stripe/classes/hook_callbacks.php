<?php
namespace local_stripe;

/**
 * Hook callbacks for the local_stripe plugin.
 *
 * @package    local_stripe
 * @copyright  2024 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Callback to extend the user menu with Stripe billing portal link.
     *
     * @param \core_user\hook\extend_user_menu $hook
     */
    public static function extend_user_menu(\core_user\hook\extend_user_menu $hook): void {
        global $USER;

        // Skip guests and non-logged-in users.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        require_once(__DIR__ . '/../lib.php');

        $customerid = get_user_preferences('local_stripe_customer_id', null, $USER->id);
        $hassubscription = local_stripe_user_has_suscriptor_role($USER->id);

        $hasbilling = !empty($customerid) || $hassubscription;

        // Keep a single, predictable subscription entry in the avatar menu.
        $menuitem = new \stdClass();
        $menuitem->itemtype = 'custom';
        $menuitem->title = get_string('subscriptionmenu', 'local_stripe');
        if ($hasbilling) {
            $menuitem->url = new \moodle_url('/local/stripe/portal.php');
        } else {
            $menuitem->url = new \moodle_url('/local/stripe/plans.php');
        }
        $menuitem->pix = 'fa-credit-card';
        $menuitem->titleidentifier = 'subscriptionmenu';
        $menuitem->titlecomponent = 'local_stripe';
        $hook->add_navitem($menuitem);
    }
}
