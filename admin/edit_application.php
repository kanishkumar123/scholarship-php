<?php
session_start();
include("../config.php"); 

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { die("Invalid Application ID"); }
$app_id = intval($_GET['id']);

// Fetch App Data
$query = "SELECT a.*, ss.name AS student_name, ss.application_no, s.name AS scholarship_name, ay.year_range AS academic_year_name
          FROM applications a
          JOIN scholarship_students ss ON a.student_id = ss.id
          JOIN scholarships s ON a.scholarship_id = s.id
          LEFT JOIN academic_years ay ON ss.academic_year = ay.id
          WHERE a.id = '$app_id' LIMIT 1";
$result = mysqli_query($conn, $query);
$app = mysqli_fetch_assoc($result);
if (!$app) die("Application not found.");

// Fetch College Mappings
$mapping_query = mysqli_query($conn, "SELECT c.id AS college_id, c.name AS college_name, p.id AS program_id, p.name AS program_name FROM college_program_mapping m JOIN colleges c ON m.college_id = c.id JOIN programs p ON m.program_id = p.id ORDER BY c.name ASC, p.name ASC");
$college_programs = [];
while ($row = mysqli_fetch_assoc($mapping_query)) {
    $college_programs[$row['college_id']]['name'] = $row['college_name'];
    $college_programs[$row['college_id']]['programs'][] = ['id' => $row['program_id'], 'name' => $row['program_name']];
}

// --- FETCH UPLOADED FILES ---
$files_query = mysqli_query($conn, "SELECT file_type, file_path FROM application_files WHERE application_id = '$app_id'");
$uploaded_files = [];
while($row = mysqli_fetch_assoc($files_query)) {
    // Store path exactly as in DB (e.g. "uploads/file.pdf")
    $uploaded_files[$row['file_type']] = $row['file_path'];
}

// Helper Functions
function val($k, $d) { return htmlspecialchars($d[$k] ?? ''); }
function sel($k, $d, $v) { return (isset($d[$k]) && $d[$k] == $v) ? 'selected' : ''; }
function chk($k, $d, $v) { if (!isset($d[$k])) return ''; $values = explode(',', $d[$k]); return in_array($v, $values) ? 'checked' : ''; }
function showIf($k, $d, $v) { return (isset($d[$k]) && $d[$k] == $v) ? 'block' : 'none'; }

// Helper to make DB path viewable from Admin folder (prepend ../)
function adminLink($path) {
    if(empty($path)) return '';
    // If it doesn't start with ../, add it
    return (strpos($path, '../') === false) ? '../' . $path : $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Application #<?= val('application_no', $app) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="../application_style.css">
<link rel="stylesheet" href="../pad.css">
<style>
    .card-header { background: linear-gradient(135deg, #2c3e50, #4ca1af); } 
    .current-signature-preview { border: 1px dashed #ccc; padding: 10px; text-align: center; margin-bottom: 15px; background: #f9f9f9; border-radius: 8px; }
    .current-signature-preview img { max-height: 80px; max-width: 100%; }
    .signature-pad-wrapper { width: 100%; height: 200px; border: 1px solid #ccc; background-color: #fff; margin-bottom: 10px; border-radius: 8px;}
    canvas#signature-pad { width: 100%; height: 100%; display: block; touch-action: none; }
    .tab { display: inline-block; padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; }
    .tab.active { border-bottom: 2px solid #007bff; font-weight: bold; color: #007bff; }
    
    /* CRITICAL: Ensure file link is present in DOM but hidden, so JS can find it */
    .file-link { display: none !important; }
    .form-group { margin-bottom: 25px; }
</style>
</head>
<body> 

<div class="container">
    <div style="margin-bottom: 20px;">
        <a href="view_applications.php" class="nav-btn prev" style="text-decoration:none; background: #6c757d; width:auto; display:inline-block;"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div style="padding: 15px; margin-bottom: 20px; border-radius: 5px; color: white; background-color: <?= $_SESSION['message']['type'] == 'success' ? '#28a745' : '#dc3545' ?>;">
            <?= $_SESSION['message']['text'] ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <h2 class="card-header"><span class="l">E</span><span class="l">D</span><span class="l">I</span><span class="l">T</span> <span class="l">A</span><span class="l">P</span><span class="l">P</span></h2>
    
    <div class="card">
        <div class="info-bar">
            <div class="info-item full-width-name"><div class="label">Student:</div><div class="value"><?= val('student_name', $app) ?></div></div>
            <div class="info-item"><div class="label">App No:</div><div class="value"><?= val('application_no', $app) ?></div></div>
            <div class="info-item"><div class="label">Scholarship:</div><div class="value"><?= val('scholarship_name', $app) ?></div></div>
        </div>

        <div class="top-nav">
            <button type="button" class="nav-tab active" data-page="1">1. Personal</button>
            <button type="button" class="nav-tab" data-page="2">2. Education</button>
            <button type="button" class="nav-tab" data-page="3">3. Claims & Sig</button>
        </div>

        <form action="update_application.php" method="post" enctype="multipart/form-data" id="main-application-form">
            <input type="hidden" name="app_id" value="<?= $app_id ?>">
            <input type="hidden" name="signature_type" id="signature_type" value="draw">
            <input type="hidden" name="signature_data" id="signature_data">
            <input type="hidden" name="signature_updated" id="signature_updated" value="0"> 

            <div id="page1" class="page active">
                <h3 class="page-header">Personal Details</h3>
                <div class="form-group">
                    <select name="institution_name" id="institution_name" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($college_programs as $col_id => $data): ?>
                            <option value="<?= $col_id ?>" <?= sel('institution_name', $app, $col_id) ?>><?= $data['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>Institution Name:</label><div class="input-bg"></div>
                </div>
                <div class="form-group">
                    <select name="course" id="course" required><option value="">-- Select --</option></select>
                    <label>Course:</label><div class="input-bg"></div>
                </div>
                 <div class="form-group-inline">
                    <div class="form-group">
                        <select name="year_of_study" id="year_of_study" required>
                            <option value="">-- Select --</option>
                            <?php foreach ([1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V'] as $num => $roman): ?>
                                <option value="<?= $num ?>" <?= sel('year_of_study', $app, $num) ?>><?= $roman ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Year:</label><div class="input-bg"></div>
                    </div>
                    <div class="form-group">
                        <select name="semester" id="semester"></select>
                        <label>Semester:</label><div class="input-bg"></div>
                    </div>
                </div>
                <?php 
                $fields = [['gender','Gender',['Male','Female','Other']], ['father_name','Father\'s Name'], ['mother_name','Mother\'s Name'], ['community','Community',['OC','BC','OBC','MBC','DNC','SC','ST']], ['caste','Caste'], ['family_income','Annual Family Income (₹)'], ['address','Permanent Address', 'textarea'], ['mobile','Mobile'], ['email','Email']];
                foreach ($fields as $f) {
                     $name=$f[0]; $label=$f[1]; $type=$f[2]??'text'; $val=val($name, $app);
                     echo '<div class="form-group">';
                     if($type==='textarea') echo "<textarea name='$name' id='$name'>$val</textarea>";
                     elseif(is_array($type)) {
                         echo "<select name='$name' id='$name'><option value=''>Select</option>";
                         foreach($type as $opt) echo "<option value='$opt' ".(($val==$opt)?'selected':'').">$opt</option>";
                         echo "</select>";
                     } else echo "<input type='text' name='$name' id='$name' value='$val'>";
                     echo "<label>$label</label><div class='input-bg'></div></div>";
                }
                ?>
            </div>
            
            <div id="page2" class="page">
                <h3 class="page-header">Education</h3>
                 <div class="form-section">
                    <h4>Previous Examination Details</h4>
                    <table class="styled-table">
                        <thead><tr><th>Exam</th><th>Year/Reg</th><th>Board</th><th>Class</th><th>Marks</th></tr></thead>
                        <tbody>
                        <?php for($i=1;$i<=2;$i++): ?>
                        <tr>
                            <td data-label="Exam"><input type="text" name="exam_name_<?= $i ?>" value="<?= val("exam_name_$i", $app) ?>"></td>
                            <td data-label="Year"><input type="text" name="exam_year_reg_<?= $i ?>" value="<?= val("exam_year_reg_$i", $app) ?>"></td>
                            <td data-label="Board"><input type="text" name="exam_board_<?= $i ?>" value="<?= val("exam_board_$i", $app) ?>"></td>
                            <td data-label="Class"><input type="text" name="exam_class_<?= $i ?>" value="<?= val("exam_class_$i", $app) ?>"></td>
                            <td data-label="Marks"><input type="text" name="exam_marks_<?= $i ?>" value="<?= val("exam_marks_$i", $app) ?>"></td>
                        </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-section">
                    <h4>Lateral Entry</h4>
                    <div class="form-group"><input type="text" name="lateral_exam_name" value="<?= val('lateral_exam_name', $app) ?>" placeholder=" "><label>Exam Passed</label><div class="input-bg"></div></div>
                    <div class="form-group"><input type="text" name="lateral_exam_year_reg" value="<?= val('lateral_exam_year_reg', $app) ?>" placeholder=" "><label>Reg No</label><div class="input-bg"></div></div>
                    <div class="form-group"><input type="text" name="lateral_percentage" value="<?= val('lateral_percentage', $app) ?>" placeholder=" "><label>Percentage</label><div class="input-bg"></div></div>
                </div>
            </div>

            <div id="page3" class="page">
                <h3 class="page-header">Claims & Signature</h3>

                <div class="form-section">
                    <h4>Sports</h4>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="sports_level[]" value="District" <?= chk('sports_level', $app, 'District') ?>> District</label>
                        <label><input type="checkbox" name="sports_level[]" value="State" <?= chk('sports_level', $app, 'State') ?>> State</label>
                    </div>
                    <div class="upload-area">
                        <span class="icon"><i class="fas fa-upload"></i></span>
                        <strong>Upload Proof</strong>
                        <input type="file" name="sports_proof" accept=".pdf,.jpg,.jpeg,.png">
                        
                        <?php if (!empty($uploaded_files['sports'])): ?>
                            <a href="<?= adminPath($uploaded_files['sports']) ?>" class="file-link"><?= basename($uploaded_files['sports']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Ex-Servicemen</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="ex_servicemen" value="Yes" <?= chk('ex_servicemen', $app, 'Yes') ?> onclick="toggleSection('ex_servicemen', 'ex_sec')"> Yes</label>
                        <label><input type="radio" name="ex_servicemen" value="No" <?= chk('ex_servicemen', $app, 'No') ?> onclick="toggleSection('ex_servicemen', 'ex_sec')"> No</label>
                    </div>
                    <div id="ex_sec" class="conditional-section" style="display:<?= showIf('ex_servicemen', $app, 'Yes') ?>;">
                        <div class="upload-area">
                             <span class="icon"><i class="fas fa-file-upload"></i></span>
                             <strong>Upload Proof</strong>
                             <input type="file" name="ex_servicemen_proof">
                             <?php if (!empty($uploaded_files['ex_servicemen'])): ?>
                                <a href="<?= adminPath($uploaded_files['ex_servicemen']) ?>" class="file-link"><?= basename($uploaded_files['ex_servicemen']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Disabled Status</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="disabled" value="Yes" <?= chk('disabled', $app, 'Yes') ?> onclick="toggleSection('disabled', 'dis_sec')"> Yes</label>
                        <label><input type="radio" name="disabled" value="No" <?= chk('disabled', $app, 'No') ?> onclick="toggleSection('disabled', 'dis_sec')"> No</label>
                    </div>
                    <div id="dis_sec" class="conditional-section" style="display:<?= showIf('disabled', $app, 'Yes') ?>;">
                        <div class="form-group">
                            <input type="text" name="disability_category" value="<?= val('disability_category', $app) ?>">
                            <label>Category</label><div class="input-bg"></div>
                        </div>
                        <div class="upload-area">
                            <span class="icon"><i class="fas fa-wheelchair"></i></span>
                            <strong>Upload Proof</strong>
                            <input type="file" name="disability_proof">
                             <?php if (!empty($uploaded_files['disabled'])): ?>
                                <a href="<?= adminPath($uploaded_files['disabled']) ?>" class="file-link"><?= basename($uploaded_files['disabled']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Parent in VMRF</h4>
                    <div class="radio-group">
                        <label><input type="radio" name="parent_vmrf" value="Yes" <?= chk('parent_vmrf', $app, 'Yes') ?> onclick="toggleSection('parent_vmrf', 'vmrf_sec')"> Yes</label>
                        <label><input type="radio" name="parent_vmrf" value="No" <?= chk('parent_vmrf', $app, 'No') ?> onclick="toggleSection('parent_vmrf', 'vmrf_sec')"> No</label>
                    </div>
                    <div id="vmrf_sec" class="conditional-section" style="display:<?= showIf('parent_vmrf', $app, 'Yes') ?>;">
                        <div class="form-group">
                            <input type="text" name="parent_vmrf_details" value="<?= val('parent_vmrf_details', $app) ?>">
                            <label>Details</label><div class="input-bg"></div>
                        </div>
                        <div class="upload-area">
                            <span class="icon"><i class="fas fa-id-card"></i></span>
                            <strong>Upload Proof</strong>
                            <input type="file" name="parent_vmrf_proof">
                            <?php if (!empty($uploaded_files['parent_vmrf'])): ?>
                                <a href="<?= adminPath($uploaded_files['parent_vmrf']) ?>" class="file-link"><?= basename($uploaded_files['parent_vmrf']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <h2 style="margin-top: 30px;">Signature</h2>
                <?php if (!empty($app['signature_path'])): ?>
                    <div class="current-signature-preview">
                        <p style="font-size:0.8em; margin-bottom:5px;">Current Signature:</p>
                        <img src="<?= adminPath($app['signature_path']) ?>" alt="Signature">
                    </div>
                <?php endif; ?>
                
                <div>
                    <div class="tab active" data-target="draw">Draw New</div>
                    <div class="tab" data-target="type">Type New</div>
                    <div class="tab" data-target="upload">Upload New</div>
                </div>
                <div class="tab-content" id="tab-draw">
                    <div class="signature-pad-wrapper"><canvas id="signature-pad"></canvas></div>
                    <button type="button" id="clear" class="nav-btn prev">Clear</button>
                </div>
                <div class="tab-content" id="tab-type" style="display:none;">
                    <div class="form-group"><input type="text" id="typed-signature" placeholder=" "><label>Type Name</label><div class="input-bg"></div></div>
                </div>
                <div class="tab-content" id="tab-upload" style="display:none;">
                     <div class="upload-area"><input type="file" name="signature_file" id="signature_file"></div>
                </div>
            </div> 
            
            <div class="navigation-buttons">
                <button type="button" class="nav-btn prev" id="prevBtn" onclick="prevPage()" style="display:none;">Prev</button>
                <button type="button" class="nav-btn next" id="nextBtn" onclick="nextPage()">Next</button>
                <button type="submit" class="nav-btn submit" id="submitBtn" style="display:none;">Update</button>
            </div>
        </form> 
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>
<script src="../application_scripts.js"></script> 
<script>
    const collegePrograms = <?= json_encode($college_programs) ?>;
    const preSelectedCollege = "<?= val('institution_name', $app) ?>";
    const preSelectedCourse = "<?= val('course', $app) ?>";
    const preSelectedSemester = "<?= val('semester', $app) ?>";

    document.addEventListener("DOMContentLoaded", () => {
        // 1. Mark fields dirty so floating labels float
        document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(input => {
            if(input.value.trim() !== "") {
                input.classList.add('is-dirty');
                const wrapper = input.closest('.form-group');
                if(wrapper) wrapper.classList.add('validated');
            }
        });

        // 2. Trigger Dropdown updates
        if(typeof updatePrograms === 'function' && preSelectedCollege) {
            const instSelect = document.getElementById('institution_name');
            for(let i=0; i<instSelect.options.length; i++) {
                 if(instSelect.options[i].value == preSelectedCollege) {
                     instSelect.selectedIndex = i;
                     break;
                 }
            }
            updatePrograms(); 
        }
        if(typeof updateSemesters === 'function' && document.getElementById('year_of_study').value) {
            updateSemesters();
        }

        // 3. Re-select dependent fields
        setTimeout(() => {
             const courseSelect = document.getElementById('course');
             if(courseSelect && preSelectedCourse) {
                 courseSelect.value = preSelectedCourse;
                 courseSelect.classList.add('is-dirty');
                 if(courseSelect.closest('.form-group')) courseSelect.closest('.form-group').classList.add('validated');
             }
             
             const semSelect = document.getElementById('semester');
             if(semSelect && preSelectedSemester) {
                 semSelect.value = preSelectedSemester;
                 semSelect.classList.add('is-dirty');
                 if(semSelect.closest('.form-group')) semSelect.closest('.form-group').classList.add('validated');
             }
        }, 200);
    });
</script>
</body>
</html>