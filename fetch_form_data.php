<?php
// fetch_form_data.php
// Requires $conn, $student_id, and $scholarship_id to be defined (from student_form.php and auth_check.php)

// Fetch student + scholarship info
$query = "
    SELECT ss.*, s.name AS scholarship_name, ay.year_range AS academic_year_name
    FROM scholarship_students ss
    JOIN scholarships s ON ss.scholarship_id = s.id
    LEFT JOIN academic_years ay ON ss.academic_year = ay.id
    WHERE ss.id = '$student_id'
";
$student_result = mysqli_query($conn, $query);
$student = mysqli_fetch_assoc($student_result);

if (!$student) {
    // Note: Re-using the echo/script method here for simplicity, but a dedicated error page is better.
    echo "<script>alert('Error: Student record not found.'); window.location.href='index.php';</script>";
    exit;
}

// Fetch college-program mappings
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

?>