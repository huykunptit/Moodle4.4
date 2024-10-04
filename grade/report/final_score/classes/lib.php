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
 * This file contains functions used by the participation report
 *
 * @package    gradereport_final_score
 * @subpackage gradereport
 * @copyright  2013-2019 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_final_score;


defined('MOODLE_INTERNAL') || die;

define('FILENAME_SHORTEN', 30);

require_once(__DIR__ . '../../../../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;


require_once($CFG->dirroot . '/grade/querylib.php');

use \assignfeedback_editpdf\document_services;
use stdClass;

class lib {

    public static function get_grades_for_students(int $course_id, array $student_ids) {
        global $DB;
    

        if(empty($student_ids)){
            return [];
        }

        
        $student_ids_placeholder = implode(',', array_map('intval', $student_ids));
        

        $sql = "SELECT u.id AS student_id, u.firstname, u.lastname, u.username,
                       AVG(CASE 
                           WHEN gi.itemname LIKE '%ôn tập%' THEN COALESCE(gg.finalgrade, 0)
                           ELSE NULL
                       END) AS midterm_grade,
                       MAX(CASE 
                           WHEN gi.itemname LIKE '%Bài tập thu hoạch%' THEN COALESCE(gg.finalgrade, 0)
                           ELSE NULL
                       END) AS assignment_grade,
                       MAX(CASE 
                           WHEN gi.itemname LIKE '%Thực hành%' THEN COALESCE(gg.finalgrade, 0)
                           ELSE NULL
                       END) AS practical_grade
                  FROM {user} u
                  JOIN {grade_grades} gg ON gg.userid = u.id
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.courseid = :course_id
                   AND u.id IN (" . $student_ids_placeholder . ")
              GROUP BY u.id, u.firstname, u.lastname";
    
        // Execute the query
        $data = $DB->get_records_sql($sql, array(
            'course_id' => $course_id,
        ));
    
        return $data;
    }
    
    


    public static function createExcel($course, $students, $category, $teacher) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $day = date('d');
        $month = date('m');
        $year = date('Y');
        $string = '';
        if($month < 8){
            $string = 'Học kỳ 1 năm học ' . $year . '-'  . ($year + 1);
        }
        else{
            $string = 'Học kỳ 2 năm học ' . $year . '-'  . ($year + 1);
        }
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(10);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(6);
        $sheet->getColumnDimension('G')->setWidth(6);
        $sheet->getColumnDimension('H')->setWidth(6);
        $sheet->getColumnDimension('I')->setWidth(6);
        $sheet->getColumnDimension('J')->setWidth(10);
    
        // Merge cells for headers
        $sheet->mergeCells('A1:D1');
        $sheet->mergeCells('E1:J1');
        $sheet->mergeCells('A2:D2');
        $sheet->mergeCells('A3:D3');
        $sheet->mergeCells('E3:I3');
        $sheet->mergeCells('E5:H5');
        $sheet->mergeCells('A9:E9');

    
        // Title Rows
        $sheet->setCellValue('A1', 'HỌC VIỆN CÔNG NGHỆ BƯU CHÍNH VIỄN THÔNG');
        $sheet->setCellValue('A2', 'KHOA:');
        $sheet->setCellValue("A3", "MÔN: " .  mb_strtoupper(str_replace("Bài giảng môn học: ", "", $course->summary), 'UTF-8'));
        $sheet->setCellValue('E1', 'BẢNG ĐIỂM THÀNH PHẦN');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('E1')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('E1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C6")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("A9")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        // Subtitles and Information
        $sheet->setCellValue('B5', 'Học phần:');
        $sheet->setCellValue("C5", str_replace("Bài giảng môn học: ", "", $course->summary));
        $sheet->setCellValue('B6', 'Số tín chỉ:');
        $sheet->setCellValue('E5', "Nhóm: " . $category->name);
        $sheet->setCellValue('E3', $string);
    
        $sheet->getStyle("E5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E5')->getFont()->setBold(true)->setSize(12);


        $sheet->getStyle("B6")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B6')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('C6')->getFont()->setBold(true)->setSize(12);

        $sheet->getStyle("B5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B5')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('E3')->getFont()->setBold(false)->setSize(12);
        $sheet->getStyle('C5')->getFont()->setBold(true)->setSize(12);

        // Table headers
        $sheet->setCellValue('A8', 'Số TT');
        $sheet->setCellValue('B8', 'Mã SV');
        $sheet->setCellValue('C8', 'Họ, đệm');
        $sheet->setCellValue('D8', 'Tên');
        $sheet->setCellValue('E8', 'Lớp');
        $sheet->setCellValue('F8', 'Điểm chuyên cần');
        $sheet->setCellValue('G8', 'Điểm kiểm tra');
        $sheet->setCellValue('H8', 'Điểm TN-TH');
        $sheet->setCellValue('I8', 'Điểm BTL');
        $sheet->setCellValue('J8', 'Ghi chú');
        $sheet->setCellValue('A9', 'Trọng số:');
    
        // Rotate text in columns F, G, H
        $sheet->getStyle('A8')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A8:J8')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('F8:I8')->getAlignment()->setTextRotation(90);
        $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(13);

        $student_ids = array_map(function($student) {
            return $student->id;
        }, $students);
        
        $grades_data = self::get_grades_for_students($course->id, $student_ids);
        

        // Convert grades data into a lookup array
        $grades_lookup = [];
        foreach ($grades_data as $grade) {
            $grades_lookup[$grade->student_id] = $grade;
        }
        
        
        $row = 10; // Starting row for the sheet
        $index = 1; // Starting index for students
        
        foreach($students as $student) {
            // Get the student's grades from the lookup array
            $grade = isset($grades_lookup[$student->id]) ? $grades_lookup[$student->id] : (object)[
                'midterm_grade' => 0,
                'practical_grade' => 0,
                'assignment_grade' => 0
            ];
        
            $sheet->setCellValue('A'. $row, $index);
            $sheet->setCellValue('B'. $row, strtoupper($student->username));
            $sheet->setCellValue('C'. $row, $student->firstname);
            $sheet->setCellValue('D'. $row, $student->lastname);
            $sheet->setCellValue('E'. $row, str_replace("Bài giảng môn học: ", "", $course->summary));
            $sheet->setCellValue('F'. $row, (int) \core_completion\progress::get_course_progress_percentage($course, $student->id) / 10);
            $sheet->setCellValue("G". $row, round($grade->midterm_grade, 2)) ?? 0;
            $sheet->setCellValue("H". $row, round($grade->practical_grade, 2)) ?? 0;
            $sheet->setCellValue("I". $row, round($grade->assignment_grade, 2)) ?? 0;
            
            $row++;
            $index++;
        }
        
    
    
        // Style settings
        $styleArray = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        
        $last = $index + 9;

        // Apply border to header and row
        $sheet->getStyle('A8:J' . $last)->applyFromArray($styleArray);

        // Align content in columns C and D to the left
        $sheet->getStyle("C10:D" . $last)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        // Set font for the entire range
        $fontRange = 'A1:J' . ($last + 20);
        $sheet->getStyle($fontRange)->getFont()->setName('Times New Roman');

        // Merge cells for different sections
        $sheet->mergeCells("B" . ($last + 3) . ':D' . ($last + 3));
        $sheet->mergeCells("B" . ($last + 4) . ':D' . ($last + 4));
        $sheet->mergeCells("C" . ($last + 11) . ':E' . $last + 11);
        $sheet->mergeCells("E" . ($last + 2) . ':J' . ($last + 2));
        $sheet->mergeCells("E" . ($last + 3) . ':J' . ($last + 3));
        $sheet->mergeCells("E" . ($last + 4) . ':J' . ($last + 4));

        // Merge cells for the teacher's name
        $sheet->mergeCells("E" . ($last + 8) . ':J' . ($last + 8));
        $val = "(Ký và ghi rõ họ tên)";
        $sheet->setCellValue("B" . ($last + 4), $val);
        $sheet->setCellValue("E" . ($last + 4), $val);
        $sheet->setCellValue("B" . ($last + 3), "Trưởng Bộ môn");
        $sheet->setCellValue("E" . ($last + 3), "Giảng viên");
        // Set the teacher's information and institution
        $sheet->setCellValue("C" . ($last + 11), "Trung tâm Đào tạo Bưu chính Viễn thông I");
        $sheet->setCellValue("E" . ($last + 8), "GVC. " . $teacher->firstname . ' ' . $teacher->lastname);
        $sheet->getStyle("B" . $last + 1 . ':J' . $last +  11)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        // Get the current date
        

        // Format the date string
        $dateString = "Hà Nội, ngày $day tháng $month năm $year";

        // Set the date string to the merged cells in column E
        $sheet->setCellValue("E" . ($last + 2), $dateString);
        $sheet->getStyle("E" . ($last + 2))->getFont()->setItalic(true);
        $sheet->getStyle("E" . ($last + 4))->getFont()->setItalic(true);
        $sheet->getStyle("B" . ($last + 4))->getFont()->setItalic(true);
        $sheet->getStyle("B" . ($last + 3))->getFont()->setBold(true);
        $sheet->getStyle("E" . ($last + 3))->getFont()->setBold(true);
        $sheet->getStyle("E" . ($last + 8))->getFont()->setBold(true);
        $sheet->getStyle("D" . ($last + 10))->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);
        $filename = 'bang_diem_thanh_phan_'.str_replace("Bài giảng môn học: ", "", $course->summary).'.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
    
        $writer->save('php://output');
        exit;
    }
    

}
