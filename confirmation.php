<?php
session_start();
// NOTE: Ensure config.php defines $conn and that $conn is a valid mysqli connection object.
include("config.php"); 

if (!isset($conn) || mysqli_connect_errno()) {
    die("Database connection failed: Could not connect.");
}


// --- NEW COMBINED AUTHENTICATION LOGIC ---

$app_id = 0; // Initialize $app_id

if (isset($_GET['id'])) {
    // --- Use Case 1: USER IS AN ADMIN (or someone clicking a specific link) ---
    // They are providing an ID in the URL. We MUST verify they are an admin.
    if (!isset($_SESSION['admin_id'])) {
        die("Access Denied: You must be an admin to view applications by ID.");
    }
    $app_id = intval($_GET['id']);

} elseif (isset($_SESSION['application_id'])) {
    // --- Use Case 2: USER IS A STUDENT (who just submitted) ---
    // No ID in the URL, so they MUST have an application ID in their session.
    $app_id = intval($_SESSION['application_id']);

} else {
    // --- Use Case 3: NOBODY IS LOGGED IN or NO ID PROVIDED ---
    // No admin, no student. Access denied.
    header("Location: index.php"); // Redirect to student login/home
    exit;
}

// We must have a valid $app_id to proceed
if (!$app_id) {
    die("Application not specified or access denied.");
}

// --- DATABASE FETCH LOGIC ---
$result = mysqli_query(
    $conn,
    "SELECT a.*, sc.name AS scholarship_name, 
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
      LIMIT 1"
);

$app = mysqli_fetch_assoc($result);
if (!$app) die("Application not found.");

// --- HELPER FUNCTIONS ---
function format_inr($num) {
    if (empty($num)) return '₹ N/A';
    $num = round(floatval(preg_replace('/[^0-9.]/', '', $num)));
    $s = substr($num, -3);
    $n = substr($num, 0, -3);
    if ($n) {
        $n = preg_replace("/(\d{2}(?=\d))/", "$1,", $n);
    }
    return '₹' . $n . (empty($n) ? '' : ',') . $s;
}

function get_file_button($file_type, $app_id, $conn) {
    $safe_app_id = intval($app_id);
    $safe_file_type = mysqli_real_escape_string($conn, $file_type);

    $query = "SELECT file_path FROM application_files WHERE application_id = $safe_app_id AND file_type = '$safe_file_type' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $file_path = $row['file_path'];
        // Fix path for student-side viewing (it's in the root, not /admin/)
        if (strpos($file_path, '../') === 0) {
             $file_path = substr($file_path, 3); // Remove ../
        }

        return '
            <a href="' . htmlspecialchars($file_path) . '" 
               target="_blank" 
               class="view-file-btn" 
               title="View Attached Document">
               <i class="fas fa-file-lines"></i>
            </a>';
    }
    return '';
}

// --- DATA FORMATTING AND PREPARATION ---
$full_name = htmlspecialchars($app['name']);
$first_name = explode(' ', $full_name)[0];

$dob_formatted = 'N/A';
if (!empty($app['dob'])) {
    try {
        $date = new DateTime($app['dob']);
        $dob_formatted = $date->format('d-m-Y');
    } catch (Exception $e) {
        $dob_formatted = htmlspecialchars($app['dob']); 
    }
}

$family_income_formatted = format_inr($app['family_income']);

// --- HTML START ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Confirmed! 🎉</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="stylesheet" href="confirmation_style.css">

    <style>
        /* General Grid Improvement for Academic Section */
        .card-section .grid {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .card-section .grid .full-width {
            grid-column: 1 / -1; /* Ensures this item takes the full row */
        }

        /* File Button Styling */
        .view-file-btn {
            color: #17a2b8;
            font-size: 1.1em;
            margin-left: 10px;
            cursor: pointer;
            transition: color 0.2s, background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 8px;
        }
        .view-file-btn:hover {
            color: white;
            background: #17a2b8;
        }

        /* Upscale Academic Score Card */
        .academic-score-wrapper {
            display: flex;
            flex-direction: column;
            gap: 15px; /* Increased gap */
            padding: 20px 25px; /* Increased padding */
            background: #ffffff; /* White background for separation */
            border: 1px solid #e0e0e0; /* Subtle border */
            border-radius: 15px; /* More rounded corners */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 2px 5px rgba(0, 0, 0, 0.03); 
            transition: all 0.3s ease-in-out;
        }
        .academic-score-wrapper:hover {
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08), 0 2px 5px rgba(0, 0, 0, 0.05); 
            transform: translateY(-2px); 
        }
        .score-header {
            font-weight: 700;
            color: #343a40;
            font-size: 1.2rem; 
            border-bottom: 3px solid #f0f0f0; 
            padding-bottom: 8px;
            margin-bottom: 0;
        }
        .score-body {
            display: flex;
            justify-content: space-between;
            align-items: flex-end; 
        }
        .score-details {
             flex-grow: 1;
        }
        .score-details span {
            display: block;
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .score-details strong {
            color: #343a40;
            font-size: 1rem;
            font-weight: 600;
        }
        .score-result {
            align-self: center;
            text-align: right;
            min-width: 120px;
        }
        .score-result strong {
            font-size: 2.5rem; 
            color: #007bff; 
            line-height: 1;
            display: block;
        }
        .score-result span {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        /* --- FIX FOR ADDRESS WRAPPING --- */
        .grid-item.full-width .value {
            white-space: normal; /* Allows text to wrap */
            word-wrap: break-word; /* Breaks long words if necessary */
            line-height: 1.6; /* Improves readability of paragraphs */
        }
        
        /* --- ⭐️ NEW STYLES FOR LOGOUT BUTTON ⭐️ --- */
        .center {
            text-align: center;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px; /* Adds space between buttons */
            flex-wrap: wrap; /* Allows buttons to wrap on small screens */
        }
        
        /* Style for the new logout button */
        .download-btn.logout {
            background: #dc3545; /* A red color for logout */
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }
        .download-btn.logout:hover {
            background: #c82333; /* Darker red on hover */
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.4);
        }

        /* Signature Styling */
.declaration-section {
    margin-top: 20px;
    border-top: 2px dashed #e0e0e0;
    padding-top: 20px;
}
.declaration-text {
    font-size: 0.9em;
    color: #555;
    margin-bottom: 15px;
    font-style: italic;
}
.signature-box {
    text-align: right; /* Aligns signature to the right */
    margin-top: 10px;
}
.signature-img {
    max-width: 200px;
    max-height: 80px;
    border-bottom: 1px solid #333;
    padding-bottom: 5px;
    display: inline-block;
}
.signature-label {
    font-weight: bold;
    font-size: 0.9em;
    color: #333;
    margin-top: 5px;
}
        /* --- END OF NEW STYLES --- */
    </style>

</head>
<body>

<div class="container">
    <div class="header-wrapper">
        <div class="header">
            <h1 id="firework-trigger">Congrats, <?= $first_name ?>! You're Gold.</h1>
            <p>Your details are locked in. Check out the summary below and grab your PDF copy.</p>
        </div>
    </div>

    <div class="card" id="main-card">
        <div class="spotlight"></div>
        
        <div class="featured-card">
            <div class="feature-item">
                <div class="label">Scholarship Name</div>
                <div class="value"><?= htmlspecialchars($app['scholarship_name']) ?></div>
            </div>
            <div class="feature-item">
                <div class="label">Application ID</div>
                <div class="value">#<?= htmlspecialchars($app['application_no']) ?></div>
            </div>
            <div class="feature-item">
                <div class="label">Academic Year</div>
                <div class="value"><?= htmlspecialchars($app['academic_year'] ?? 'N/A') ?></div>
            </div>
        </div>
        
        <div class="card-section">
            <h2><i class="fas fa-user-check"></i> Personal & Contact Details</h2>
            <div class="grid">
                <div class="grid-item"><div class="label">Applicant Name</div><div class="value"><?= $full_name ?></div></div>
                <div class="grid-item"><div class="label">Institution</div><div class="value"><?= htmlspecialchars($app['college_name'] ?? 'N/A') ?></div></div>
                <div class="grid-item"><div class="label">Program</div><div class="value"><?= htmlspecialchars($app['program_name'] ?? 'N/A') ?></div></div>
                <div class="grid-item"><div class="label">Current Year/Semester</div><div class="value">Year <?= htmlspecialchars($app['year_of_study'] ?? 'N/A') ?>, Sem <?= htmlspecialchars($app['semester'] ?? 'N/A') ?></div></div>
                <div class="grid-item"><div class="label">Email / Mobile</div><div class="value"><?= htmlspecialchars($app['email']) ?> / <?= htmlspecialchars($app['mobile']) ?></div></div>
                <div class="grid-item"><div class="label">DOB / Gender</div><div class="value"><?= $dob_formatted ?> / <?= htmlspecialchars($app['gender']) ?></div></div>
                <div class="grid-item"><div class="label">Family Income (Annual)</div><div class="value"><?= $family_income_formatted ?></div></div>
                <div class="grid-item"><div class="label">Community (Caste)</div><div class="value"><?= htmlspecialchars($app['community']) ?> (<?= htmlspecialchars($app['caste']) ?>)</div></div>
                <div class="grid-item full-width">
                    <div class="label">Address</div>
                    <div class="value"><?= nl2br(htmlspecialchars($app['address'])) ?></div>
                </div>
            </div>
        </div>

        <div class="card-section">
            <h2><i class="fas fa-graduation-cap"></i> Academic Scores</h2>
            <div class="grid">
                <div class="grid-item full-width">
                    <div class="academic-score-wrapper">
                        <div class="score-header"><?= htmlspecialchars($app['exam_name_1']) ?></div>
                        <div class="score-body">
                            <div class="score-details">
                                <strong>Year of Passing:</strong> <span><?= htmlspecialchars($app['exam_year_reg_1']) ?></span>
                                <strong>Class:</strong> <span><?= htmlspecialchars($app['exam_class_1']) ?></span>
                                <strong>Board:</strong> <span><?= htmlspecialchars($app['exam_board_1']) ?></span>
                            </div>
                            <div class="score-result">
                                <strong><?= htmlspecialchars($app['exam_marks_1']) ?><?= strpos($app['exam_marks_1'], '.') !== false ? '%' : '' ?></strong>
                                <span class="marks-label">Marks/Percentage</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid-item full-width">
                    <div class="academic-score-wrapper">
                         <div class="score-header"><?= htmlspecialchars($app['exam_name_2']) ?></div>
                        <div class="score-body">
                            <div class="score-details">
                                <strong>Year of Passing:</strong> <span><?= htmlspecialchars($app['exam_year_reg_2']) ?></span>
                                <strong>Class:</strong> <span><?= htmlspecialchars($app['exam_class_2']) ?></span>
                                <strong>Board:</strong> <span><?= htmlspecialchars($app['exam_board_2']) ?></span>
                            </div>
                            <div class="score-result">
                                <strong><?= htmlspecialchars($app['exam_marks_2']) ?><?= strpos($app['exam_marks_2'], '.') !== false ? '%' : '' ?></strong>
                                <span class="marks-label">Marks/Percentage</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($app['lateral_exam_name'])): ?>
                <div class="grid-item full-width">
                    <div class="academic-score-wrapper">
                        <div class="score-header">Lateral Entry Details (Diploma/Equivalent)</div>
                        <div class="score-body">
                            <div class="score-details">
                                <strong>Exam Name:</strong> <span><?= htmlspecialchars($app['lateral_exam_name']) ?></span>
                                <strong>Year of Registration:</strong> <span><?= htmlspecialchars($app['lateral_exam_year_reg']) ?></span>
                            </div>
                            <div class="score-result">
                                <strong><?= htmlspecialchars($app['lateral_percentage']) ?>%</strong>
                                <span class="marks-label">Final Percentage</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-section">
            <h2><i class="fas fa-clipboard-list"></i> Extra Info & Status</h2>
            <div class="grid">
                <div class="grid-item"><div class="label">Sports Level</div><div class="value"><?= htmlspecialchars($app['sports_level']) ?><?= get_file_button('sports', $app_id, $conn) ?></div></div>
                <div class="grid-item"><div class="label">Ex-Servicemen Quota</div><div class="value"><?= htmlspecialchars($app['ex_servicemen']) ?><?= get_file_button('ex_servicemen', $app_id, $conn) ?></div></div>
                <div class="grid-item"><div class="label">Disabled Status</div><div class="value"><?= htmlspecialchars($app['disabled']) ?> <?= !empty($app['disability_category']) ? "— " . htmlspecialchars($app['disability_category']) . "" : "" ?><?= get_file_button('disabled', $app_id, $conn) ?></div></div>
                <div class="grid-item"><div class="label">Parent in VMRF?</div><div class="value"><?= htmlspecialchars($app['parent_vmrf']) ?> <?= !empty($app['parent_vmrf_details']) ? "(" . htmlspecialchars($app['parent_vmrf_details']) . ")" : "" ?><?= get_file_button('parent_vmrf', $app_id, $conn) ?></div></div>
                <div class="grid-item"><div class="label">Submitted On</div><div class="value"><?= htmlspecialchars($app['submitted_at']) ?></div></div>
            </div>
        </div>
        <div class="card-section declaration-section">
            <h2><i class="fas fa-file-contract"></i> Declaration</h2>
            <div class="declaration-text">
                I hereby declare that the information provided above is true to the best of my knowledge. I understand that any discrepancy may lead to the cancellation of my application.
            </div>
            
            <div class="grid">
                <div class="grid-item full-width">
                    <div class="signature-box">
                        <?php 
                        // Check if signature path exists and file is valid
                        $sig_path = $app['signature_path']; // Ensure this matches your DB column name
                        
                        // Handle formatting if path starts with '../' (cleanup for display)
                        if (strpos($sig_path, '../') === 0) {
                            $sig_path = substr($sig_path, 3);
                        }
                        
                        if (!empty($sig_path) && file_exists($sig_path)): ?>
                            <img src="<?= htmlspecialchars($sig_path) ?>" alt="Applicant Signature" class="signature-img">
                        <?php else: ?>
                            <div style="height: 50px; border-bottom: 1px solid #000; width: 200px; display:inline-block;"></div>
                        <?php endif; ?>
                        <div class="signature-label">Signature of the Applicant</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    
                    
    <div class="center">
        <a class="download-btn" id="download-btn" href="generate_pdf.php?id=<?= $app['id'] ?>">
            <i class="fas fa-file-pdf"></i> Download Your PDF
        </a>
        
        <?php // --- ⭐️ NEW LOGOUT BUTTON ⭐️ ---
        // Only show this button if the user is a STUDENT
        if (isset($_SESSION['application_id'])): ?>
            <a class="download-btn logout" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <?php endif; 
        // --- END NEW LOGOUT BUTTON --- ?>
    </div>
</div>

<script src="confirmation_script.js"></script>
</body>
</html>