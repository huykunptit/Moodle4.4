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
 * @package    gradereport_learning_results
 * @author     Andreas Grabs <info@grabs-edv.de>
 * @copyright  2020 Andreas Grabs EDV-Beratung
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_learning_results\output;

require_once(__DIR__ . '../../../../../../vendor/autoload.php');


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * A dummy renderer class just to make it overridable by themes.
 *
 * @copyright  2020 Andreas Grabs EDV-Beratung
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * export report file csv
     *
     * @return void
     */
    public function export_excel(string $filterCourse = 'all', string $sortby = 'completed', ?string $search = '', string $type = 'DESC'): bool 
    {
        $courses = self::get_course_grades($filterCourse, $sortby, $search, $type);
        
        if(empty($courses)){
            return false;
        }
        // Tạo một Spreadsheet mới
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        $sheet->setCellValue('A2', 'Tên khóa');
        $sheet->setCellValue('B2', 'Tỷ lệ hoàn thành');
        $sheet->setCellValue('C2', 'Bài tập quá trình');
        $sheet->setCellValue('D2', 'Thí nghiệm thực hành');
        $sheet->setCellValue('E2', 'Bài tập thu hoạch');
    
        $row = 3; 
    
        foreach ($courses as $course) {
            $sheet->setCellValue('A' . $row, $course['coursename']);
            $sheet->setCellValue('B' . $row, $course['completion_percentage'] . '%');
    
            $qua_trinh = 0;
            $thi_nghiem = 0;
            $thu_hoach = 0;
    
            foreach ($course['grades'] as $grade) {
                if ($grade['item_name'] === 'Bài tập quá trình') {
                    $qua_trinh = is_null($grade['grade']) ? 0 : $grade['grade'];
                } elseif ($grade['item_name'] === 'Thí nghiệm thực hành') {
                    $thi_nghiem = is_null($grade['grade']) ? 0 : $grade['grade'];
                } elseif ($grade['item_name'] === 'Bài tập thu hoạch') {
                    $thu_hoach = is_null($grade['grade']) ? 0 : $grade['grade'];
                }
            }
    
            $sheet->setCellValue('C' . $row, $qua_trinh);
            $sheet->setCellValue('D' . $row, $thi_nghiem);
            $sheet->setCellValue('E' . $row, $thu_hoach);
    
            $row++;
        }
    
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
            'font' => [
                'name' => 'Times New Roman', 
                'size' => 13, 
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A2:E' . ($row - 1))->applyFromArray($styleArray);


        $headerStyle = [
            'font' => [
                'name' => 'Times New Roman', 
                'bold' => true,
                'size' => 13, 
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        
        $sheet->getStyle('A2:E2')->applyFromArray($headerStyle);

        foreach (range('A', 'E') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        // Xuất file Excel ra trình duyệt
        $writer = new Xlsx($spreadsheet);
        
        // Thiết lập tiêu đề HTTP để trình duyệt tải file về
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="learning_results.xlsx"');
        header('Cache-Control: max-age=0');
    
        // Ghi dữ liệu ra output và kết thúc
        $writer->save('php://output');
        exit;
    }
    
    
      
    /**
     * get grades course by user 
     *
     * @param string $filterCourse
     * @param string $sortby
     * @param string|null $search
     * @param string $type
     * @return string
     */
    public static function get_course_grades(string $filterCourse = 'all', string $sortby = 'completed', ?string $search = '', string $type = 'DESC'): array
    {
        global $USER, $DB;
        $courses = enrol_get_users_courses($USER->id, true, 'id, fullname');

        if (empty($courses)) {
            return [];
        }

        $filter = '';
        if ($filterCourse == 'active') {
            $filter = "AND cc.timecompleted IS NULL";
        } elseif ($filterCourse == 'archived') {
            $filter = "AND cc.timecompleted IS NOT NULL";
        }

        $courseids = array_column($courses, 'id');
        $courseids_list = implode(',', array_map('intval', $courseids)); 


        // Define valid sort columns and directions
        $validSortColumnsSql = [
            'coursename' => 'c.fullname',
            'completed' => 'cc.timecompleted',
        ];

        $sortbySql = $validSortColumnsSql[$sortby] ?? 'c.fullname'; 
        $validOrderDirs = ['ASC', 'DESC'];
        $type = in_array(strtoupper($type), $validOrderDirs) ? strtoupper($type) : 'ASC'; 

        // SQL Query
        $sql = "SELECT 
                    c.id AS courseid,
                    c.fullname AS coursename,
                    gi.id AS item_id,
                    gi.itemname AS item_name,
                    gg.finalgrade AS grade,
                    cc.timecompleted
                FROM {grade_items} gi
                JOIN {grade_grades} gg ON gi.id = gg.itemid
                JOIN {course} c ON c.id = gi.courseid
                LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = gg.userid
                WHERE gi.courseid IN ($courseids_list)
                AND gg.userid = :userid
                $filter
                AND gi.itemname IN ('Bài tập quá trình', 'Thí nghiệm thực hành', 'Bài tập thu hoạch')
                ORDER BY $sortbySql $type";

        // Prepare and execute the query
        $params = ['userid' => $USER->id];
        $grades = $DB->get_records_sql($sql, $params);


        if(empty($grades)){
            return [];
        }
        // Process results
        $grades_lookup = [];
        foreach ($grades as $grade) {
            $grades_lookup[$grade->courseid][] = [
                'item_id' => $grade->item_id,
                'item_name' => $grade->item_name,
                'grade' => $grade->grade,
            ];
        }

        $result = [];
        foreach ($courses as $course) {

            if ($search && stripos($course->fullname, $search) === false) {
                continue; 
            }
            $completion_percentage = (int) \core_completion\progress::get_course_progress_percentage($course, $USER->id);
            if ($filterCourse == 'active' && $completion_percentage >= 100) {
                continue;  
            }
            elseif($filterCourse == 'archived' && $completion_percentage < 100){
                continue;
            }
            
            $grades_data = $grades_lookup[$course->id] ?? [];
            
            $course_complete = self::courseComplete($course->id, $USER->id);

            $result[] = [
                'courseid' => $course->id,
                'coursename' => $course->fullname,
                'grades' => $grades_data,
                'completion_percentage' => $completion_percentage,
                'course_complete' => $course_complete,
            ];
        }

        return $result;
    }



    /**
     * get html table 
     *
     * @param array $result
     * @return string
     */
    public static function display_report_table(string $filterCourse = 'all', string $sortby = 'completed', ?string $search = '', string $type = 'DESC'): string {

        $courses = self::get_course_grades($filterCourse, $sortby, $search, $type);

        if(empty($courses)){

        }
        $html = '
        <div class="table-container" style="margin-top: 20px;" id="table-data">
            <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th class="sortable" data-sort="name" style="text-align:center;">Tên khóa</th>
                        <th class="sortable" data-sort="completion" style="text-align:center;">Tỷ lệ hoàn thành</th>
                        <th class="sortable" data-sort="exercise" style="text-align:center;">Bài tập quá trình</th>
                        <th class="sortable" data-sort="experiment" style="text-align:center;">Thí nghiệm thực hành</th>
                        <th class="sortable" data-sort="assignment" style="text-align:center;">Bài tập thu hoạch</th>
                    </tr>
                </thead>
                <tbody>';
    
            if (!empty($courses)) {
                foreach ($courses as $course) {
                    
                    $completion_percentage = htmlspecialchars($course['completion_percentage']) . '%';
                    $qua_trinh = '_'; 
                    $thi_nghiem = '_'; 
                    $thu_hoach = '_'; 
        
                    foreach ($course['grades'] as $grade) {
                        if ($grade['item_name'] === 'Bài tập quá trình') {
                            $qua_trinh = is_null($grade['grade']) ? '_' : htmlspecialchars($grade['grade']);
                        } elseif ($grade['item_name'] === 'Thí nghiệm thực hành') {
                            $thi_nghiem = is_null($grade['grade']) ? '_' : htmlspecialchars($grade['grade']);
                        } elseif ($grade['item_name'] === 'Bài tập thu hoạch') {
                            $thu_hoach = is_null($grade['grade']) ? '_' : htmlspecialchars($grade['grade']);
                        }
                    }
        
                    $html .= '
                    <tr>
                        <td >' . htmlspecialchars($course['coursename']) . '</td>
                        <td style="text-align:center;">' . $completion_percentage . '</td>
                        <td style="text-align:center;">' . $qua_trinh . '</td>
                        <td style="text-align:center;">' . $thi_nghiem . '</td>
                        <td style="text-align:center;">' . $thu_hoach . '</td>
                    </tr>';
                }
            } else {
                $html .= '<tr><td colspan="5" style="text-align:center; font-size:20px;" class="text-secondary">Không có khóa học nào.</td></tr>';
            }
    
        $html .= '
                </tbody>
            </table>
        </div>';
    
        return $html;
    }
    



    /**
     * check completed course
     *
     * @param integer $courseid
     * @param integer $userid
     * @return integer
     */
    private static function courseComplete(int $courseid, int $userid): int 
    {
        global $CFG, $DB;
        
        require_once("{$CFG->libdir}/completionlib.php");

        $course = $DB->get_record('course', ['id' => $courseid]);
        if(!$course){
            return 0;
        }

        $cinfo = new \completion_info($course); 
        $iscomplete = $cinfo->is_course_complete($userid);

        return $iscomplete ? 1 : 0;
    }


    /**
     * Renders the filter options as a Mustache template.
     *
     * @return string The rendered HTML.
     */
    public function render_filters(): string
    {
        $data = [
            'filters' => [
                ['value' => 'all', 'label' => get_string('all', 'gradereport_learning_results'), 'selected' => true],
                ['value' => 'active', 'label' => get_string('active', 'gradereport_learning_results')],
                ['value' => 'archived', 'label' => get_string('archived', 'gradereport_learning_results')]
            ],
            'searchPlaceholder' => get_string('search', 'gradereport_learning_results'),
            'sortOptions' => [
                ['value' => 'coursename', 'label' => get_string('sort_by_course_name', 'gradereport_learning_results')],
                ['value' => 'completed', 'label' => get_string('sort_by_course_completed', 'gradereport_learning_results')],
            ],
            'viewTypes' => [
                ['value' => 'ASC', 'label' => get_string('up', 'gradereport_learning_results')],
                ['value' => 'DESC', 'label' => get_string('down', 'gradereport_learning_results')]
            ],

            'exportUrl' => new \moodle_url('/grade/report/learning_results/export.php',[
                'filterCourse' => 'all',
                'sortBy' => 'coursename',
                'search' => '',
                'type' => 'ASC',
            ])
        ];

        return $this->render_from_template('gradereport_learning_results/filters', $data);
    }

}
