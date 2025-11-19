<?php
session_start();
include("../config.php");

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = [
    'stats' => [
        'total_scholars' => 0,
        'submitted' => 0,
        'not_applied' => 0
    ],
    'gender' => [],
    'community' => [],
    'institution' => []
];

// --- 1. Fetch Totals ---

// A. Total Eligible Scholars (Imported Students)
$sql_scholars = "SELECT COUNT(*) AS count FROM scholarship_students";
$result_scholars = $conn->query($sql_scholars);
$scholars_count = (int)($result_scholars->fetch_assoc()['count'] ?? 0);

// B. Total Submitted Applications
$sql_submitted = "SELECT COUNT(*) AS count FROM applications";
$result_submitted = $conn->query($sql_submitted);
$submitted_count = (int)($result_submitted->fetch_assoc()['count'] ?? 0);

// C. Not Yet Applied (Scholars - Submitted)
// Note: This is a simple subtraction. If you need exact SQL checking for duplicates, let me know.
$not_applied_count = max(0, $scholars_count - $submitted_count);

$response['stats']['total_scholars'] = $scholars_count;
$response['stats']['submitted'] = $submitted_count;
$response['stats']['not_applied'] = $not_applied_count;


// --- 2. Filters for Charts Only ---
$base_where = " WHERE 1=1 ";
$params = [];
$types = "";

if (!empty($_GET['scholarship_id'])) {
    $base_where .= " AND a.scholarship_id = ? ";
    $params[] = $_GET['scholarship_id'];
    $types .= "i";
}
if (!empty($_GET['community'])) {
    $base_where .= " AND a.community = ? ";
    $params[] = $_GET['community'];
    $types .= "s";
}
if (!empty($_GET['institution_id'])) {
    $base_where .= " AND a.institution_name = ? ";
    $params[] = $_GET['institution_id'];
    $types .= "i";
}

// Helper Bind Function
function bind_params($stmt, $types, $params) {
    if (!empty($params)) $stmt->bind_param($types, ...$params);
}

// Gender Data
$sql_gender = "SELECT gender, COUNT(*) AS count FROM applications a $base_where AND gender IS NOT NULL AND gender != '' GROUP BY gender";
$stmt_gender = $conn->prepare($sql_gender);
bind_params($stmt_gender, $types, $params);
$stmt_gender->execute();
$result_gender = $stmt_gender->get_result();
while ($row = $result_gender->fetch_assoc()) $response['gender'][$row['gender']] = (int)$row['count'];

// Community Data
$sql_community = "SELECT community, COUNT(*) AS count FROM applications a $base_where AND community IS NOT NULL AND community != '' GROUP BY community ORDER BY count DESC";
$stmt_community = $conn->prepare($sql_community);
bind_params($stmt_community, $types, $params);
$stmt_community->execute();
$result_community = $stmt_community->get_result();
while ($row = $result_community->fetch_assoc()) $response['community'][$row['community']] = (int)$row['count'];

// Institution Data
$sql_institution = "
    SELECT a.institution_name AS id, c.name AS college_name, COUNT(*) AS count 
    FROM applications a
    LEFT JOIN colleges c ON a.institution_name = c.id
    $base_where AND a.institution_name IS NOT NULL
    GROUP BY a.institution_name, c.name
    ORDER BY count DESC LIMIT 10
";
$stmt_institution = $conn->prepare($sql_institution);
bind_params($stmt_institution, $types, $params);
$stmt_institution->execute();
$result_institution = $stmt_institution->get_result();
while ($row = $result_institution->fetch_assoc()) {
    $response['institution'][] = [
        'id' => (int)$row['id'],
        'name' => htmlspecialchars($row['college_name'] ?? 'ID: '.$row['id']),
        'count' => (int)$row['count']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>