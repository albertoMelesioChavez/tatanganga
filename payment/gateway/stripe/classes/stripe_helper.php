<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Stripe helper class for API calls.
 *
 * @package    paygw_stripe
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_stripe;

/**
 * Helper class for Stripe API interactions using cURL (no SDK needed).
 */
class stripe_helper {

    /** @var string Stripe secret API key. */
    private string $apikey;

    /**
     * Constructor.
     *
     * @param string $apikey Stripe secret API key.
     */
    public function __construct(string $apikey) {
        $this->apikey = $apikey;
    }

    /**
     * Create a Stripe Checkout Session.
     *
     * @param float $amount Amount in major currency units (e.g. 100.00 for $100 MXN).
     * @param string $currency 3-letter currency code.
     * @param string $description Payment description.
     * @param string $successurl URL to redirect to on success.
     * @param string $cancelurl URL to redirect to on cancel.
     * @param array $metadata Extra metadata to attach to the session.
     * @return array|null Decoded JSON response or null on failure.
     */
    public function create_checkout_session(float $amount, string $currency, string $description,
                                            string $successurl, string $cancelurl, array $metadata = []): ?array {
        // Stripe expects amounts in the smallest currency unit (cents/centavos).
        $amountcents = (int) round($amount * 100);

        $postdata = [
            'payment_method_types[]' => 'card',
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][product_data][name]' => $description,
            'line_items[0][price_data][unit_amount]' => $amountcents,
            'line_items[0][quantity]' => 1,
            'mode' => 'payment',
            'success_url' => $successurl,
            'cancel_url' => $cancelurl,
        ];

        // Add metadata.
        foreach ($metadata as $key => $value) {
            $postdata["metadata[$key]"] = $value;
        }

        return $this->api_call('checkout/sessions', $postdata);
    }

    /**
     * Retrieve a Checkout Session by ID.
     *
     * @param string $sessionid Stripe session ID.
     * @return array|null Decoded JSON response or null on failure.
     */
    public function get_checkout_session(string $sessionid): ?array {
        return $this->api_call('checkout/sessions/' . $sessionid, null, 'GET');
    }

    /**
     * Make an API call to Stripe.
     *
     * @param string $endpoint API endpoint (relative to https://api.stripe.com/v1/).
     * @param array|null $postdata POST data (null for GET requests).
     * @param string $method HTTP method.
     * @return array|null Decoded JSON response or null on failure.
     */
    private function api_call(string $endpoint, ?array $postdata = null, string $method = 'POST'): ?array {
        $url = 'https://api.stripe.com/v1/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->apikey . ':');

        if ($method === 'POST' && $postdata !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode >= 200 && $httpcode < 300 && $response) {
            return json_decode($response, true);
        }

        debugging("Stripe API error (HTTP $httpcode): $response", DEBUG_DEVELOPER);
        return null;
    }
}
