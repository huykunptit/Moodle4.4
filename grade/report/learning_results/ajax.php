<?php

require_once '../../../config.php';
require_login();

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

$params = [
    'filterCourse' => optional_param('filterCourse', '', PARAM_TEXT),
    'search' => optional_param('search', '', PARAM_TEXT),
    'sortBy' => optional_param('sortBy', '', PARAM_TEXT),
    'type' => optional_param('type', '', PARAM_TEXT)
];

$action = optional_param('actions', '', PARAM_TEXT);

try {
    switch($action){
        case "sort":
            // Gọi hàm sort_table
            $result = gradereport_learning_results\external\gradereport_learning_results_external::sort_table(
                $params['filterCourse'],
                $params['sortBy'],
                $params['search'],
                $params['type']
            );
            echo json_encode($result);
            break;

        case "export":
            // Gọi hàm export_excel
            $result = gradereport_learning_results\external\gradereport_learning_results_external::export_excel(
                $params['filterCourse'],
                $params['sortBy'],
                $params['search'],
                $params['type']
            );
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

die();
