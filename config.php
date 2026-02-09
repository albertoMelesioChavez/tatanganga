<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodle';
$CFG->dbpass    = 'UIMfw*B0rn37^NPpO8Zj';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_general_ci',
);

$CFG->wwwroot   = 'https://tatanganga.cloud';
$CFG->dataroot  = '/home/user/htdocs/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 02777;

@error_reporting(E_ALL | E_STRICT);
@ini_set('display_errors', '1');
$CFG->debug = E_ALL | E_STRICT;
$CFG->debugdisplay = 1;


require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
