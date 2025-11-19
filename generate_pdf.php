<?php
session_start();
require('fpdf/fpdf.php');
include("config.php"); // Assuming this file contains $conn and database connection details

// ===============================
// CONFIGURATION - HIGH-CONTRAST MODERN DESIGN
// ===============================
$primaryColor = [28, 40, 51];   // Dark Charcoal/Navy (Main professional color)
$accentColor = [52, 152, 219];  // Bright Sky Blue (Key Accent/Success Line)
$sectionHeaderBg = [236, 240, 241]; // Very Light Gray (Section Title Background)
$detailBgColor = [250, 250, 250]; // Near White (Detail field background)
$detailLabelColor = [100, 100, 100]; // Medium Gray (Labels)
$detailValueColor = [30, 30, 30];    // Near Black (Values)
$successColor = [39, 174, 96]; // Green for 'Uploaded'
$failColor = [231, 76, 60];    // Red for 'Not Uploaded'
$logoPath = 'logo.png';
$logoWidth = 40; 

// ===============================
// GET APPLICATION ID (UNCHANGED)
// ===============================
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($application_id === 0) {
    die("Invalid application ID.");
}

// ===============================
// 1. FETCH ALL DATA (UNCHANGED)
// ===============================
$query = "SELECT a.*,
             ss.name AS student_name,
             ss.dob AS student_dob,
             ss.application_no AS student_app_no,
             s.name AS scholarship_name,
             c.name AS college_name,
             p.name AS program_name
          FROM applications a
          JOIN scholarship_students ss ON a.student_id = ss.id
          JOIN scholarships s ON a.scholarship_id = s.id
          LEFT JOIN colleges c ON a.institution_name = c.id
          LEFT JOIN programs p ON a.course = p.id
          WHERE a.id = $application_id";
$result = mysqli_query($conn, $query);
$application = mysqli_fetch_assoc($result);

if (!$application) {
    die("Application not found.");
}

// Fetch all uploaded files
$files_query = mysqli_query($conn, "SELECT file_type FROM application_files WHERE application_id = $application_id");
$files = [];
while ($file_row = mysqli_fetch_assoc($files_query)) {
    $files[$file_row['file_type']] = 'Uploaded';
}
$files['income_doc'] = !empty($application['income_doc']) ? 'Uploaded' : 'Not Uploaded';
$files['id_doc'] = !empty($application['id_doc']) ? 'Uploaded' : 'Not Uploaded';


// ===============================
// HELPER FUNCTIONS (UNCHANGED)
// ===============================

function sectionTitle($pdf, $title, $primaryColor, $sectionHeaderBg, $accentColor) {
    $pdf->Ln(5);
    $pdf->SetFillColor($sectionHeaderBg[0], $sectionHeaderBg[1], $sectionHeaderBg[2]);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('Arial', 'B', 11);
    
    $height = 8;
    $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->Rect(15, $pdf->GetY(), 3, $height, 'F');
    
    $pdf->SetFillColor($sectionHeaderBg[0], $sectionHeaderBg[1], $sectionHeaderBg[2]);
    $pdf->SetX(18);
    $pdf->Cell(0, $height, "  " . strtoupper($title), 0, 1, 'L', true);
    $pdf->Ln(2);
}

function drawDetailSegmentCell($pdf, $label, $value, $labelColor, $valueColor, $width, $detailBgColor) {
    $normalizedValue = is_null($value) ? '' : trim((string)$value);
    
    if ($pdf->GetY() > 270) {
        $pdf->AddPage();
    }
    
    $padding_left = 3;
    $padding_top = 2;
    $label_width_fixed = 40; 

    $start_x = $pdf->GetX();
    $start_y = $pdf->GetY();
    $height = 10;
    
    $pdf->SetFillColor($detailBgColor[0], $detailBgColor[1], $detailBgColor[2]);
    $pdf->Rect($start_x, $start_y, $width, $height, 'F');
    
    $pdf->SetY($start_y + $padding_top);
    $pdf->SetX($start_x + $padding_left);
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor($labelColor[0], $labelColor[1], $labelColor[2]);
    $pdf->Cell($label_width_fixed, 5, $label . ':', 0, 0, 'L');
    
    $pdf->SetY($start_y + $padding_top);
    $pdf->SetX($start_x + $padding_left + $label_width_fixed); 
    
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor($valueColor[0], $valueColor[1], $valueColor[2]);
    $pdf->MultiCell($width - $padding_left - $label_width_fixed, 5, $normalizedValue, 0, 'L');

    $pdf->SetY($start_y);
    $pdf->SetX($start_x + $width);
}

function drawSmartSegmentSection($pdf, $title, $data, $primaryColor, $sectionHeaderBg, $accentColor, $labelColor, $valueColor, $detailBgColor) {
    $filteredData = array_filter($data, function($value) {
        $normalizedValue = is_null($value) ? '' : trim((string)$value);
        if (in_array(strtolower($normalizedValue), ['no', 'n/a', 'not uploaded', ''])) {
             return false;
        }
        return !empty($normalizedValue);
    });

    if (empty($filteredData)) {
        return;
    }
    
    sectionTitle($pdf, $title, $primaryColor, $sectionHeaderBg, $accentColor);

    $cell_width = 85; 
    $data_keys = array_keys($filteredData);
    $data_values = array_values($filteredData);
    $count = count($filteredData);
    
    $line_height = 12; 

    $i = 0;
    while ($i < $count) {
        $pdf->SetX(15);
        
        $label1 = $data_keys[$i];
        $value1 = $data_values[$i];
        drawDetailSegmentCell($pdf, $label1, $value1, $labelColor, $valueColor, $cell_width, $detailBgColor);

        $i++;
        if ($i < $count) {
            $pdf->Cell(10, 10, '', 0, 0); 
            $label2 = $data_keys[$i];
            $value2 = $data_values[$i];
            drawDetailSegmentCell($pdf, $label2, $value2, $labelColor, $valueColor, $cell_width, $detailBgColor);
        }

        $pdf->Ln($line_height); 
        $i++;
    }
    
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetLineWidth(0.2);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY()); 
    $pdf->Ln(1);
}

function drawEducationTable($pdf, $title, $data1, $data2, $primaryColor, $sectionHeaderBg, $accentColor, $detailBgColor) {
    $hasData1 = !empty(array_filter($data1, function($v) { return !empty($v) && strtolower($v) != 'n/a'; }));
    $hasData2 = !empty(array_filter($data2, function($v) { return !empty($v) && strtolower($v) != 'n/a'; }));

    if (!$hasData1 && !$hasData2) {
        return;
    }
    
    sectionTitle($pdf, $title, $primaryColor, $sectionHeaderBg, $accentColor);
    
    $pdf->SetDrawColor(200, 200, 200); 
    $pdf->SetLineWidth(0.1);
    
    $pdf->SetX(15);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]); 
    $pdf->SetTextColor(255, 255, 255); 
    
    $headerWidths = [40, 35, 45, 25, 25]; 
    $headers = ['Examination', 'Year & Reg. No.', 'Board / University', 'Class/Grade', 'Marks (%)'];
    
    foreach($headers as $i => $header) {
        $pdf->Cell($headerWidths[$i], 7, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFillColor($detailBgColor[0], $detailBgColor[1], $detailBgColor[2]); 

    $drawRow = function($pdf, $data, $headerWidths, $detailBgColor) {
        $pdf->SetX(15);
        $fields = ['name', 'year_reg', 'board', 'class', 'marks'];

        foreach($fields as $i => $field) {
            $value = $data[$field] ?? '';
            $displayValue = (empty($value) || strtolower($value) == 'n/a') ? '-' : $value;
            $align = ($field == 'marks') ? 'C' : 'L';
            
            $pdf->SetFillColor($detailBgColor[0], $detailBgColor[1], $detailBgColor[2]); 
            $pdf->Cell($headerWidths[$i], 7, $displayValue, 1, 0, $align, true);
        }
        $pdf->Ln();
    };

    if ($hasData1) $drawRow($pdf, $data1, $headerWidths, $detailBgColor);
    if ($hasData2) $drawRow($pdf, $data2, $headerWidths, $detailBgColor);
    
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetLineWidth(0.2);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY()); 
    $pdf->Ln(1);
}


// ===============================
// PDF START (UNCHANGED HEADER)
// ===============================
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 8, 15); 
$pdf->SetAutoPageBreak(true, 8); 

$pdf->SetY(8);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Rect(0, 0, $pdf->GetPageWidth(), 35, 'F'); 

$pdf->Image($logoPath, 15, 14, $logoWidth);

$pdf->SetTextColor(255, 255, 255); 
$pdf->SetY(12);
$pdf->SetX(15 + $logoWidth + 10);
$pdf->SetFont('Arial', 'B', 16);
$pdf->MultiCell(
    0, 7,
    "SCHOLARSHIP APPLICATION RECEIPT",
    0, 'L'
);

$pdf->SetX(15 + $logoWidth + 10);
$pdf->SetFont('Arial', 'I', 10); 
$pdf->Cell(0, 5, "Application No: " . $application['student_app_no'], 0, 1, 'L');
$pdf->SetX(15 + $logoWidth + 10);
$pdf->Cell(0, 5, "Date: " . date("d-M-Y"), 0, 1, 'L');

$pdf->SetY(40); 
// --- END MODERN HEADER ---

// ===============================
// CONTENT SECTIONS (REVISED APP DATA ARRAY)
// ===============================

// Application Details
$appData = [
    'Scholarship Name' => $application['scholarship_name'],
    'Academic Year'    => $application['academic_year'],
    'Institution'      => $application['college_name'] ?? $application['institution_name'],
    'Course'           => $application['program_name'] ?? $application['course'],
    'Year' 	       => $application['year_of_study'],
    'Semester'         => $application['semester']
];
drawSmartSegmentSection($pdf, 'Application Details', $appData, $primaryColor, $sectionHeaderBg, $accentColor, $detailLabelColor, $detailValueColor, $detailBgColor);

// Personal Details
$personalData = [
    'Applicant Name'   => $application['student_name'],
    'Date of Birth'    => date("d-m-Y", strtotime($application['student_dob'])),
    'Gender'           => $application['gender'],
    'Father\'s Name'   => $application['father_name'],
    'Mother\'s Name'   => $application['mother_name'],
    'Community'        => $application['community'],
    'Caste'            => $application['caste'],
    'Annual Income'    => $application['family_income'] ? 'Rs. ' . number_format($application['family_income']) : '',
    'Mobile'           => $application['mobile'],
    'Email'            => $application['email'],
    'Phone (STD)'      => $application['phone_std'], 
    'Address'          => $application['address']
];
drawSmartSegmentSection($pdf, 'Personal Details', $personalData, $primaryColor, $sectionHeaderBg, $accentColor, $detailLabelColor, $detailValueColor, $detailBgColor);

// Educational Qualification (Table)
$eduData1 = [
    'name'     => $application['exam_name_1'],
    'year_reg' => $application['exam_year_reg_1'],
    'board'    => $application['exam_board_1'],
    'class'    => $application['exam_class_1'],
    'marks'    => $application['exam_marks_1'],
];
$eduData2 = [
    'name'     => $application['exam_name_2'],
    'year_reg' => $application['exam_year_reg_2'],
    'board'    => $application['exam_board_2'],
    'class'    => $application['exam_class_2'],
    'marks'    => $application['exam_marks_2'],
];
drawEducationTable($pdf, 'Educational Qualification', $eduData1, $eduData2, $primaryColor, $sectionHeaderBg, $accentColor, $detailBgColor);

// Lateral Entry
$lateralData = [
    'Exam Passed'      => $application['lateral_exam_name'],
    'Year & Reg. No'   => $application['lateral_exam_year_reg'],
    'Percentage'       => $application['lateral_percentage'] ? $application['lateral_percentage'] . '%' : ''
];
drawSmartSegmentSection($pdf, 'Lateral Entry Details', $lateralData, $primaryColor, $sectionHeaderBg, $accentColor, $detailLabelColor, $detailValueColor, $detailBgColor);

// Special Claims
$claimsData = [
    'Sports Level'         => $application['sports_level'],
    'Ex-Servicemen'        => $application['ex_servicemen'],
    'Disabled'             => $application['disabled'],
    'Disability Category'  => ($application['disabled'] == 'Yes') ? $application['disability_category'] : '',
    'Parent VMRF Employee' => $application['parent_vmrf'],
    'Parent Details'       => ($application['parent_vmrf'] == 'Yes') ? $application['parent_vmrf_details'] : ''
];
drawSmartSegmentSection($pdf, 'Special Claims', $claimsData, $primaryColor, $sectionHeaderBg, $accentColor, $detailLabelColor, $detailValueColor, $detailBgColor);


// Document Status - always shows all document categories
$filesData = [
    'Income Document'       => $files['income_doc'] ?? 'Not Uploaded',
    'ID Document'           => $files['id_doc'] ?? 'Not Uploaded',
    'Sports Proof'          => $files['sports'] ?? 'Not Uploaded',
    'Ex-Servicemen Proof'   => $files['ex_servicemen'] ?? 'Not Uploaded',
    'Disability Proof'      => $files['disabled'] ?? 'Not Uploaded',
    'Parent VMRF Proof'     => $files['parent_vmrf'] ?? 'Not Uploaded'
];

sectionTitle($pdf, 'Document Upload Status', $primaryColor, $sectionHeaderBg, $accentColor);

$cell_width = 85;
$data_keys = array_keys($filesData);
$data_values = array_values($filesData);
$count = count($filesData);

// Constants for alignment in this manual loop
$padding_left = 3;
$padding_top = 2;
$label_width_fixed = 40; 
$height = 10;
$line_height = 12;

$i = 0;
while ($i < $count) {
    $pdf->SetX(15);
    
    // --- Column 1 ---
    $label1 = $data_keys[$i];
    $value1 = $data_values[$i];
    $displayColor1 = $value1 === 'Uploaded' ? $successColor : $failColor;

    $start_x = $pdf->GetX();
    $start_y = $pdf->GetY();
    
    // Draw Detail Background
    $pdf->SetFillColor($detailBgColor[0], $detailBgColor[1], $detailBgColor[2]); 
    $pdf->Rect($start_x, $start_y, $cell_width, $height, 'F');

    // Draw Label
    $pdf->SetY($start_y + $padding_top);
    $pdf->SetX($start_x + $padding_left);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor($detailLabelColor[0], $detailLabelColor[1], $detailLabelColor[2]);
    $pdf->Cell($label_width_fixed, 5, $label1 . ':', 0, 0, 'L');
    
    // Draw Value
    $pdf->SetY($start_y + $padding_top);
    $pdf->SetX($start_x + $padding_left + $label_width_fixed);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetTextColor($displayColor1[0], $displayColor1[1], $displayColor1[2]);
    $pdf->Cell($cell_width - $padding_left - $label_width_fixed, 5, $value1, 0, 0, 'L');
    
    $pdf->SetY($start_y);
    $pdf->SetX($start_x + $cell_width);

    // --- Column 2 ---
    $i++;
    if ($i < $count) {
        $pdf->Cell(10, $height, '', 0, 0); // Gap
        $label2 = $data_keys[$i];
        $value2 = $data_values[$i];
        $displayColor2 = $value2 === 'Uploaded' ? $successColor : $failColor;
        
        $start_x = $pdf->GetX();
        $start_y = $pdf->GetY();
        
        // Draw Detail Background
        $pdf->SetFillColor($detailBgColor[0], $detailBgColor[1], $detailBgColor[2]); 
        $pdf->Rect($start_x, $start_y, $cell_width, $height, 'F');

        // Draw Label
        $pdf->SetY($start_y + $padding_top);
        $pdf->SetX($start_x + $padding_left);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor($detailLabelColor[0], $detailLabelColor[1], $detailLabelColor[2]);
        $pdf->Cell($label_width_fixed, 5, $label2 . ':', 0, 0, 'L');
        
        // Draw Value
        $pdf->SetY($start_y + $padding_top);
        $pdf->SetX($start_x + $padding_left + $label_width_fixed);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor($displayColor2[0], $displayColor2[1], $displayColor2[2]);
        $pdf->Cell($cell_width - $padding_left - $label_width_fixed, 5, $value2, 0, 0, 'L');
        
        $pdf->SetY($start_y);
        $pdf->SetX($start_x + $cell_width);
    }

    $pdf->Ln($line_height); 
    $i++;
}

// Draw light bottom line for separation
$pdf->SetDrawColor(220, 220, 220);
$pdf->SetLineWidth(0.2);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY()); 


// Footer
$pdf->SetY(-10); 
$pdf->SetFont('Arial', 'I', 7); 
$pdf->SetTextColor(130, 130, 130);
$pdf->Cell(0, 5, 'This is an auto-generated application receipt. No signature required.', 0, 1, 'C');

// ===============================
// OUTPUT
// ===============================
$pdf->Output('I', 'Application_Receipt_' . $application['student_app_no'] . '.pdf');
exit();
?>