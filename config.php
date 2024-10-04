<?php  // Moodle configuration file

unset($CFG);
global $CFG;

$CFG = new stdClass();


$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';  
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'root';
$CFG->dbpass    = '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => 3306,
  'dbsocket' => '', 
  // 'dbcollation' => 'utf8mb4_general_ci',
);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


$CFG->wwwroot   = 'http://localhost';
$CFG->dataroot  = 'C:\\moodledata';
$CFG->admin     = 'admin';
$CFG->theme = 'boost';

//$CFG->allow_mass_enroll_feature=1;
$CFG->directorypermissions = 0777;
// //$CFG->block_configurable_reports_enable_sql_execution = 1;
$CFG->modchooserdefault=0;
$CFG->draft_area_bucket_capacity = 5000;



require_once(__DIR__ . '/lib/setup.php');

// Enable debugging
// @error_reporting(E_ALL | E_STRICT);
// @ini_set('display_errors', '1');
// define('DEBUGGING', true);
// define('DEBUG_DEVELOPER', 6143); // Show all debugging information

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
