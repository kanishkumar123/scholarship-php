<?php
// ==========================================================
// 1. ADMIN AUTHENTICATION
// ==========================================================
session_start();

// FIX: Go up one level to find config.php
include("../config.php"); 

// Ensure Admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die("Access Denied: Admin login required.");
}

if (!isset($_GET['id'])) {
    die("Error: No Application ID provided.");
}

$app_id = intval($_GET['id']);

// ==========================================================
// 2. DATA FETCHING
// ==========================================================

// A. Fetch Application Data
$query = "SELECT a.*, s.name AS scholarship_name, ay.year_range AS academic_year_name
          FROM applications a
          JOIN scholarships s ON a.scholarship_id = s.id
          LEFT JOIN academic_years ay ON a.academic_year = ay.id
          WHERE a.id = '$app_id'";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}

$app = mysqli_fetch_assoc($result);

if (!$app) {
    die("Application not found.");
}

// B. Fetch College Mappings (for dropdowns)
$mapping_query = mysqli_query($conn, "
    SELECT c.id AS college_id, c.name AS college_name, p.id AS program_id, p.name AS program_name
    FROM college_program_mapping m
    JOIN colleges c ON m.college_id = c.id
    JOIN programs p ON m.program_id = p.id
    ORDER BY c.name ASC, p.name ASC
");

$college_programs = [];
while ($row = mysqli_fetch_assoc($mapping_query)) {
    $college_programs[$row['college_id']]['name'] = $row['college_name'];
    $college_programs[$row['college_id']]['programs'][] = [
        'id' => $row['program_id'],
        'name' => $row['program_name']
    ];
}

// C. Helper function to check for existing uploaded files
function get_existing_file($conn, $app_id, $file_type) {
    $q = "SELECT file_path FROM application_files WHERE application_id = '$app_id' AND file_type = '$file_type' LIMIT 1";
    $r = mysqli_query($conn, $q);
    if ($row = mysqli_fetch_assoc($r)) {
        // FIX: The DB stores "uploads/image.jpg". 
        // Since we are in the "admin" folder, we need to go back up "../" to see it.
        $db_path = $row['file_path'];
        
        // Remove ../ if it's already there to avoid duplication, then add it back consistently
        $clean_path = str_replace('../', '', $db_path); 
        return '../' . $clean_path;
    }
    return false;
}

// Helper for Signature Display
function get_signature_path($path) {
    if (empty($path)) return '';
    $clean_path = str_replace('../', '', $path);
    return '../' . $clean_path;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Application #<?= $app['application_no'] ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="../application_style.css"> 
<style>
    /* Specific overrides for Edit Mode */
    body { background: #f4f6f9; }
    .card-header { background: #ffc107; color: #333; } /* Yellow for Edit Mode */
    .nav-tab.active { border-bottom: 3px solid #ffc107; color: #333; }
    
    .file-preview-link {
        display: inline-block;
        margin-bottom: 10px;
        padding: 5px 10px;
        background: #e9ecef;
        color: #007bff;
        border-radius: 4px;
        font-size: 0.85em;
        text-decoration: none;
    }
    .file-preview-link:hover { background: #dce1e6; }
    
    .readonly-sig { 
        border: 2px dashed #ccc; 
        background: #f9f9f9; 
        padding: 20px; 
        text-align: center; 
        border-radius: 8px;
    }
    .admin-badge {
        background: #dc3545; color: white; padding: 2px 8px; 
        border-radius: 4px; font-size: 0.7em; vertical-align: middle;
    }
    
    /* --- NEW CSS FOR CANCEL BUTTON --- */
    .nav-btn.cancel {
        background-color: #6c757d; /* Gray color */
        color: white;
        margin-right: auto; /* Tries to push it to the left if flexbox is used */
    }
    .nav-btn.cancel:hover {
        background-color: #5a6268;
    }
    /* Ensure buttons align nicely */
    .navigation-buttons {
        display: flex;
        justify-content: flex-end; /* Default items to right */
        gap: 10px;
    }
    /* Force cancel to the far left */
    .nav-btn.cancel {
        margin-right: auto; 
    }

    /* --- ⭐️ FIX FOR FILE UPLOAD CLICKING ⭐️ --- */
    /* This overrides any 'hidden' styles from the student CSS */
    .admin-file-label {
        display: block;
        margin-top: 10px;
        padding: 10px;
        background: #fff;
        border: 1px solid #ced4da;
        border-radius: 5px;
        cursor: pointer;
    }
    .admin-file-label:hover {
        background-color: #f8f9fa;
    }
    .admin-file-label input[type="file"] {
        display: block !important; /* Force it to show */
        opacity: 1 !important;     /* Make sure it's not transparent */
        position: static !important;
        margin-top: 5px;
        width: 100%;
        cursor: pointer;
    }
</style>
</head>
<body> 

<div class="container">
<h2 class="card-header">
    EDIT APPLICATION <span class="admin-badge">ADMIN MODE</span>
</h2>
    <div class="card">
        
        <div class="info-bar">
            <div class="info-item full-width-name">
                <div class="label">Student Name:</div>
                <div class="value"><?= htmlspecialchars($app['name']) ?></div>
            </div>
            <div class="info-item">
                <div class="label">App No:</div>
                <div class="value"><?= htmlspecialchars($app['application_no']) ?></div>
            </div>
            <div class="info-item">
                <div class="label">Academic Year:</div>
                <div class="value"><?= htmlspecialchars($app['academic_year_name'] ?? (string)$app['academic_year']) ?></div>
            </div>
        </div>

        <div class="top-nav">
            <button type="button" class="nav-tab active" data-page="1">1. Personal & Academic</button>
            <button type="button" class="nav-tab" data-page="2">2. Educational Qualification</button>
            <button type="button" class="nav-tab" data-page="3">3. Special Claims & Documents</button>
        </div>

        <form action="update_application.php" method="post" enctype="multipart/form-data" id="main-application-form" onsubmit="return confirm('Are you sure you want to update this application?');">
            <input type="hidden" name="application_id" value="<?= $app_id ?>">
            
            <div id="page1" class="page active">
                <h3 class="page-header"><i class="fas fa-edit"></i> Edit Personal & Academic Details</h3>

                <div class="form-group">
                    <select name="institution_name" id="institution_name" required>
                        <option value="">-- SELECT INSTITUTION --</option>
                        <?php foreach ($college_programs as $college_id => $data): 
                            $selected = ($app['institution_name'] == $college_id) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($college_id) ?>" <?= $selected ?>>
                                <?= htmlspecialchars($data['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label>Institution Name:</label>
                    <div class="input-bg"></div>
                </div>

                <div class="form-group">
                    <select name="course" id="course" required>
                        <option value="">-- SELECT COURSE --</option>
                    </select>
                    <label>Course / Programme:</label>
                    <div class="input-bg"></div>
                </div>

                <div class="form-group-inline">
                    <div class="form-group">
                        <select name="year_of_study" id="year_of_study" required>
                            <option value="">-- Select Year --</option>
                            <?php
                            $roman_years = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];
                            foreach ($roman_years as $num => $roman):
                                $selected = ($app['year_of_study'] == $num) ? 'selected' : '';
                            ?>
                                <option value="<?= $num ?>" <?= $selected ?>><?= $roman ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Year of Study:</label>
                        <div class="input-bg"></div>
                    </div>
                    
                    <div class="form-group">
                        <select name="semester" id="semester">
                            <option value="<?= htmlspecialchars($app['semester']) ?>" selected><?= htmlspecialchars($app['semester']) ?></option>
                        </select>
                        <label>Semester:</label>
                        <div class="input-bg"></div>
                    </div>
                </div>

                <?php
                $fields = [
                    ['name', 'Full Name'], 
                    ['gender', 'Gender', ['Male','Female','Other']],
                    ['father_name', "Father's Name"],
                    ['mother_name', "Mother's Name"],
                    ['community', 'Community', ['OC','BC','OBC','MBC','DNC','SC','ST']],
                    ['caste', 'Caste'],
                    ['family_income', 'Annual Family Income (₹)'],
                    ['mobile', 'Mobile Number'],
                    ['email', 'Email ID'],
                ];

                foreach ($fields as $f) {
                    $name = $f[0];
                    $label = $f[1];
                    $options = $f[2] ?? null;
                    $value = htmlspecialchars($app[$name]);

                    echo '<div class="form-group">';
                    if ($options) {
                        echo "<select name='$name' id='$name'>";
                        foreach ($options as $opt) {
                            $sel = ($value == $opt) ? 'selected' : '';
                            echo "<option value='$opt' $sel>$opt</option>";
                        }
                        echo "</select>";
                    } else {
                        echo "<input type='text' name='$name' id='$name' value='$value'>";
                    }
                    echo "<label>$label</label><div class='input-bg'></div></div>";
                }
                ?>
                <div class="form-group">
                    <textarea name="address" id="address"><?= htmlspecialchars($app['address']) ?></textarea>
                    <label>Permanent Address</label>
                    <div class="input-bg"></div>
                </div>
            </div>

            <div id="page2" class="page">
                <h3 class="page-header"><i class="fas fa-graduation-cap"></i> Edit Educational Qualification</h3>
                
                <div class="form-section">
                    <h4>Previous Examination Details</h4>
                    <table class="styled-table">
                        <thead>
                        <tr>
                            <th>Examination</th>
                            <th>Year of Passing & Reg. No.</th>
                            <th>Board / University</th>
                            <th>Class / Grade</th>
                            <th>Marks (%)</th>
                        </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-label="Examination"><input type="text" name="exam_name" value="<?= htmlspecialchars($app['exam_name']) ?>"></td>
                                <td data-label="Year & Reg. No."><input type="text" name="exam_year_reg" value="<?= htmlspecialchars($app['exam_year_reg']) ?>"></td>
                                <td data-label="Board / Univ."><input type="text" name="exam_board" value="<?= htmlspecialchars($app['exam_board']) ?>"></td>
                                <td data-label="Class / Grade"><input type="text" name="exam_class" value="<?= htmlspecialchars($app['exam_class']) ?>"></td>
                                <td data-label="Marks (%)"><input type="text" name="exam_marks" value="<?= htmlspecialchars($app['exam_marks']) ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-section">
                    <h4>Lateral Entry Students (If Applicable)</h4>
                    <div class="form-group">
                        <input type="text" name="lateral_exam_name" value="<?= htmlspecialchars($app['lateral_exam_name']) ?>">
                        <label>Exam Passed (Degree/Diploma):</label>
                        <div class="input-bg"></div>
                    </div>
                    <div class="form-group">
                        <input type="text" name="lateral_exam_year_reg" value="<?= htmlspecialchars($app['lateral_exam_year_reg']) ?>">
                        <label>Month & Year of Passing with Reg. No.:</label>
                        <div class="input-bg"></div>
                    </div>
                    <div class="form-group">
                        <input type="text" name="lateral_percentage" value="<?= htmlspecialchars($app['lateral_percentage']) ?>">
                        <label>Percentage Obtained:</label>
                        <div class="input-bg"></div>
                    </div>
                </div>
            </div>

            <div id="page3" class="page">
                <h3 class="page-header"><i class="fas fa-clipboard-list"></i> Edit Special Claims & Documents</h3>

                <div class="form-section">
                    <h4>Sports Achievements</h4>
                    <div class="checkbox-group">
                        <?php 
                        $saved_sports = explode(", ", $app['sports_level']); 
                        $levels = ['District', 'State', 'National', 'International'];
                        foreach ($levels as $lvl) {
                            $checked = in_array($lvl, $saved_sports) ? 'checked' : '';
                            echo "<label><input type='checkbox' name='sports_level[]' value='$lvl' $checked> $lvl</label>";
                        }
                        ?>
                    </div>
                    <div class="upload-area">
                        <?php if($path = get_existing_file($conn, $app_id, 'sports')): ?>
                            <a href="<?= $path ?>" target="_blank" class="file-preview-link"><i class="fas fa-eye"></i> View Current Proof</a>
                        <?php endif; ?>
                        <label class="admin-file-label">
                            <strong><i class="fas fa-upload"></i> Upload New File (Replaces old):</strong>
                            <input type="file" name="sports_proof" accept=".pdf,.jpg,.jpeg,.png">
                        </label>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Ex-Servicemen Quota</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="ex_servicemen" value="Yes" <?= ($app['ex_servicemen'] == 'Yes')?'checked':'' ?>> Yes</label>
                        <label><input type="radio" name="ex_servicemen" value="No" <?= ($app['ex_servicemen'] == 'No')?'checked':'' ?>> No</label>
                    </div>
                    <div class="upload-area">
                         <?php if($path = get_existing_file($conn, $app_id, 'ex_servicemen')): ?>
                            <a href="<?= $path ?>" target="_blank" class="file-preview-link"><i class="fas fa-eye"></i> View Current Proof</a>
                        <?php endif; ?>
                        <label class="admin-file-label">
                            <strong><i class="fas fa-upload"></i> Upload New File (Replaces old):</strong>
                            <input type="file" name="ex_servicemen_proof" accept=".pdf,.jpg,.jpeg,.png">
                        </label>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Disabled Person Status</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="disabled" value="Yes" <?= ($app['disabled'] == 'Yes')?'checked':'' ?>> Yes</label>
                        <label><input type="radio" name="disabled" value="No" <?= ($app['disabled'] == 'No')?'checked':'' ?>> No</label>
                    </div>
                    <div class="form-group">
                        <input type="text" name="disability_category" value="<?= htmlspecialchars($app['disability_category']) ?>">
                        <label>Category:</label>
                        <div class="input-bg"></div>
                    </div>
                    <div class="upload-area">
                        <?php if($path = get_existing_file($conn, $app_id, 'disabled')): ?>
                            <a href="<?= $path ?>" target="_blank" class="file-preview-link"><i class="fas fa-eye"></i> View Current Proof</a>
                        <?php endif; ?>
                        <label class="admin-file-label">
                            <strong><i class="fas fa-upload"></i> Upload New File (Replaces old):</strong>
                            <input type="file" name="disability_proof" accept=".pdf,.jpg,.jpeg,.png">
                        </label>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Parent working in VMRF-DU?</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="parent_vmrf" value="Yes" <?= ($app['parent_vmrf'] == 'Yes')?'checked':'' ?>> Yes</label>
                        <label><input type="radio" name="parent_vmrf" value="No" <?= ($app['parent_vmrf'] == 'No')?'checked':'' ?>> No</label>
                    </div>
                    <div class="form-group">
                        <input type="text" name="parent_vmrf_details" value="<?= htmlspecialchars($app['parent_vmrf_details']) ?>">
                        <label>Details:</label>
                        <div class="input-bg"></div>
                    </div>
                    <div class="upload-area">
                        <?php if($path = get_existing_file($conn, $app_id, 'parent_vmrf')): ?>
                            <a href="<?= $path ?>" target="_blank" class="file-preview-link"><i class="fas fa-eye"></i> View Current Proof</a>
                        <?php endif; ?>
                        <label class="admin-file-label">
                            <strong><i class="fas fa-upload"></i> Upload New File (Replaces old):</strong>
                            <input type="file" name="parent_vmrf_proof" accept=".pdf,.jpg,.jpeg,.png">
                        </label>
                    </div>
                </div>

                <h3 class="page-header">Applicant Signature (Read-Only)</h3>
                <div class="readonly-sig">
                    <?php 
                        $sig = get_signature_path($app['signature_path']);
                        if(!empty($sig) && file_exists($sig)): 
                    ?>
                        <img src="<?= htmlspecialchars($sig) ?>" alt="Signature" style="max-width:300px;">
                        <p style="color:#666; font-size:0.9em;">(Admins cannot edit signatures)</p>
                    <?php else: ?>
                        <p>No signature found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="navigation-buttons">
                <button type="button" class="nav-btn cancel" onclick="if(confirm('Discard changes and go back?')) window.history.back();">
                    <i class="fas fa-times"></i> Cancel
                </button>

                <button type="button" class="nav-btn prev" id="prevBtn" onclick="prevPage()">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="nav-btn next" id="nextBtn" onclick="nextPage()">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" class="nav-btn submit" style="background-color: #28a745;">
                    <i class="fas fa-save"></i> Update Application
                </button>
            </div>
            
        </form> 
    </div>
</div>

<script>
    const collegePrograms = <?= json_encode($college_programs) ?>;
    const savedCourseId = "<?= htmlspecialchars($app['course']) ?>";
    const institutionSelect = document.getElementById('institution_name');
    const courseSelect = document.getElementById('course');

    function updatePrograms() {
        const collegeId = institutionSelect.value;
        courseSelect.innerHTML = '<option value="">-- SELECT COURSE --</option>';

        if (collegeId && collegePrograms[collegeId]) {
            collegePrograms[collegeId].programs.forEach(program => {
                const option = document.createElement('option');
                option.value = program.id;
                option.textContent = program.name;
                if (program.id == savedCourseId) {
                    option.selected = true;
                }
                courseSelect.appendChild(option);
            });
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        if(institutionSelect.value) {
            updatePrograms();
        }
        institutionSelect.addEventListener('change', updatePrograms);
    });

    function showPage(pageId) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
        
        document.getElementById('page' + pageId).classList.add('active');
        document.querySelector(`.nav-tab[data-page="${pageId}"]`).classList.add('active');

        document.getElementById('prevBtn').style.display = (pageId == 1) ? 'none' : 'inline-block';
        document.getElementById('nextBtn').style.display = (pageId == 3) ? 'none' : 'inline-block';
        document.querySelector('.submit').style.display = (pageId == 3) ? 'inline-block' : 'none';
    }

    let currentPage = 1;
    function nextPage() {
        if(currentPage < 3) { currentPage++; showPage(currentPage); }
    }
    function prevPage() {
        if(currentPage > 1) { currentPage--; showPage(currentPage); }
    }

    document.querySelectorAll('.nav-tab').forEach(btn => {
        btn.addEventListener('click', (e) => {
            currentPage = parseInt(e.target.getAttribute('data-page'));
            showPage(currentPage);
        });
    });

    showPage(1);

</script>
</body>
</html>