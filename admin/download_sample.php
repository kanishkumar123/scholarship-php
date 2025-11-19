<?php
// Force download of sample CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="students_sample.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write the NEW 4-column header
fputcsv($output, ['ApplicationNo', 'DOB', 'Name', 'ScholarshipCode']);

// Write sample rows
// IMPORTANT: The DOB must be in dd-mm-yyyy format, as your upload script expects.


fclose($output);
exit;
?>