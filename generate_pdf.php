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

// Fetch Application Data
$query = "SELECT a.*, 
            sc.name AS scholarship_name, 
            sc.scholarshipcode AS scholarship_code, 
            c.name AS college_name, 
            c.signature_path AS college_sig, 
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

// ⭐️ NEW CHECKBOX FUNCTION: Uses DejaVu Sans for proper square rendering
function getCheckbox($label, $isChecked) {
    // UTF-8 Hex codes for Ballot Box characters
    // Checked: ☑ (&#9745;)  |  Unchecked: ☐ (&#9744;)
    // We strictly use dejavusans font just for the symbol to ensure it looks like a box
    
    if ($isChecked) {
        $box = '<span style="font-family:dejavusans; font-size:12pt; color: #000;">&#9745;</span>'; // Checked Box
    } else {
        $box = '<span style="font-family:dejavusans; font-size:12pt; color: #333;">&#9744;</span>'; // Empty Box
    }
    
    // Return HTML with label (label uses standard font)
    return '<span style="white-space:nowrap;">' . $box . ' <span style="font-family:helvetica; font-size:10pt;">' . $label . '</span></span>';
}

// --- 3. PDF CLASS ---
class MYPDF extends TCPDF {
    public function Header() {
        if ($this->getPage() == 1) {
            $image_file = 'assets/logo.png';
            if (file_exists($image_file)) {
                $this->Image($image_file, '', 10, 150, '', 'PNG', '', 'T', false, 300, 'C', false, false, 0, false, false, false);
            }

            $this->SetFont('helvetica', 'B', 12);
            $this->SetTextColor(33, 37, 41); 
            $this->SetY(48); 
            $this->MultiCell(0, 10, 'Application Form for Educational Scholarships / Fee Concession under Various Schemes', 0, 'C', 0, 1, '', '', true);
            
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
$pdf->SetTitle('Application_' . $app['application_no']);

// Margins
$pdf->SetMargins(15, 68, 15); 
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('helvetica', '', 10);

// Start Page 1
$pdf->AddPage();

// Common CSS
$css = '
<style>
    h3 {
        color: #0056b3;
        font-size: 13pt;
        font-weight: bold;
        border-bottom: 2px solid #eeeeee;
        padding-bottom: 5px;
        margin-top: 15px; 
        margin-bottom: 8px;
    }
    table { width: 100%; border-collapse: collapse; padding: 5px; }
    th { background-color: #f8f9fa; color: #495057; font-weight: bold; border: 1px solid #dee2e6; padding: 6px; font-size: 9pt; }
    td { border: 1px solid #dee2e6; color: #212529; padding: 6px; font-size: 9pt; vertical-align: middle; }
    .label { font-weight: bold; background-color: #fdfdfd; font-size: 9pt; }
    .value { color: #000; font-size: 9pt; }
    
    /* Summary Box */
    .summary-table td { border: 1px solid #dae0e5; padding: 8px; }
    .summary-label { font-size: 9pt; color: #666; display: block; }
    .summary-val { font-size: 10pt; font-weight: bold; color: #333; }
    
    /* Checkbox Spacing */
    .chk-row span { margin-right: 15px; }
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
            <span class="summary-val">' . htmlspecialchars($app['application_no']) . '</span>
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

// --- CHECKBOX LOGIC PREPARATION ---

// 1. Gender
$g = strtolower($app['gender']);
$gender_html = '<span class="chk-row">' . 
               getCheckbox('Male', $g=='male') . '&nbsp;&nbsp;' .
               getCheckbox('Female', $g=='female') . '&nbsp;&nbsp;' .
               getCheckbox('Other', ($g!='male' && $g!='female')) . 
               '</span>';

// 2. Community
$c = strtoupper(trim($app['community']));
// Normalize variations
if(strpos($c, 'MBC') !== false) $c = 'MBC/DNC';
if(strpos($c, 'DNC') !== false) $c = 'MBC/DNC';

$comm_html = '<span class="chk-row">' .
             getCheckbox('OC', $c=='OC') . '&nbsp;' .
             getCheckbox('BC', $c=='BC') . '&nbsp;' .
             getCheckbox('OBC', $c=='OBC') . '&nbsp;' .
             getCheckbox('MBC/DNC', $c=='MBC/DNC') . '&nbsp;' .
             getCheckbox('SC', $c=='SC') . '&nbsp;' .
             getCheckbox('ST', $c=='ST') .
             '</span>';

$dob = (!empty($app['dob'])) ? date('d-m-Y', strtotime($app['dob'])) : 'N/A';

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
        <td class="value" width="60%">' . htmlspecialchars($app['program_name'] ?? '-') . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Current Year & Sem</td>
        <td class="value" width="60%">Year ' . htmlspecialchars($app['year_of_study']) . ' (Sem ' . htmlspecialchars($app['semester']) . ')</td>
    </tr>
    <tr>
        <td class="label" width="40%">Gender</td>
        <td class="value" width="60%">' . $gender_html . '</td>
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
        <td class="value" width="60%">' . $comm_html . '</td>
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
    <tr>
        <td class="label" width="40%">Permanent Address</td>
        <td class="value" width="60%">' . nl2br(htmlspecialchars($app['address'])) . '</td>
    </tr>
</table>
<br/>';

$pdf->writeHTML($html_p1, true, false, true, false, '');


// --- 6. PAGE 2 CONTENT ---
$pdf->SetMargins(15, 15, 15); 
$pdf->SetPrintHeader(false); 
$pdf->AddPage();

$html_p2 = $css; 

// --- 2. EDUCATION QUALIFICATION (Always Shown) ---
$html_p2 .= '<h3>2. Education Qualification</h3>';
$html_p2 .= '
<table cellpadding="5">
    <thead>
        <tr>
            <th width="25%">Name of the Examination</th>
            <th width="20%">Year of Passing & Reg. No</th>
            <th width="30%">Board/University/Institution</th>
            <th width="10%">Class/ Grade</th>
            <th width="15%" align="right">Marks (%)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td width="25%">' . htmlspecialchars($app['exam_name'] ?? '') . '</td>
            <td width="20%">' . htmlspecialchars($app['exam_year_reg'] ?? '') . '</td>
            <td width="30%">' . htmlspecialchars($app['exam_board'] ?? '') . '</td>
            <td width="10%">' . htmlspecialchars($app['exam_class'] ?? '') . '</td>
            <td width="15%" align="right">' . htmlspecialchars($app['exam_marks'] ?? '') . '</td>
        </tr>
    </tbody>
</table>
<br/>';

// --- 3. LATERAL ENTRY (Always Shown) ---
$html_p2 .= '<h3>3. Fill the Column (for PG / UG Lateral Entry Students)</h3>';
$html_p2 .= '
<table cellpadding="6">
    <tr>
        <td width="40%" class="label">Name of the Examination Passed Degree / Diploma</td>
        <td width="60%" class="value">' . htmlspecialchars($app['lateral_exam_name'] ?? '') . '</td>
    </tr>
    <tr>
        <td width="40%" class="label">Month & Year of Passing with Reg. No</td>
        <td width="60%" class="value">' . htmlspecialchars($app['lateral_exam_year_reg'] ?? '') . '</td>
    </tr>
    <tr>
        <td width="40%" class="label">Percentage Obtained</td>
        <td width="60%" class="value">' . htmlspecialchars($app['lateral_percentage'] ?? '') . '</td>
    </tr>
</table>
<br/>';

// --- 4. OTHER CLAIMS (Checkboxes) ---

// Sports Logic
$s = strtolower($app['sports_level'] ?? '');
// We check if the specific word exists in the DB string
$sports_html = '<span class="chk-row">' .
               getCheckbox('District', strpos($s, 'district') !== false) . '&nbsp;&nbsp;' .
               getCheckbox('State', strpos($s, 'state') !== false) . '&nbsp;&nbsp;' .
               getCheckbox('National', strpos($s, 'national') !== false) . '&nbsp;&nbsp;' .
               getCheckbox('International', strpos($s, 'international') !== false) .
               '</span>';

// Ex-Servicemen (Yes/No)
$ex = strtolower($app['ex_servicemen'] ?? 'no');
$ex_html = '<span class="chk-row">' . getCheckbox('Yes', $ex=='yes') . '&nbsp;&nbsp;&nbsp;' . getCheckbox('No', $ex!='yes') . '</span>';

// Disabled (Yes/No)
$dis = strtolower($app['disabled'] ?? 'no');
$dis_html = '<span class="chk-row">' . getCheckbox('Yes', $dis=='yes') . '&nbsp;&nbsp;&nbsp;' . getCheckbox('No', $dis!='yes') . '</span>';

// Parent VMRF (Yes/No)
$par = strtolower($app['parent_vmrf'] ?? 'no');
$par_html = '<span class="chk-row">' . getCheckbox('Yes', $par=='yes') . '&nbsp;&nbsp;&nbsp;' . getCheckbox('No', $par!='yes') . '</span>';


$html_p2 .= '<h3>4. Other Claims & Details</h3>';
$html_p2 .= '
<table cellpadding="6">
    <tr>
        <td class="label" width="40%">Sports Category</td>
        <td class="value" width="60%">' . $sports_html . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Ex-Servicemen Quota</td>
        <td class="value" width="60%">' . $ex_html . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Differently Abled</td>
        <td class="value" width="60%">' . $dis_html . '</td>
    </tr>
    <tr>
        <td class="label" width="40%">Parent working in VMRF-DU?</td>
        <td class="value" width="60%">' . $par_html . '</td>
    </tr>';

if ($par == 'yes' && !empty($app['parent_vmrf_details'])) {
    $html_p2 .= '
    <tr>
        <td class="label" width="40%">Parent Details</td>
        <td class="value" width="60%">' . nl2br(htmlspecialchars($app['parent_vmrf_details'])) . '</td>
    </tr>';
}

$html_p2 .= '</table><br/>';

// Enclosures
$html_p2 .= '
<div style="font-size:10pt;">
    <strong>Enclose:</strong>
    <ol>
        <li>Copy of SSLC Mark Sheet, H.Sc Mark Sheet (11th & 12th), UG Degree Certificate, PG Degree Certificate, Transfer Certificate, Conduct Certificate, Community Certificate, Aadhaar Card</li>
        <li>Income Certificate, Attendance Certificate, Sports Certificate, Disability Certificate, Ex-Servicemen Certificate in Original</li>
    </ol>
</div>';

// Write Page 2
$pdf->writeHTML($html_p2, true, false, true, false, '');


// --- 7. PAGE 3 (DECLARATION & SIGNATURES) ---
$pdf->AddPage(); // Force Declaration to Page 3
$html_p3 = $css;

// Declaration
$html_p3 .= '<h3>5. Declaration</h3>';
$html_p3 .= '<p class="declaration">
We declare that, we are aware of the various Scholarships offered by Vinayaka Mission\'s Research Foundation (Deemed to be University), Salem and applicability. We understand fully that, our application for scholarship does not guarantee for the scholarship. We certify that, the above information is true and correct to the best of our knowledge. At any point of time, if it is found that, the information given is not true, the scholarship given could be withdrawn. We understand fully that, the decision of the Vinayaka Mission\'s Research Foundation (Deemed to be University) is final in all respects on awarding of scholarship and its withdrawal, in case of submission of wrong information.<br/><br/>
We agree that, the university has every right to amend the norms for the award of Educational Scholarship / Fee Concession from time to time.<br/><br/>
We also agree that, if the student discontinue the course in the middle, the above scholarship / concession amount availed by the student will be refunded to the institution / university to get the No Due certificate from the institution / university.
</p>';

// Student Signature Logic
$student_sig_html = '';
if (!empty($app['signature_path']) && file_exists($app['signature_path'])) {
    $student_sig_html = '<img src="'.$app['signature_path'].'" width="120" />'; 
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
    This is to certify that the above particulars furnished by the student and parent are correct and genuine. The request for Educational Scholarship / Fee Concession is recommended and forwarded to the Registrar / Scholarship Committee of Vinayaka Mission\'s Research Foundation (Deemed to be University) for further process.
</div>';

// Head of Institution Signature Logic
$hoi_sig_html = '';
$college_sig_path = $app['college_sig']; 
if (!empty($college_sig_path) && file_exists($college_sig_path)) {
    $hoi_sig_html = '<img src="'.$college_sig_path.'" width="140" />';
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


// --- 8. PAGE 4 (INSTRUCTIONS, CATEGORIES & SELECTION) ---
$pdf->AddPage();
$html_p4 = $css;

// Instruction Header
$html_p4 .= '<div class="info-title">INSTRUCTIONS TO THE CANDIDATES</div><br/>';
$html_p4 .= '
<p class="declaration">
Vinayaka Mission\'s Research Foundation (Deemed to be University) offers Institutional Scholarships / Fee Concessions / Freeships and Fee waiver to recognize and reward meritorious students. The Scholarships / Concessions / Freeships are awarded to students as per the categories listed. These Scholarships / Concessions / Freeships are given every year to help young aspiring students to pursue higher education and advanced studies.
This is to aim with the Mission and vision of VMRF-DU to provide opportunities to deserving candidates to undertake advanced studies and research. The quantum of scholarship and the number of scholarships are subject to change from time to time and according to the programmes.
</p>';

$html_p4 .= '<div class="section-head">Scholarship Categories :</div><br/>';

// Two-Column Table Layout
$html_p4 .= '
<table border="0" cellspacing="0" cellpadding="2">
    <tr>
        <td width="49%" valign="top">
            <table border="1" cellspacing="0" cellpadding="4">
                <thead>
                    <tr>
                        <th colspan="2" align="left" style="font-weight:bold;">I – Founders Scholarships</th>
                    </tr>
                    <tr>
                        <th width="20%" class="tbl-header">Code No</th>
                        <th width="80%" class="tbl-header">Category</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td align="center">1.1</td><td>State Board ( Top 100 Rank Holders )</td></tr>
                    <tr><td align="center">1.2</td><td>CBSE ( Top 100 Rank Holders )</td></tr>
                    <tr><td align="center">1.3</td><td>Tamil Nadu District Toppers ( Top 2 Ranks )</td></tr>
                    <tr><td align="center">1.4</td><td>JEE - Main ( Top 5000 Rank Holders )</td></tr>
                    <tr><td align="center">1.5</td><td>JEE - Advanced ( Top 1000 Rank Holders )</td></tr>
                </tbody>
            </table>
        </td>
        
        <td width="2%"></td>
        
        <td width="49%" valign="top">
            <table border="1" cellspacing="0" cellpadding="4">
                <thead>
                    <tr>
                        <th width="20%" class="tbl-header">Code No</th>
                        <th width="80%" class="tbl-header">Category</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td align="center">2.1</td><td>Academic Merit</td></tr>
                    <tr><td align="center">2.2</td><td>Ex-Servicemen Scholarship</td></tr>
                    <tr><td align="center">2.3</td><td>Differently-Abled</td></tr>
                    <tr><td align="center">2.4</td><td>Sports Category</td></tr>
                    <tr><td align="center">2.5</td><td>Economically & Socially Backward</td></tr>
                    <tr><td align="center">2.6</td><td>First Graduate</td></tr>
                    <tr><td align="center">2.7</td><td>Staff’s Children of VMRF-DU</td></tr>
                    <tr><td align="center">2.8</td><td>Alumni of VMRF-DU</td></tr>
                    <tr><td align="center">2.9</td><td>Single Parent</td></tr>
                    <tr><td align="center">2.10</td><td>Both parents are not alive</td></tr>
                    <tr><td align="center">2.11</td><td>Student from Tamil Medium</td></tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>
<br/>';

// Procedure Text - Item 1 Only
$html_p4 .= '
<div class="section-head" style="text-decoration: underline;">Scholarships Procedure :</div>
<ol>
    <li><strong>Selection</strong>
        <ul>
            <br>    
            <li>Every year all eligible students have to apply for Fresh and Renewal of the scholarship in the prescribed form available in the University / College office / Website.</li>
            <li>Application forms for request of scholarship for renewal (Second Year to Final Year) will be received from May to 31st July of every year. Fresh application (First Year) will be received till last date of admission.</li>
            <li>A Student can apply and avail ONLY ONE scholarship scheme.</li>
            <li>The eligible students will be informed through the Head of the Institution.</li>
            <li>The above scholarships are for the regular Full time programme students only.</li>
        </ul>
    </li>
</ol>';

// Write Page 4
$pdf->writeHTML($html_p4, true, false, true, false, '');


// --- 9. PAGE 5 (GUIDELINES & NOT ELIGIBLE) ---
$pdf->AddPage(); // Force Page Break
$html_p5 = $css;

// Continue Procedure List starting from 2
$html_p5 .= '
<ol start="2">
    <li><strong>Guidelines of Second year to final year Scholarships :</strong>
        <p>All the above scholarships are renewed every year subject to academic performance of the student in the previous academic year of VMRF-DU Examinations with above 9.0 SGPA (without any break of study). It will also be subject to discipline and maintenance of attendance at least 75% by the Student. In such case, student would get the same amount of scholarship which was provided in the previous year.</p>
        <p>The Second year to final year students who are not availed any scholarship category during admission time and if they wish to apply based on the VMRF-DU Examinations with above 9.0 SGPA and above are eligible to get only 20% scholarship in Tuition fees for that year.</p>
    </li>
    <li><strong>Not Eligible for Scholarship</strong>
        <p>Students for the following categories are not eligible for any scholarship / any year during the period of study.</p>
        <ul>
            <li>Break of Study</li>
            <li>Failure in the VMRF-DU examination</li>
            <li>Less than 75% of attendance</li>
            <li>Disciplinary action / Enquiry / Suspension</li>
            <li>Failure to apply for scholarship within the stipulated date will not be eligible for the scholarship.</li>
        </ul>
    </li>
</ol>
<p><strong>Note :</strong></p>
<ul>
    <li>Students applying for the award of scholarship shall submit the proof of annual income certified by the appropriate Government authority / from the employer of parents.</li>
    <li>In all matters connected with or arising from the above, the decision of the Vice-Chancellor of VMRF-
    DU shall be final.</li>
</ul>';

// Write Page 5
$pdf->writeHTML($html_p5, true, false, true, false, '');

// OUTPUT
$filename = 'Application_' . $app['application_no'] . '.pdf';
$pdf->Output($filename, 'D'); 
?>