<?php
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

$type = $_POST['signature_type'] ?? '';

if ($type === 'draw') {
    $data = $_POST['signature_data'] ?? '';
    $data = str_replace('data:image/png;base64,', '', $data);
    $data = str_replace(' ', '+', $data);
    $decoded = base64_decode($data);
    $filename = 'signature_draw_' . time() . '.png';
    file_put_contents('uploads/' . $filename, $decoded);
    echo "<h3>Signature (Drawn)</h3><img src='uploads/$filename' width='300'>";
}

elseif ($type === 'type') {
    $text = $_POST['signature_data'] ?? 'No signature';
    $filename = 'signature_type_' . time() . '.png';

    // Generate image from text
    $img = imagecreatetruecolor(400, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 0, 0, 400, 100, $white);
    $font = __DIR__ . '/arial.ttf'; // Add a font file in folder if needed
    imagettftext($img, 28, 0, 20, 60, $black, $font, $text);
    imagepng($img, 'uploads/' . $filename);
    imagedestroy($img);

    echo "<h3>Signature (Typed)</h3><img src='uploads/$filename' width='300'>";
}

elseif ($type === 'upload' && isset($_FILES['signature_file'])) {
    $fileTmp = $_FILES['signature_file']['tmp_name'];
    $filename = 'signature_upload_' . time() . '.png';
    move_uploaded_file($fileTmp, 'uploads/' . $filename);
    echo "<h3>Signature (Uploaded)</h3><img src='uploads/$filename' width='300'>";
}

else {
    echo "No valid signature data received.";
}
?>
