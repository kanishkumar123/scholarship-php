<?php
session_start();
include("../config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$view = $_GET['view'] ?? 'all';
$pageTitle = ($view === 'pending') ? "Students Not Yet Applied" : "All Eligible Scholars";
$currentPage = 'students_list';

include('header.php');

// --- Logic ---
// ★ FIXED: Removed the JOIN with colleges table. 
// We now select 's.institution_name' directly from the table.
if ($view === 'pending') {
    $sql = "SELECT s.*, sc.name as scholarship_name
            FROM scholarship_students s
            LEFT JOIN scholarships sc ON s.scholarship_id = sc.id
            WHERE s.application_no NOT IN (SELECT application_no FROM applications)
            ORDER BY s.name ASC";
} else {
    $sql = "SELECT s.*, sc.name as scholarship_name
            FROM scholarship_students s
            LEFT JOIN scholarships sc ON s.scholarship_id = sc.id
            ORDER BY s.name ASC";
}

$result = mysqli_query($conn, $sql);
?>

<style>
    .container-fluid { padding: 20px; }
    .table-responsive {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .btn-back { background: #6c757d; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; }
    
    .dt-buttons .dt-button {
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 5px 15px;
        margin-bottom: 15px;
    }
    .dt-buttons .dt-button:hover { background: #218838; }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h2><?= $pageTitle ?> (<?= mysqli_num_rows($result) ?>)</h2>
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped" id="studentsTable">
            <thead>
                <tr>
                    <th>App No</th>
                    <th>Name</th>
                    <th>DOB</th>
                    <th>Institution</th>
                    <th>Scholarship</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): 
                    $dob_formatted = 'N/A';
                    if (!empty($row['dob'])) {
                        $dob_timestamp = strtotime($row['dob']);
                        if ($dob_timestamp) {
                            $dob_formatted = date('d-m-Y', $dob_timestamp);
                        } else {
                            $dob_formatted = $row['dob']; 
                        }
                    }
                    // ★ FIXED: Now pulling directly from the table
                    $institution = !empty($row['institution_name']) ? $row['institution_name'] : 'N/A';
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['application_no']) ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= $dob_formatted ?></td>
                    <td><?= htmlspecialchars($institution) ?></td>
                    <td><?= htmlspecialchars($row['scholarship_name']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('footer.php'); ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap4.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#studentsTable').DataTable({
            dom: 'Bfrtip', 
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Download Excel',
                    className: 'dt-button',
                    title: '<?= $pageTitle ?>_List'
                }
            ],
            "pageLength": 50 
        });
    });
</script>