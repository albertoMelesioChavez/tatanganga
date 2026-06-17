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

        // Only show if user has a Stripe customer ID stored
        $customerid = get_user_preferences('local_stripe_customer_id', null, $USER->id);
        if (empty($customerid)) {
            return;
        }

        // Create the menu item
        $menuitem = new \stdClass();
        $menuitem->itemtype = 'custom';
        $menuitem->title = get_string('managebilling', 'local_stripe');
        $menuitem->url = new \moodle_url('/local/stripe/portal.php');
        $menuitem->pix = 'fa-credit-card'; // FontAwesome icon
        $menuitem->titleidentifier = 'managebilling';
        $menuitem->titlecomponent = 'local_stripe';

        // Add the menu item to the hook
        $hook->add_navitem($menuitem);
    }
}
