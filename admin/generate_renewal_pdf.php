<?php
session_start();

// ⭐️ FIX 1: Go up one level to find config.php
include("../config.php"); 

// ⭐️ FIX 2: Go up one level to find tcpdf folder
require_once('../tcpdf/tcpdf.php'); 

// --- 1. SECURITY & DATA FETCHING ---
if (!isset($conn) || mysqli_connect_errno()) {
    die("Database connection failed.");
}

// Ensure an ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Request.");
}

$renewal_id = intval($_GET['id']);

// Fetch Renewal Data + Joined Application Data
// We fetch the student signature from the original application
// We fetch the college signature from the college table
$query = "SELECT r.*, 
            a.application_no, 
            a.name, 
            a.dob, 
            a.gender,
            a.father_name, 
            a.mother_name, 
            a.family_income, 
            a.mobile, 
            a.email, 
            a.community, 
            a.caste, 
            a.address, 
            a.signature_path AS student_sig,
            sc.name AS scholarship_name, 
            sc.scholarshipcode AS scholarship_code, 
            c.name AS college_name, 
            c.signature_path AS college_sig, 
            p.name AS original_program_name,
            a.academic_year
      FROM renewals r
      JOIN applications a ON r.application_id = a.id
      JOIN scholarships sc ON a.scholarship_id = sc.id
      LEFT JOIN colleges c ON a.institution_name = c.id
      LEFT JOIN programs p ON a.course = p.id
      WHERE r.id = '$renewal_id'
      LIMIT 1";

$result = mysqli_query($conn, $query);
$app = mysqli_fetch_assoc($result);

if (!$app) die("Renewal Application not found.");

// --- 2. HELPER FUNCTIONS ---
function format_inr($num) {
    if (empty($num)) return '0';
    return number_format((float)preg_replace('/[^0-9.]/', '', $num), 0);
}

// --- 3. PDF CLASS ---
class MYPDF extends TCPDF {
    public function Header() {
        if ($this->getPage() == 1) {
            // ⭐️ FIX 3: Updated Logo Path to go up one level
            $image_file = '../assets/logo.png';
            
            if (file_exists($image_file)) {
                $this->Image($image_file, '', 10, 150, '', 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
            }

            $this->SetFont('helvetica', 'B', 12);
            $this->SetTextColor(33, 37, 41); 
            $this->SetY(48); 
            $this->MultiCell(0, 10, 'Renewal Application Form for Educational Scholarships', 0, 'C', 0, 1, '', '', true);
            
            $this->SetDrawColor(0, 86, 179); 
            $this->SetLineWidth(0.5); 
            $this->Line(15, 60, 195, 60);
            
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.1);
        }
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
$pdf->SetTitle('Renewal_' . $app['application_no']);

$pdf->SetMargins(15, 68, 15); 
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('helvetica', '', 11);

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
    table { width: 100%; border-collapse: collapse; padding: 6px; }
    th { background-color: #f8f9fa; color: #495057; font-weight: bold; border: 1px solid #dee2e6; padding: 8px; font-size: 10pt; }
    td { border: 1px solid #dee2e6; color: #212529; padding: 8px; font-size: 10pt; }
    .label { font-weight: bold; background-color: #fdfdfd; }
    .value { color: #000; }
    .summary-table td { border: 1px solid #dae0e5; padding: 8px; }
    .summary-label { font-size: 9pt; color: #666; display: block; }
    .summary-val { font-size: 11pt; font-weight: bold; color: #333; }
    .declaration { font-size: 10pt; text-align: justify; line-height: 1.4; }
    .office-use { background-color: #fcfcfc; border: 1px dashed #999; padding: 10px; font-size: 10pt; }
    .info-title { font-weight: bold; font-size: 12pt; text-align: center; color: #000; border: 1px solid #000; padding: 5px; }
    .section-head { font-weight: bold; font-size: 12pt; margin-top: 15px; margin-bottom: 5px; }
    .tbl-header { font-weight: bold; text-align: center; background-color: #ffffff; }
</style>
';

// --- 5. PAGE 1 CONTENT ---
$scholarship_code = !empty($app['scholarship_code']) ? $app['scholarship_code'] : 'N/A';

$html_p1 = $css;
$html_p1 .= '
<table cellpadding="8" cellspacing="0" class="summary-table" style="background-color: #f0f8ff; border: 1px solid #b8daff;">
    <tr>
        <td width="50%">
            <span class="summary-label">Scholarship Scheme -</span>
            <span class="summary-val">' . htmlspecialchars($app['scholarship_name']) . '</span>
        </td>
        <td width="50%">
            <span class="summary-label">Application Number -</span>
            <span class="summary-val">' . htmlspecialchars($app['application_no']) . ' (Renewal)</span>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <span class="summary-label">Scholarship Code -</span>
            <span class="summary-val">' . htmlspecialchars($scholarship_code) . '</span>
        </td>
        <td width="50%">
            <span class="summary-label">Academic Year -</span>
            <span class="summary-val">' . htmlspecialchars($app['academic_year'] ?? 'N/A') . '</span>
        </td>
    </tr>
</table>
<br/>';

// Personal Details
$dob = (!empty($app['dob'])) ? date('d-m-Y', strtotime($app['dob'])) : 'N/A';
$sem_roman = ['I','II','III','IV','V','VI','VII','VIII','IX','X'];
$current_sem_display = isset($sem_roman[$app['semester']-1]) ? $sem_roman[$app['semester']-1] : $app['semester'];

// Determine Program Name
$prog_display = !empty($app['renewal_course_name']) ? $app['renewal_course_name'] : ($app['original_program_name'] ?? '-');
// Note: If renewal course is actually an ID in DB, we rely on the JOIN p.name. 
// Assuming here $app['course'] in renewal table might be the ID same as apps table.
// If renewal table stores ID in 'course' column, $app['course'] is ID. We joined programs p ON r.course = p.id (if IDs match).
// If `p.name` is null, we fallback to original.
if (!empty($app['original_program_name'])) {
    $prog_display = $app['original_program_name'];
}

$html_p1 .= '<h3>1. Personal & Institution Details</h3>';
$html_p1 .= '
<table cellpadding="6">
    <tr>
        <td class="label" width="40%">Full Name</td>
        <td class="value" width="60%">' . htmlspecialchars($app['name']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Institution Name</td>
        <td class="value" width="60%">' . htmlspecialchars($app['college_name'] ?? '-') . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Program / Course</td>
        <td class="value" width="60%">' . htmlspecialchars($prog_display) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Current Year & Sem</td>
        <td class="value" width="60%">Year ' . htmlspecialchars($app['year_of_study']) . ' (Sem ' . $current_sem_display . ')</td>
    </tr>
    <tr>
        <td class="label" width="40%">University Reg. No</td>
        <td class="value" width="60%">' . htmlspecialchars($app['university_reg_no'] ?? '-') . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Gender</td>
        <td class="value" width="60%">' . htmlspecialchars($app['gender']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Date of Birth</td>
        <td class="value" width="60%">' . $dob . '</td>
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
        <td class="label" width="40%">Community</td>
        <td class="value" width="60%">' . htmlspecialchars($app['community']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Caste</td>
        <td class="value" width="60%">' . htmlspecialchars($app['caste']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Annual Income</td>
        <td class="value" width="60%">Rs. ' . format_inr($app['family_income']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Mobile Number</td>
        <td class="value" width="60%">' . htmlspecialchars($app['mobile']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Email Address</td>
        <td class="value" width="60%">' . htmlspecialchars($app['email']) . '</td>
    </tr>
</table>
<br/>';

$html_p1 .= '<h3>2. Permanent Address</h3>';
$html_p1 .= '<table cellpadding="6"><tr><td>' . nl2br(htmlspecialchars($app['address'])) . '</td></tr></table>';

$pdf->writeHTML($html_p1, true, false, true, false, '');


// --- 6. PAGE 2 CONTENT (Renewal Specifics) ---
$pdf->SetMargins(15, 15, 15); 
$pdf->SetPrintHeader(false); 
$pdf->AddPage();

$html_p2 = $css; 

// Academic Performance (Renewal Specific)
$html_p2 .= '<h3>3. Academic Performance (Previous Year)</h3>';
$html_p2 .= '
<table cellpadding="6">
    <tr>
        <td class="label" width="40%">Previous Year of Study</td>
        <td class="value" width="60%">Year ' . htmlspecialchars($app['previous_year_of_study']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Attendance Percentage</td>
        <td class="value" width="60%">' . htmlspecialchars($app['previous_attendance_percentage']) . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Marks / CGPA (Prev Sem)</td>
        <td class="value" width="60%">' . htmlspecialchars($app['marks_sem1']) . '</td>
    </tr>';

if (!empty($app['marks_sem2'])) {
    $html_p2 .= '
    <tr>
        <td class="label" width="40%">Marks / SGPA (Curr Sem)</td>
        <td class="value" width="60%">' . htmlspecialchars($app['marks_sem2']) . '</td>
    </tr>';
}
$html_p2 .= '</table><br/>';

// Other Scholarship
$html_p2 .= '<h3>4. Other Scholarship Details</h3>';
$html_p2 .= '
<table cellpadding="6">
    <tr>
        <td class="label" width="40%">Receipt of other Scholarship?</td>
        <td class="value" width="60%">' . htmlspecialchars($app['scholarship_receipt']) . '</td>
    </tr>';

if (strtolower($app['scholarship_receipt']) == 'yes') {
    $html_p2 .= '
    <tr>
        <td class="label" width="40%">Details</td>
        <td class="value" width="60%">' . nl2br(htmlspecialchars($app['scholarship_particulars'])) . '</td>
    </tr>';
}
$html_p2 .= '</table><br/>';

// Enclosures
$html_p2 .= '
<div style="font-size:10pt;">
    <strong>Enclose:</strong>
    <ol>
        <li>Copy of Previous Semester Mark Sheets</li>
        <li>Attendance Certificate from Head of Department</li>
        <li>Income Certificate (if applicable)</li>
        <li>Receipt/Proof of other scholarships (if any)</li>
    </ol>
</div>';

// Write Page 2
$pdf->writeHTML($html_p2, true, false, true, false, '');


// --- 7. PAGE 3 (DECLARATION & SIGNATURES) ---
$pdf->AddPage(); 
$html_p3 = $css;

// Declaration
$html_p3 .= '<h3>5. Declaration</h3>';
$html_p3 .= '<p class="declaration">
We declare that, we are aware of the various Scholarships offered by Vinayaka Mission\'s Research Foundation (Deemed to be University), Salem and applicability. We understand fully that, our application for renewal of scholarship does not guarantee the scholarship. We certify that, the above information is true and correct to the best of our knowledge. At any point of time, if it is found that, the information given is not true, the scholarship given could be withdrawn.<br/><br/>
We agree that, the university has every right to amend the norms for the award of Educational Scholarship / Fee Concession from time to time.<br/><br/>
We also agree that, if the student discontinue the course in the middle, the above scholarship / concession amount availed by the student will be refunded to the institution / university to get the No Due certificate from the institution / university.
</p>';

// Student Signature Logic (From Original App)
// ⭐️ FIX 4: Correct Path for Signatures (assuming they are stored as 'uploads/...')
// Since we are in 'admin/', we need to look in '../uploads/...'
$student_sig_html = '';
$sig_path = $app['student_sig'];
if (!empty($sig_path) && file_exists("../" . $sig_path)) {
    $student_sig_html = '<img src="../' . $sig_path . '" width="120" />'; 
} else {
    $student_sig_html = '<br/><br/><br/>'; 
}

$html_p3 .= '
<table border="0" cellpadding="5">
    <tr>
        <td width="60%">Date: '.date('d-m-Y').'</td> 
        <td width="40%" align="center">
            '.$student_sig_html.'<br/>
            <strong style="font-size:10pt; border-top: 1px solid #000; padding-top:5px;">Signature of the Student</strong>
        </td>
    </tr>
</table>
<br/><br/>';

// Office Use Section
$html_p3 .= '
<div class="office-use">
    <strong>(For office use)</strong><br/>
    This is to certify that the above particulars furnished by the student regarding renewal are correct and genuine. The student has maintained the required attendance and academic performance. The request is recommended and forwarded to the Registrar / Scholarship Committee for further process.
</div>';

// Head of Institution Signature Logic
$hoi_sig_html = '';
$college_sig_path = $app['college_sig']; 
if (!empty($college_sig_path) && file_exists("../" . $college_sig_path)) {
    $hoi_sig_html = '<img src="../' . $college_sig_path . '" width="140" />';
} else {
    $hoi_sig_html = '<br/><br/><br/>';
}

$html_p3 .= '
<br/><br/>
<table border="0" cellpadding="5">
    <tr>
        <td width="50%"></td> 
        <td width="50%" align="right">
            '.$hoi_sig_html.'<br/>
            <strong style="font-size:10pt; border-top: 1px solid #000; padding-top:5px;">Signature of Head of the Institution</strong>
        </td>
    </tr>
</table>';

// Write Page 3
$pdf->writeHTML($html_p3, true, false, true, false, '');


// --- 8. PAGE 4 (INSTRUCTIONS & CATEGORIES) ---
$pdf->AddPage();
$html_p4 = $css;

$html_p4 .= '<div class="info-title">RENEWAL GUIDELINES</div><br/>';
$html_p4 .= '
<p class="declaration">
Renewal of scholarship is subject to the student maintaining satisfactory academic performance and attendance as per university norms. Students must apply for renewal every year within the stipulated timeline.
</p>';

$html_p4 .= '<div class="section-head">Eligibility for Renewal :</div>';
$html_p4 .= '
<ul>
    <li>Minimum 75% attendance in the previous academic year.</li>
    <li>No disciplinary action pending against the student.</li>
    <li>Successful completion of previous semester examinations without any arrears (as per specific scheme norms).</li>
    <li>Submission of renewal application before the deadline (31st July).</li>
</ul>';

$html_p4 .= '<div class="section-head">Important Notes :</div>';
$html_p4 .= '
<ul>
    <li>The decision of the Scholarship Committee regarding the quantum of renewal amount is final.</li>
    <li>If a student discontinues studies, the scholarship amount availed must be refunded.</li>
    <li>Break of study or detention will lead to cancellation of scholarship eligibility.</li>
</ul>';

$pdf->writeHTML($html_p4, true, false, true, false, '');

// OUTPUT
$filename = 'Renewal_App_' . $app['application_no'] . '.pdf';
$pdf->Output($filename, 'D'); 
?>