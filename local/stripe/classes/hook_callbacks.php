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
        // Skip guests and non-logged-in users.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        require_once(__DIR__ . '/../lib.php');

        // Keep a single, predictable subscription entry in the avatar menu.
        $menuitem = new \stdClass();
        $menuitem->itemtype = 'link';
        $menuitem->title = local_stripe_get_subscription_menu_label();
        $menuitem->url = new \moodle_url('/local/stripe/portal.php');
        $menuitem->titleidentifier = 'subscriptionmenu';
        $menuitem->titlecomponent = 'local_stripe';
        $hook->add_navitem($menuitem);
    }
}
