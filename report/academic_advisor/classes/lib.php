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

namespace report_academic_advisor;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '../../../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->dirroot . '/grade/querylib.php');

class lib {

    /**
     * Create and export Excel file for completion rates.
     *
     * @param integer $course
     * @param array $students
     * @param integer $total_criteria
     * @param integer $completed_criteria
     * @return void
     */
    public static function createExcel($course, array $students) {
        global $DB;
    
        $in = new \completion_info($course);
    
        // Tạo một đối tượng Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
    
        $sheet->setCellValue('A1', 'BÁO CÁO CHO CỐ VẤN HỌC TẬP');
        $sheet->mergeCells('A1:J1'); // Gộp ô từ A1 đến J1
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
    
        $headers = [
            'STT', 
            'Họ tên', 
            'Mã SV', 
            'Lớp', 
            'Ngày tháng năm sinh', 
            'SĐT', 
            'Email', 
            'Phần trăm hoàn thành môn học', 
            'Các mục chưa hoàn thành', 
            'Lần cuối truy cập hệ thống'
        ];
    
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '2', $header);
            $column++;
        }
    
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setWidth(25);
        }
    
        $rowNumber = 3; 
        $index = 1; 
    
        foreach($students as $student) {
            $completions = $in->get_completions($student->id);
            $completed_criteria = 0;
            $incomplete_criteria = [];
            $total_criteria = count($completions);
    
            foreach ($completions as $completion) {
                if ($completion->is_complete()) {
                    $completed_criteria++;
                } else {
                    // Lấy chi tiết của tiêu chí chưa hoàn thành
                    $criteria_details = $completion->get_criteria()->get_details($completion);
                    
                    // Kiểm tra nếu chi tiết có trường 'criteria'
                    if (!empty($criteria_details['criteria'])) {
                        if (is_array($criteria_details['criteria'])) {
                            $incomplete_criteria = array_merge($incomplete_criteria, self::get_text_from_html($criteria_details['criteria']));
                        } else {
                            $incomplete_criteria[] = self::get_text_from_html($criteria_details['criteria']);
                        }
                    }
                }
            }

            

            $completion_percentage = ($total_criteria > 0) ? ($completed_criteria / $total_criteria) * 100 : 0;
            
            $last_access = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $student->id, 'courseid' => $course->id]);
            $last_access = ($last_access) ? date('Y-m-d H:i:s', $last_access) : 'Chưa truy cập';
    
            $birthday = profile_user_record($student->id, false)->birthday ?? '_';
    
            
            $sheet->setCellValue('A' . $rowNumber, $index);
            $sheet->setCellValue('B' . $rowNumber, fullname($student));
            $sheet->setCellValue('C' . $rowNumber, $student->idnumber);
            $sheet->setCellValue('D' . $rowNumber, str_replace("Bài giảng môn học: ", "", $course->summary));
            $sheet->setCellValue('E' . $rowNumber, $birthday);
            $sheet->setCellValue('F' . $rowNumber, $student->phone1 ?? '_');
            $sheet->setCellValue('G' . $rowNumber, $student->email ?? '_');
            $sheet->setCellValue('H' . $rowNumber, round($completion_percentage, 2) . '%');
            $criteria_output = !empty($incomplete_criteria) ? implode(', ', $incomplete_criteria) : 'Đã hoàn thành';

            $sheet->setCellValue('I' . $rowNumber, strip_tags($criteria_output));
    
            $sheet->setCellValue('J' . $rowNumber, $last_access);
            $sheet->getStyle('I' . $rowNumber)->getAlignment()->setWrapText(true);
            
            $index++;
            $rowNumber++;
        }

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn(); 

        // Định nghĩa kiểu viền
        $borderStyle = [
            'font' => [
                'name' => 'Times New Roman'
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],  // Màu đen
                ],
            ],
        ];

        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFF'] // Yellow background
            ]
        ];
        $sheet->getStyle("A2:J2")->applyFromArray($headerStyle);
        $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray($borderStyle);
    
        $writer = new Xlsx($spreadsheet);
        $filename = 'bao_cao_hoc_tap.xlsx';
    
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
    
        $writer->save('php://output');
        exit;
    }
    

    // Hàm lấy nội dung từ HTML
    private static function get_text_from_html($html) {
        return strip_tags($html);
    }

}
