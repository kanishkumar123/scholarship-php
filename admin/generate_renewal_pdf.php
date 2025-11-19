<?php
session_start();
require('../fpdf/fpdf.php');
include("../config.php"); // Path is from admin/, so ../ is correct

// ===============================
// 1. CONFIGURATION
// ===============================
$primaryColor = [25, 118, 210]; // Blue
$accentGray   = [245, 245, 245];
$textColor    = [40, 40, 40];
$logoPath     = '../logo.png'; // Assumes logo.png is in the root folder
$logoWidth    = 120;

// ===============================
// 2. SECURITY & GET ID
// ===============================
if (!isset($_SESSION['admin_id'])) {
    die("Access Denied. Please log in as an admin.");
}

$renewal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($renewal_id === 0) {
    die("Invalid renewal ID.");
}

// ===============================
// 3. FETCH DATA
// ===============================
$query = "
    SELECT 
        r.*, 
        a.application_no,
        ss.name AS student_name,
        s.name AS scholarship_name
    FROM renewals r
    JOIN applications a ON r.application_id = a.id
    JOIN scholarship_students ss ON a.student_id = ss.id
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE r.id = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $renewal_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$renewal = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$renewal) {
    die("Renewal record not found.");
}

// ===============================
// 4. HELPER FUNCTIONS (Copied from your generate_pdf.php)
// ===============================
function sectionTitle($pdf, $title, $primaryColor) {
    $pdf->Ln(5);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, "   " . strtoupper($title), 0, 1, 'L', true);
    $pdf->Ln(3);
}

function drawBoxySection($pdf, $title, $data, $primaryColor) {
    sectionTitle($pdf, $title, $primaryColor);
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(40, 40, 40);
    foreach ($data as $label => $value) {
        if ($pdf->GetY() > 250) $pdf->AddPage();
        $pdf->SetX(15);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, $label . ":", 0, 0); // Increased label width
        $pdf->SetFont('Arial', '', 11);
        $current_x = $pdf->GetX();
        $current_y = $pdf->GetY();
        $pdf->MultiCell(0, 8, $value, 0, 'L');
        $pdf->SetY($pdf->GetY() + 1); 
    }
    $pdf->Ln(2);
}

// ===============================
// 5. PDF START
// ===============================
$pdf = new FPDF();
$pdf->AddPage();
$pageWidth = $pdf->GetPageWidth();
$pdf->SetMargins(15, 10, 15);
$pdf->SetAutoPageBreak(true, 15);

// Logo
$x = ($pageWidth - $logoWidth) / 2;
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, $x, 12, $logoWidth);
}
$pdf->Ln(35);

// Title
$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 10, "SCHOLARSHIP RENEWAL FORM", 0, 1, 'C');
$pdf->Ln(2);

// ===============================
// 6. CONTENT SECTIONS
// ===============================

// Student Details
$studentData = [
    'Student Name'     => $renewal['student_name'],
    'Application No'   => $renewal['application_no'],
    'Scholarship'      => $renewal['scholarship_name'],
    'Submitted At'     => date("d-m-Y H:i", strtotime($renewal['submitted_at']))
];
drawBoxySection($pdf, 'Student & Application Details', $studentData, $primaryColor);

// Renewal Academic Details
$acadData = [
    'Institution Name'  => $renewal['institution_name'],
    'Course'            => $renewal['course'],
    'Year of Study'     => $renewal['year_of_study'],
    'Semester'          => $renewal['semester'],
    'University Reg. No' => $renewal['university_reg_no']
];
drawBoxySection($pdf, 'Renewal Academic Details', $acadData, $primaryColor);

// Marks
$marksData = [
    'Semester 1 Marks' => $renewal['marks_sem1'],
    'Semester 2 Marks' => $renewal['marks_sem2'],
    'Sem 1 Marksheet'  => !empty($renewal['marks_sem1_file_path']) ? 'Uploaded' : 'Not Uploaded',
    'Sem 2 Marksheet'  => !empty($renewal['marks_sem2_file_path']) ? 'Uploaded' : 'Not Uploaded'
];
drawBoxySection($pdf, 'Marks & Documents', $marksData, $primaryColor);

// Other Scholarship
$otherData = [
    'Received Other Scholarship?' => $renewal['scholarship_receipt'],
    'Particulars'                 => ($renewal['scholarship_receipt'] == 'Yes') ? $renewal['scholarship_particulars'] : 'N/A',
    'Proof Document'              => ($renewal['scholarship_receipt'] == 'Yes') ? (!empty($renewal['scholarship_file_path']) ? 'Uploaded' : 'Not Uploaded') : 'N/A',
];
drawBoxySection($pdf, 'Other Scholarship Details', $otherData, $primaryColor);

// PDF Footer
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(130, 130, 130);
$pdf->Cell(0, 10, 'This is an auto-generated renewal receipt. No signature required.', 0, 1, 'C');

// ===============================
// 7. OUTPUT
// ===============================
$pdf->Output('I', 'Renewal_Receipt_' . $renewal['application_no'] . '_Year_' . $renewal['year_of_study'] . '.pdf');
exit();
?>