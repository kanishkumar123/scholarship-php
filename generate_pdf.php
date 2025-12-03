<?php
session_start();
include("config.php");
require_once('tcpdf/tcpdf.php'); 

// --- 1. SECURITY & DATA FETCHING ---
if (!isset($conn) || mysqli_connect_errno()) {
    die("Database connection failed.");
}

$app_id = 0;
if (isset($_GET['id']) && isset($_SESSION['admin_id'])) {
    $app_id = intval($_GET['id']);
} elseif (isset($_SESSION['application_id'])) {
    $app_id = intval($_SESSION['application_id']);
} else {
    die("Access Denied.");
}

$query = "SELECT a.*, 
            sc.name AS scholarship_name, 
            sc.scholarshipcode AS scholarship_code, 
            c.name AS college_name, 
            p.name AS program_name,
            ss.academic_year
      FROM applications a
      JOIN scholarships sc ON a.scholarship_id = sc.id
      LEFT JOIN colleges c ON a.institution_name = c.id
      LEFT JOIN programs p ON a.course = p.id
      LEFT JOIN scholarship_students ss 
            ON ss.application_no = a.application_no 
            AND ss.scholarship_id = a.scholarship_id
      WHERE a.id = '$app_id'
      LIMIT 1";

$result = mysqli_query($conn, $query);
$app = mysqli_fetch_assoc($result);

if (!$app) die("Application not found.");

// --- 2. HELPER FUNCTIONS ---
function format_inr($num) {
    if (empty($num)) return '0';
    return number_format((float)preg_replace('/[^0-9.]/', '', $num), 0);
}

// --- 3. PDF CLASS ---
class MYPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = 'assets/logo.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, '', 5, 150, '', 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
        }

        // Heading
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(33, 37, 41); 
        $this->SetY(40); 
        $this->MultiCell(0, 10, 'Application Form for Educational Scholarships / Fee Concession under Various Schemes', 0, 'C', 0, 1, '', '', true);
        
        // Divider Line
        $this->SetDrawColor(0, 86, 179); 
        $this->SetLineWidth(0.5); 
        $this->Line(15, 52, 195, 52);
        
        // Reset
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.1);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// --- 4. SETUP PDF ---
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Scholarship Portal');
$pdf->SetTitle('Application_' . $app['application_no']);

// Margins (Top 58mm to clear header)
$pdf->SetMargins(15, 58, 15); 
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('helvetica', '', 11);

// Start Page 1
$pdf->AddPage();

// Common CSS
$css = '
<style>
    h3 {
        color: #0056b3;
        font-size: 14pt;
        font-weight: bold;
        border-bottom: 2px solid #eeeeee;
        padding-bottom: 5px;
        margin-top: 20px; 
        margin-bottom: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        padding: 6px;
    }
    th {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: bold;
        border: 1px solid #dee2e6;
        padding: 8px;
        font-size: 10pt;
    }
    td {
        border: 1px solid #dee2e6;
        color: #212529;
        padding: 8px;
        font-size: 10pt;
    }
    .label { font-weight: bold; background-color: #fdfdfd; }
    .highlight { color: #28a745; font-weight: bold; }
    .code-text { color: #dc3545; font-weight: bold; }
</style>
';

// --- 5. PAGE 1 CONTENT ---

// Summary Table
$scholarship_code = !empty($app['scholarship_code']) ? $app['scholarship_code'] : 'N/A';

$html_p1 = $css;
$html_p1 .= '
<table cellpadding="6" border="0" style="background-color: #f0f8ff; border: 1px solid #b8daff;">
    <tr>
        <td width="60%" style="border-right: 1px solid #dae0e5;">
            <span style="font-size:9pt; color:#666;">Scholarship Scheme:</span><br/>
            <strong>' . htmlspecialchars($app['scholarship_name']) . '</strong>
            <br/><br/>
            <span style="font-size:9pt; color:#666;">Scholarship Code:</span><br/>
            <span class="code-text">' . htmlspecialchars($scholarship_code) . '</span>
        </td>
        <td width="40%">
            <span style="font-size:9pt; color:#666;">Application Number:</span><br/>
            <span style="font-size:14pt; font-weight:bold;">' . htmlspecialchars($app['application_no']) . '</span>
            <br/><br/>
             <span style="font-size:9pt; color:#666;">Academic Year:</span><br/>
             <strong>' . htmlspecialchars($app['academic_year'] ?? 'N/A') . '</strong>
        </td>
    </tr>
</table>
<br/>';

// Personal Details
$dob = (!empty($app['dob'])) ? date('d-m-Y', strtotime($app['dob'])) : 'N/A';

$html_p1 .= '<h3>1. Personal & Institution Details</h3>';
$html_p1 .= '
<table cellpadding="6">
    <tr>
        <td class="label" width="40%">Full Name</td>
        <td class="value" width="60%">' . htmlspecialchars($app['name']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Email Address</td>
        <td class="value" width="60%">' . htmlspecialchars($app['email']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Mobile Number</td>
        <td class="value" width="60%">' . htmlspecialchars($app['mobile']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Date of Birth / Gender</td>
        <td class="value" width="60%">' . $dob . ' / ' . htmlspecialchars($app['gender']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Father\'s Name</td>
        <td class="value" width="60%">' . htmlspecialchars($app['father_name']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Mother\'s Name</td>
        <td class="value" width="60%">' . htmlspecialchars($app['mother_name']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Family Income</td>
        <td class="value" width="60%">Rs. ' . format_inr($app['family_income']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Institution Name</td>
        <td class="value" width="60%">' . htmlspecialchars($app['college_name'] ?? '-') . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Program / Course</td>
        <td class="value" width="60%">' . htmlspecialchars($app['program_name'] ?? '-') . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Current Year & Sem</td>
        <td class="value" width="60%">Year ' . htmlspecialchars($app['year_of_study']) . ' (Sem ' . htmlspecialchars($app['semester']) . ')</td>
    </tr>
    <tr>
        <td class="label" width="40%">Community</td>
        <td class="value" width="60%">' . htmlspecialchars($app['community']) . ' (' . htmlspecialchars($app['caste']) . ')</td>
    </tr>
</table>
<br/>'; 

// Address
$html_p1 .= '<h3>2. Permanent Address</h3>';
$html_p1 .= '<table cellpadding="6"><tr><td>' . nl2br(htmlspecialchars($app['address'])) . '</td></tr></table>
<br/>';

// Education Qualification
$html_p1 .= '<h3>3. Education Qualification</h3>';
$html_p1 .= '
<table cellpadding="5">
    <thead>
        <tr>
            <th width="25%">Name of the Examination</th>
            <th width="20%">Year of passing and reg. no</th>
            <th width="30%">Board/University/Institution</th>
            <th width="10%">Class/ Grade</th>
            <th width="15%" align="right">Marks Obtained (PCB/PCM/ overall %)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td width="25%">' . htmlspecialchars($app['exam_name_1']) . '</td>
            <td width="20%">' . htmlspecialchars($app['exam_year_reg_1']) . '</td>
            <td width="30%">' . htmlspecialchars($app['exam_board_1']) . '</td>
            <td width="10%">-</td>
            <td width="15%" align="right">' . htmlspecialchars($app['exam_marks_1']) . '</td>
        </tr>
        <tr>
            <td width="25%">' . htmlspecialchars($app['exam_name_2']) . '</td>
            <td width="20%">' . htmlspecialchars($app['exam_year_reg_2']) . '</td>
            <td width="30%">' . htmlspecialchars($app['exam_board_2']) . '</td>
            <td width="10%">-</td>
            <td width="15%" align="right">' . htmlspecialchars($app['exam_marks_2']) . '</td>
        </tr>';

if (!empty($app['lateral_exam_name'])) {
    $html_p1 .= '
        <tr>
            <td width="25%">' . htmlspecialchars($app['lateral_exam_name']) . '</td>
            <td width="20%">' . htmlspecialchars($app['lateral_exam_year_reg']) . '</td>
            <td width="30%">Lateral Entry</td>
            <td width="10%">-</td>
            <td width="15%" align="right">' . htmlspecialchars($app['lateral_percentage']) . '%</td>
        </tr>';
}

$html_p1 .= '</tbody></table>';

// WRITE PAGE 1
$pdf->writeHTML($html_p1, true, false, true, false, '');


// --- 6. PAGE 2 CONTENT ---

// FORCE PAGE BREAK
$pdf->AddPage();

// Additional Info
$html_p2 = $css; 
$html_p2 .= '<h3>4. If your claim is Sports / Ex-servicemen / Disabled Person :</h3>';
$html_p2 .= '
<table cellpadding="6">
    <tr>
        <td class="label" width="40%">Sports Category</td>
        <td class="value" width="60%">' . htmlspecialchars($app['sports_level']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Ex-Servicemen Quota</td>
        <td class="value" width="60%">' . htmlspecialchars($app['ex_servicemen']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Differently Abled</td>
        <td class="value" width="60%">' . htmlspecialchars($app['disabled']) . '</td>
    </tr>
</table>
<br/>';

// Parent VMRF Section
$html_p2 .= '<h3>5. Is Your Parents working with VMRF-DU</h3>';
$html_p2 .= '
<table cellpadding="6">
    <tr>
        <td class="label" width="40%">Parent working in VMRF?</td>
        <td class="value" width="60%">' . htmlspecialchars($app['parent_vmrf']) . '</td>
    </tr>
</table>';

// Declaration
$html_p2 .= '<h3>6. Declaration</h3>';
$html_p2 .= '<p style="font-size: 10pt; line-height: 1.5; color: #333;">I hereby declare that the information provided above is true to the best of my knowledge. I understand that any discrepancy found at any stage may lead to the cancellation of the scholarship application and potential disciplinary action.</p>';

// Signature
$sig_html = '';
$sig_path = $app['signature_path']; 
if (!empty($sig_path) && file_exists($sig_path)) {
    $sig_html = '<img src="'.$sig_path.'" width="150" />'; 
} else {
    $sig_html = '<br/><br/>'; 
}

$html_p2 .= '
<br/><br/><br/>

<table border="0" cellpadding="5">
    <tr>
        <td width="60%"></td> <td width="40%" align="center">
            '.$sig_html.'<br/>
            <strong style="font-size:10pt; border-top: 1px solid #000; display:block; padding-top:5px;">Signature of Applicant</strong>
            <br/>
            
        </td>
        <span style="font-size:8pt;text-align:center; color:#555; font-style:italic;">No signature needed since it\'s a computer generated application.</span>
    </tr>
</table>';

// WRITE PAGE 2
$pdf->writeHTML($html_p2, true, false, true, false, '');

// OUTPUT
$filename = 'Application_' . $app['application_no'] . '.pdf';
$pdf->Output($filename, 'D'); 
?>
