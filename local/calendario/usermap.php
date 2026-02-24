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
 * User map page (suscriptor-only).
 *
 * @package    local_calendario
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/iplookup/lib.php');

require_login();

$context = context_system::instance();
$canaccess = false;

if (function_exists('capability_exists') && \capability_exists('local/stripe:issuscriptor')) {
    $canaccess = has_capability('local/stripe:issuscriptor', $context);
}

if (!$canaccess) {
    // Fallback to role check if capability is not present/assigned.
    $suscriptorroleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
    if ($suscriptorroleid) {
        $canaccess = $DB->record_exists('role_assignments', [
            'roleid' => $suscriptorroleid,
            'userid' => $USER->id,
        ]);
    }
}

if (!$canaccess) {
    require_capability('moodle/site:config', $context);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/calendario/usermap.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('usermap', 'local_calendario'));
$PAGE->set_heading(get_string('usermap', 'local_calendario'));

$cache = cache::make('local_calendario', 'usermap_points');
$cachekey = 'points_v1';
$points = $cache->get($cachekey);

if ($points === false) {
    $records = $DB->get_records_sql(
        "SELECT id, firstname, lastname, country, lastip
           FROM {user}
          WHERE deleted = 0
            AND suspended = 0
            AND confirmed = 1
            AND lastaccess > 0",
        []
    );

    $aggregated = [];
    foreach ($records as $u) {
        $country = strtoupper(trim((string) ($u->country ?? '')));
        $ip = trim((string) ($u->lastip ?? ''));

        $lat = null;
        $lng = null;
        $countryname = null;

        if ($ip !== '' && $ip !== '0.0.0.0') {
            $loc = iplookup_find_location($ip);
            if (empty($loc['error']) && !empty($loc['latitude']) && !empty($loc['longitude'])) {
                $lat = (float) $loc['latitude'];
                $lng = (float) $loc['longitude'];
                $countryname = $loc['country'] ?? null;
                if (!empty($loc['country']) && empty($country)) {
                    $list = get_string_manager()->get_list_of_countries(true);
                    $code = array_search($loc['country'], $list, true);
                    if ($code) {
                        $country = (string) $code;
                    }
                }
            }
        }

        if (($lat === null || $lng === null) && $country !== '') {
            $centroids = local_calendario_get_country_centroids();
            if (isset($centroids[$country])) {
                $lat = (float) $centroids[$country][0];
                $lng = (float) $centroids[$country][1];
            }
        }

        if ($lat === null || $lng === null) {
            continue;
        }

        // Aggregate by rounded lat/lng to avoid exact user-level plotting.
        $key = round($lat, 1) . ',' . round($lng, 1);
        if (!isset($aggregated[$key])) {
            $label = $countryname;
            if (empty($label) && $country !== '') {
                $list = get_string_manager()->get_list_of_countries(true);
                $label = $list[$country] ?? $country;
            }
            if (empty($label)) {
                $label = get_string('unknownlocation', 'local_calendario');
            }

            $aggregated[$key] = [
                'lat' => round($lat, 1),
                'lng' => round($lng, 1),
                'count' => 0,
                'label' => $label,
            ];
        }
        $aggregated[$key]['count']++;
    }

    $points = array_values($aggregated);
    $cache->set($cachekey, $points);
}

// Leaflet via CDN (lightweight). If you prefer self-hosted assets later, we can vendor them.
$PAGE->requires->css(new moodle_url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'));
$PAGE->requires->js(new moodle_url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'), true);

echo $OUTPUT->header();

echo html_writer::start_div('local-calendario-usermap');
echo html_writer::tag('p', get_string('usermapintro', 'local_calendario'));

echo html_writer::div('', '', ['id' => 'usermap', 'style' => 'height: 520px; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;']);

echo html_writer::end_div();

$pointsjson = json_encode($points, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$js = <<<JS
(function() {
  var points = $pointsjson;

  var map = L.map('usermap', { scrollWheelZoom: false }).setView([20, 0], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 8,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  function radius(count) {
    return Math.min(42000, 9000 + (count * 5500));
  }

  points.forEach(function(p) {
    var circle = L.circle([p.lat, p.lng], {
      radius: radius(p.count),
      color: '#8B1538',
      weight: 2,
      fillColor: '#8B1538',
      fillOpacity: 0.22
    }).addTo(map);

    var title = (p.label || 'Usuarios') + ': ' + p.count;
    circle.bindPopup('<div style="font-weight:700;margin-bottom:4px;">' + (p.label || '') + '</div>' +
      '<div style="color:#374151;">' + p.count + ' usuario(s)</div>');
    circle.bindTooltip(title);
  });
})();
JS;

$PAGE->requires->js_amd_inline($js);

echo $OUTPUT->footer();

/**
 * Minimal country centroid list (ISO 3166-1 alpha-2 => [lat, lng]).
 *
 * @return array
 */
function local_calendario_get_country_centroids(): array {
    return [
        'MX' => [23.6345, -102.5528],
        'US' => [37.0902, -95.7129],
        'CA' => [56.1304, -106.3468],
        'AR' => [-38.4161, -63.6167],
        'BR' => [-14.2350, -51.9253],
        'CL' => [-35.6751, -71.5430],
        'CO' => [4.5709, -74.2973],
        'PE' => [-9.1900, -75.0152],
        'ES' => [40.4637, -3.7492],
        'FR' => [46.2276, 2.2137],
        'DE' => [51.1657, 10.4515],
        'IT' => [41.8719, 12.5674],
        'GB' => [55.3781, -3.4360],
        'PT' => [39.3999, -8.2245],
        'NL' => [52.1326, 5.2913],
        'BE' => [50.5039, 4.4699],
        'CH' => [46.8182, 8.2275],
        'AT' => [47.5162, 14.5501],
        'SE' => [60.1282, 18.6435],
        'NO' => [60.4720, 8.4689],
        'DK' => [56.2639, 9.5018],
        'PL' => [51.9194, 19.1451],
        'CZ' => [49.8175, 15.4730],
        'RO' => [45.9432, 24.9668],
        'UA' => [48.3794, 31.1656],
        'TR' => [38.9637, 35.2433],
        'RU' => [61.5240, 105.3188],
        'IN' => [20.5937, 78.9629],
        'JP' => [36.2048, 138.2529],
        'CN' => [35.8617, 104.1954],
        'KR' => [35.9078, 127.7669],
        'AU' => [-25.2744, 133.7751],
        'NZ' => [-40.9006, 174.8860],
    ];
}
