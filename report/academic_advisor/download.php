<?php

// Include necessary Moodle config and libraries
require_once '../../config.php';
require_once $CFG->libdir . '/excellib.class.php';

// Use PhpSpreadsheet classes for Excel generation
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Get the course ID and ensure it's valid
$courseid = required_param('course', PARAM_INT);

// Get the course data
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('report/academic_advisor:view', $context);

// Retrieve course completion progress data
$completion = new completion_info($course);
$progress = $completion->get_progress_all('', [], 0, 'u.lastname ASC', 0, 0, $context);

// Create a new Spreadsheet instance
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator('Moodle Academic Advisor Report')
    ->setTitle('Báo cáo cho cố vấn học tập');

// Set the title in the first row and merge cells
$sheet->setCellValue('A1', 'BÁO CÁO CHO CỐ VẤN HỌC TẬP');
$sheet->mergeCells('A1:J1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getRowDimension(1)->setRowHeight(30);

// Define header row and style it
$headers = ['STT', 'Họ tên', 'Mã SV', 'Lớp', 'Ngày tháng năm sinh', 'SĐT', 'Email', 'Phần trăm hoàn thành môn học', 'Các mục chưa hoàn thành', 'Lần cuối truy cập hệ thống'];
$colIndex = 'A';
$sheet->getRowDimension('2')->setRowHeight(35);
$sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman');
foreach ($headers as $header) {
    $sheet->setCellValue($colIndex . '2', $header);
    $sheet->getColumnDimension($colIndex)->setAutoSize(true); // Auto-size the columns
    $sheet->getStyle($colIndex . '2')->getFont()->setBold(true);
    $sheet->getStyle($colIndex . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $colIndex++;
}

// Style the header row
$sheet->getRowDimension(2)->setRowHeight(20);
$sheet->getStyle('A2:J2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Fill in the data
$rowIndex = 3;
$index = 1;
global $USER;
foreach ($progress as $user) {
    $user_info = $DB->get_record('user',['id'=>$user->id]);
    // Get the user's profile and completion data
    $birthday = profile_user_record($user_info->id);
    $dob_display = '';
    if (!empty($birthday->dob) && $birthday->dob != 0) {
        // Convert the dob timestamp to 'dd/mm/yyyy' format
        $dob_display = date('d/m/Y', $birthday->dob);
    }
    
    $completion_percentage = ($total_criteria > 0) ? ($completed_criteria / $total_criteria) * 100 : 0;
    $last_access = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $user_info->id, 'courseid' => $course->id]);

    // Retrieve uncompleted items for the user
    $rows = array();

    $completion = new completion_info($course);
    $in = new completion_info($course);
    $completions = $in->get_completions($user_info->id);


    $completed_criteria = 0;
    $incomplete_criteria = []; 
    $total_criteria = count($completions);
    // Loop through course criteria.
    foreach ($completions as $completion) {
        $criteria = $completion->get_criteria();

        $row = array();
       
        if ($completion->is_complete()) {
            $completed_criteria++;
        }
        else{
            $row['details'] = $criteria->get_details($completion);
        }

        $rows[] = $row; 
    }
    // Initialize an empty array to collect criteria details
    $criteria_details = [];

    // Loop through $rows to extract criteria details
    foreach ($rows as $row) {
        if (!empty($row['details']['criteria'])) {
            // Add the criteria details to the array
            $criteria_details[] = strip_tags($row['details']['criteria']);
        }
    }
    
    // If there are criteria details, join them as a string, otherwise display 'None'
    $display_criteria = !empty($criteria_details) ? implode("\n", $criteria_details) : 'None';
    
    // Set the criteria details into the Excel cell


    $phone = !empty($user_info->phone1) ? $user_info->phone1 : (!empty($user_info->phone2) ? $user_info->phone2 : 'No phone available');
    // Set the data for the row
    $sheet->setCellValue("A{$rowIndex}", $index);
    $sheet->setCellValue("B{$rowIndex}", fullname($user_info));
    $sheet->setCellValue("C{$rowIndex}", strtoupper($user_info->username));
    $sheet->setCellValue("D{$rowIndex}", $user_info->department);
    $sheet->setCellValue("E{$rowIndex}", $dob_display);
    $sheet->setCellValue("F{$rowIndex}", $phone ?? 'N/A');
    $sheet->setCellValue("G{$rowIndex}", $user_info->email);
    $sheet->setCellValue("H{$rowIndex}", round($completion_percentage,2) . '%');
    $sheet->setCellValue("I{$rowIndex}", $display_criteria);
    $sheet->setCellValue("J{$rowIndex}", $last_access ? date('d/m/Y H:i:s', $last_access) : 'N/A');

    // Apply borders and alignment for each row
    $sheet->getStyle("A{$rowIndex}:J{$rowIndex}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$rowIndex}:J{$rowIndex}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("I{$rowIndex}")->getAlignment()->setWrapText(true); 

    $rowIndex++;
    $index++;
}

// Adjust text alignment for specific columns
$sheet->getStyle('A3:A' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Center STT (Index)
$sheet->getStyle('H3:H' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Center completion percentage
$sheet->getStyle('J3:J' . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Center last access time

// Set auto-size for all columns to make it fit content
foreach (range('A', 'J') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}
$date = date('d/m/y');

// Set the filename for the downloaded Excel file
$filename = $date.'_bao_cao_ket_qua_ ' . $course->shortname . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"{$filename}\"");
header('Cache-Control: max-age=0');

// Output the file to the browser
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

