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
 * Apply local language overrides for the user confirmation email.
 *
 * This writes to $CFG->dataroot/lang/en_local/moodle.php and $CFG->dataroot/lang/es_local/moodle.php
 * so it is deployed by running this CLI on the target server (moodledata is not in git).
 *
 * @package    local_calendario
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/clilib.php');

$langs = ['en', 'es'];

$subject = 'Confirma tu correo para activar tu cuenta en {$a}';

$body = <<<'HTML'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0;padding:0;background:#f6f7fb;">
  <tr>
    <td align="center" style="padding:28px 16px;">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:560px;max-width:560px;background:#ffffff;border:1px solid #e8e8ef;border-radius:14px;overflow:hidden;">
        <tr>
          <td style="padding:18px 22px;background:#ffffff;border-bottom:1px solid #eef0f4;">
            <div style="font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:14px;color:#111827;font-weight:700;letter-spacing:-0.2px;">
              {$a->sitename}
            </div>
          </td>
        </tr>

        <tr>
          <td style="padding:22px;">
            <div style="font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:20px;line-height:1.25;color:#111827;font-weight:750;letter-spacing:-0.3px;margin:0 0 10px;">
              Confirma tu correo
            </div>

            <div style="font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:14px;line-height:1.6;color:#374151;margin:0 0 16px;">
              Hola {$a->firstname},<br>
              Gracias por crear tu cuenta en <strong>{$a->sitename}</strong>. Para activarla, confirma tu correo con el botón:
            </div>

            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0 14px;">
              <tr>
                <td style="border-radius:10px;background:#8B1538;">
                  <a href="{$a->link}" style="display:inline-block;padding:12px 16px;font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">
                    Confirmar correo
                  </a>
                </td>
              </tr>
            </table>

            <div style="font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:12px;line-height:1.55;color:#6b7280;margin:0 0 6px;">
              Si el botón no funciona, copia y pega este enlace en tu navegador:
            </div>
            <div style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;font-size:12px;line-height:1.6;color:#111827;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;word-break:break-all;">
              {$a->link}
            </div>

            <div style="font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:12px;line-height:1.6;color:#6b7280;margin:14px 0 0;">
              Si tú no solicitaste esta cuenta, puedes ignorar este mensaje.
            </div>
          </td>
        </tr>

        <tr>
          <td style="padding:16px 22px;background:#fbfbfd;border-top:1px solid #eef0f4;">
            <div style="font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:12px;line-height:1.6;color:#6b7280;margin:0;">
              {$a->admin}
            </div>
          </td>
        </tr>
      </table>

      <div style="font-family:Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;font-size:11px;line-height:1.6;color:#9ca3af;margin-top:10px;">
        Mensaje automático de {$a->sitename}
      </div>
    </td>
  </tr>
</table>
HTML;

$content = "<?php\n";
$content .= "// Auto-generated local overrides for confirmation email.\n";
$content .= "defined('MOODLE_INTERNAL') || die();\n\n";
$content .= "\$string['emailconfirmationsubject'] = " . var_export($subject, true) . ";\n\n";
$content .= "\$string['emailconfirmation'] = " . var_export($body, true) . ";\n";

foreach ($langs as $lang) {
    $localdir = $CFG->dataroot . '/lang/' . $lang . '_local';
    $targetfile = $localdir . '/moodle.php';

    if (!is_dir($localdir)) {
        if (!mkdir($localdir, $CFG->directorypermissions, true) && !is_dir($localdir)) {
            cli_error('Unable to create directory: ' . $localdir);
        }
    }

    if (file_put_contents($targetfile, $content) === false) {
        cli_error('Unable to write: ' . $targetfile);
    }
    mtrace('Wrote overrides to: ' . $targetfile);
}

mtrace('Now run: php admin/cli/purge_caches.php');
