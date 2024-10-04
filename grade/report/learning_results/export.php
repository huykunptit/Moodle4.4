<?php
require_once '../../../config.php';
require_login();

$params = [
    'filterCourse' => optional_param('filterCourse', '', PARAM_TEXT),
    'search' => optional_param('search', '', PARAM_TEXT),
    'sortBy' => optional_param('sortBy', '', PARAM_TEXT),
    'type' => optional_param('type', '', PARAM_TEXT)
];


$plugin = $PAGE->get_renderer('gradereport_learning_results'); 
$csv = $plugin->export_excel(
    $params['filterCourse'],
    $params['sortBy'],
    $params['search'],
    $params['type']
);

