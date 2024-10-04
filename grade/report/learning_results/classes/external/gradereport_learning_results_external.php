<?php

namespace gradereport_learning_results\external;

defined('MOODLE_INTERNAL') || die;


use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

class gradereport_learning_results_external extends external_api {

    public static function sort_table_parameters() {
        return new external_function_parameters([
            'filterCourse' => new external_value(PARAM_TEXT, 'Filter course'),
            'sortBy' => new external_value(PARAM_TEXT, 'Sort by'),
            'search' => new external_value(PARAM_TEXT, 'Search term'),
            'type' => new external_value(PARAM_TEXT, 'type'),
        ]);
    }

    /**
     * sort data return html 
     *
     * @param string $filterCourse
     * @param string $sortBy
     * @param string|null $search
     * @return string
     */
    public static function sort_table(string $filterCourse, string $sortBy, string $search = "", string $type): string {
        global $PAGE;

        $output = $PAGE->get_renderer('gradereport_learning_results');
        
        $html = $output->display_report_table($filterCourse, $sortBy, $search, $type);

        return $html;
    }



    public static function export_excel(string $filterCourse, string $sortBy, string $search = "", string $type): void
    {
        global $PAGE;
        $output = $PAGE->get_renderer('gradereport_learning_results');
        $output->export_excel($filterCourse, $sortBy, $search, $type);
    }
    public static function sort_table_returns() {
        return new external_value(PARAM_TEXT, 'The result');
    }
}
