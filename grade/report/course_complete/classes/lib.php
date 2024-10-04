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

namespace gradereport_course_complete;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '../../../../../vendor/autoload.php');

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
    public static function createExcel($course, array $students, $criteria) {
        // Create new Spreadsheet object
        $completion = new \completion_info($course);
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('TỶ LỆ HOÀN THÀNH MÔN HỌC');
    
        $worksheet->getColumnDimension('A')->setWidth(5);
        $worksheet->getColumnDimension('B')->setWidth(40);
        $worksheet->getColumnDimension('C')->setWidth(20);
        $worksheet->getColumnDimension('D')->setWidth(20);
        $worksheet->getColumnDimension('E')->setWidth(35);
        $worksheet->getColumnDimension('F')->setWidth(35);
        $worksheet->getColumnDimension('G')->setWidth(15);
        $worksheet->getColumnDimension('H')->setWidth(15);
        // Define headers.
        $headers = ['STT', 'Họ tên', 'Mã SV', 'SDT', 'Email', 'Môn học', 'Hoàn thành', 'Tỷ lệ'];
    
        // Define styles for headers.
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 15,
                'name' => 'Times New Roman'
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

        $headertable = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'name' => 'Times New Roman'
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
    
        // Define styles for default cells (from row 2 onwards).
        $defaultStyle = [
            'font' => [
                'size' => 12,
                'name' => 'Times New Roman'
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
    
        // Merge cells from A1 to H1 and set title.
        $worksheet->mergeCells('A1:H1');
        $worksheet->setCellValue('A1', 'TỶ LỆ HOÀN THÀNH MÔN HỌC');
        $worksheet->getStyle('A1')->applyFromArray($headerStyle);
    
        // Write the headers to the second row.
        foreach ($headers as $col => $header) {
            $worksheet->setCellValueByColumnAndRow($col + 1, 2, $header);
            $worksheet->getStyleByColumnAndRow($col + 1, 2)->applyFromArray($headertable);
        }
    
        // Loop through students and populate the data.
        $row = 3; 
        $index = 0;
        foreach ($students as $student) {
            $index++;
            $total_criteria = count($criteria);
            $completed_criteria = 0;
    
            // Check user completion for each criterion
            foreach ($criteria as $criterion) {
                $criteria_completion = $completion->get_user_completion($student->id, $criterion);
                if ($criteria_completion && $criteria_completion->is_complete()) {
                    $completed_criteria++;
                }
            }
    
            // Calculate completion percentage
            $completion_percentage = ($total_criteria > 0) ? round(($completed_criteria / $total_criteria) * 100, 2) : 0;
    
            // Write data to the worksheet with default format applied to each cell.
            $worksheet->setCellValueByColumnAndRow(1, $row, $index);
            $worksheet->setCellValueByColumnAndRow(2, $row, fullname($student) ?? '');
            $worksheet->setCellValueByColumnAndRow(3, $row, strtoupper($student->username));
            $worksheet->setCellValueByColumnAndRow(4, $row, $student->phone1 ?? $student->phone2);
            $worksheet->setCellValueByColumnAndRow(5, $row, $student->email);
            $worksheet->setCellValueByColumnAndRow(6, $row, str_replace("Bài giảng môn học: ", "", $course->summary));
            $worksheet->setCellValueByColumnAndRow(7, $row, $completed_criteria . "/" . $total_criteria);
            $worksheet->setCellValueByColumnAndRow(8, $row, (int)$completion_percentage . '%');
    
            $worksheet->getStyleByColumnAndRow(1, $row, 8, $row)->applyFromArray($defaultStyle);
            $row++;
        }
    
        // Apply border style to the entire table
        $worksheet->getStyle('A1:H' . ($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        $worksheet->getStyle('C3:H' . ($row - 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $worksheet->getStyle('A3:A' . ($row - 1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
        // Save Excel file
        $writer = new Xlsx($spreadsheet);
        $filename = "ty_le_hoan_thanh_mon_hoc_" . str_replace("Bài giảng môn học: ", "", $course->summary) . ".xlsx";
        
        // Set correct headers for file download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Output to browser (or save to file)
        $writer->save('php://output');
    }
    
}
