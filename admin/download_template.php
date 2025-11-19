<?php
// Set the headers to force a download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="application_upload_template.csv"');

// --- INSTRUCTIONS ---
$instructions = [
    ["# INSTRUCTIONS FOR BULK APPLICATION UPLOAD"],
    ["# 1. Fields marked with (*) are MANDATORY."],
    ["# 2. For 'Yes' or 'No' fields, please use the exact text: Yes / No."],
    ["# 3. Do not change or remove the header row below (the one starting with 'reg_no')."],
    [] // Blank row for spacing
];

// --- COLUMN HEADERS (with mandatory fields marked) ---
$headers = [
    'reg_no (*)',
    'institution_name (*)',
    'course (*)',
    'year_of_study (*)',
    'semester (*)',
    'gender (*)',
    'father_name',
    'mother_name',
    'community',
    'caste',
    'family_income (*)',
    'address',
    'phone_std',
    'mobile',
    'email',
    'exam_name_1',
    'exam_year_reg_1',
    'exam_board_1',
    'exam_class_1',
    'exam_marks_1',
    'exam_name_2',
    'exam_year_reg_2',
    'exam_board_2',
    'exam_class_2',
    'exam_marks_2',
    'lateral_exam_name',
    'lateral_exam_year_reg',
    'lateral_percentage',
    'sports_level',
    'ex_servicemen',
    'disabled',
    'disability_category',
    'parent_vmrf',
    'parent_vmrf_details'
];

// Open the output stream
$output = fopen('php://output', 'w');

// Write the instruction rows
foreach ($instructions as $line) {
    fputcsv($output, $line);
}

// Write the actual header row
fputcsv($output, $headers);

// Close the stream and exit
fclose($output);
exit;
?>