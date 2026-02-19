<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DB_HOST') ?: 'localhost';
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

$CFG->wwwroot   = getenv('MOODLE_WWWROOT') ?: 'http://localhost:8080';
$CFG->dataroot  = '/home/user/htdocs/moodledata';
$CFG->admin     = 'admin';

$CFG->themerev = time();

$CFG->directorypermissions = 02777;

@ini_set('display_errors', '1');
$CFG->debugdisplay = 1;
@error_reporting(E_ALL);
$CFG->debug = E_ALL;

require_once(__DIR__ . '/lib/setup.php');
