<?php
namespace local_stripe\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/** Native Moodle forms for finding users and confirming offline access changes. */
class manual_access_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $action = $this->_customdata['action'];
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ALPHA);

        if ($action === 'search') {
            $mform->addElement('text', 'identifier', get_string('manualuseridentifier', 'local_stripe'), [
                'size' => 40, 'maxlength' => 254,
            ]);
            $mform->setType('identifier', PARAM_RAW_TRIMMED);
            $mform->addRule('identifier', get_string('required'), 'required', null, 'client');
            $this->add_action_buttons(false, get_string('manualsearchbutton', 'local_stripe'));
            return;
        }

        $mform->addElement('hidden', 'userid', $this->_customdata['user']->id);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('static', 'person', get_string('user'),
            s(fullname($this->_customdata['user'])) . ' — ' . s($this->_customdata['user']->email));
        if ($action === 'grant') {
            $mform->addElement('hidden', 'requestid', $this->_customdata['requestid']);
            $mform->setType('requestid', PARAM_ALPHANUM);
            $mform->addElement('static', 'dates', get_string('manualperiod', 'local_stripe'),
                userdate($this->_customdata['start']) . ' — ' . userdate($this->_customdata['end']));
            $mform->addElement('static', 'help', '', get_string('manualrenewalhelp', 'local_stripe'));
            $this->add_action_buttons(true, get_string('manualgrantbutton', 'local_stripe'));
        } else {
            $mform->addElement('static', 'explanation', '', get_string('manualcancelconfirm', 'local_stripe'));
            $mform->addElement('text', 'reason', get_string('manualcancelreason', 'local_stripe'), [
                'size' => 60, 'maxlength' => 255,
            ]);
            $mform->setType('reason', PARAM_TEXT);
            $mform->addRule('reason', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $this->add_action_buttons(true, get_string('manualcancelbutton', 'local_stripe'));
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['action'] === 'search' && !local_stripe_find_manual_user($data['identifier'])) {
            $errors['identifier'] = get_string('manualusernotfound', 'local_stripe');
        }
        if ($data['action'] === 'cancel' && \core_text::strlen($data['reason']) > 255) {
            $errors['reason'] = get_string('maximumchars', '', 255);
        }
        return $errors;
    }
}
