<?php
// --- 1. Start Session & Config ---
session_start();
include("../config.php");

// --- 2. Security & Login Checks ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// --- 3. Get Renewal ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid renewal ID.'];
    header("Location: view_applications.php");
    exit;
}
$renewal_id = intval($_GET['id']);

// --- 4. Fetch Renewal Data ---
$query = "
    SELECT 
        r.*, 
        a.application_no,
        a.id AS original_application_id,
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
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Renewal record not found.'];
    header("Location: view_applications.php");
    exit;
}

// --- 5. Helper Function for Displaying File Links ---
function display_file_link($label, $path) {
    if (empty($path)) {
        return "<span><em>Not Uploaded</em></span>";
    }
    
    // Handle paths stored as 'uploads/...' OR '../uploads/...'
    $final_path = $path;
    if (strpos($path, '../') !== 0) {
         $final_path = "../" . $path; // Make root-relative from admin/
    }
    
    return '<a href="' . htmlspecialchars($final_path) . '" target="_blank" class="action-button view-button" style="padding: 5px 10px; font-size: 0.85rem;">
                <i class="fa-solid fa-eye"></i> ' . $label . '
            </a>';
}

// --- 6. Set Page Variables ---
$currentPage = 'applications';
$pageTitle = "View Renewal Form";
$pageSubtitle = "For Application #" . htmlspecialchars($renewal['application_no']);

// --- 7. Include Header ---
include('header.php'); 
?>

<div class="container" style="max-width: 1000px; margin: 0 auto;">

    <div class="box">
        <div class="info-bar">
            <p><b>Student:</b> <?= htmlspecialchars($renewal['student_name']) ?></p>
            <p><b>App No:</b> <?= htmlspecialchars($renewal['application_no']) ?></p>
            <p><b>Scholarship:</b> <?= htmlspecialchars($renewal['scholarship_name']) ?></p>
        </div>

        <div class="view-grid">
            <div class="view-item">
                <label>Institution Name</label>
                <span><?= htmlspecialchars($renewal['institution_name']) ?></span>
            </div>
            <div class="view-item">
                <label>Course</label>
                <span><?= htmlspecialchars($renewal['course']) ?></span>
            </div>
            <div class="view-item">
                <label>Year of Study</label>
                <span><?= htmlspecialchars($renewal['year_of_study']) ?></span>
            </div>
            <div class="view-item">
                <label>Semester</label>
                <span><?= htmlspecialchars($renewal['semester']) ?></span>
            </div>
            <div class="view-item full-width">
                <label>University Reg. No.</label>
                <span><?= htmlspecialchars($renewal['university_reg_no']) ?></span>
            </div>
            
            <div class="view-item">
                <label>Marks (Sem 1)</label>
                <span><?= htmlspecialchars($renewal['marks_sem1']) ?></span>
            </div>
            <div class="view-item">
                <label>Marks (Sem 2)</label>
                <span><?= htmlspecialchars($renewal['marks_sem2']) ?></span>
            </div>
            <div class="view-item">
                <label>Sem 1 Marksheet</label>
                <?= display_file_link('View Document', $renewal['marks_sem1_file_path']) ?>
            </div>
            <div class="view-item">
                <label>Sem 2 Marksheet</label>
                <?= display_file_link('View Document', $renewal['marks_sem2_file_path']) ?>
            </div>

            <div class="view-item full-width">
                <label>Received any other Scholarship?</label>
                <span><?= htmlspecialchars($renewal['scholarship_receipt']) ?></span>
            </div>

            <?php if ($renewal['scholarship_receipt'] == 'Yes'): ?>
            <div class="view-item form-section" style="grid-column: 1 / -1; margin-bottom: 0;">
                <h4>Other Scholarship Details</h4>
                <div class="view-grid">
                    <div class="view-item">
                        <label>Particulars (Name, Amount)</label>
                        <span><?= nl2br(htmlspecialchars($renewal['scholarship_particulars'])) ?></span>
                    </div>
                    <div class="view-item">
                        <label>Proof Document</label>
                        <?= display_file_link('View Proof', $renewal['scholarship_file_path']) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div> <div class="form-actions" style="margin-top: 20px; justify-content: space-between; align-items: center;">
             <a href="view_applications.php" class="back-link" style="margin-top: 0;">← Back to Applications List</a>
             
             <a href="generate_renewal_pdf.php?id=<?= $renewal_id ?>" class="button download-button" target="_blank">
                <i class="fa-solid fa-download"></i> Download PDF
            </a>
        </div>
        </div>
</div>
<?php 
// This includes the footer, </html>, </body>, and global js
include('footer.php'); 
?>