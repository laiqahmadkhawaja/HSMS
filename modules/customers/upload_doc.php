<?php
/**
 * HSMS - Handle Customer KYC Document Upload
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/app.php';
require_permission('customers_manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_url('modules/customers/index.php'));
}

verify_csrf_or_die();

$custId = (int)($_POST['customer_id'] ?? 0);
$docType = trim($_POST['doc_type'] ?? 'Other');

if ($custId <= 0 || !isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
    redirect_with_message(base_url('modules/customers/view.php?id=' . $custId), 'Please select a valid document file.', 'danger');
}

$allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['doc_file']['tmp_name']);

if (!in_array($mime, $allowedMimes, true) || $_FILES['doc_file']['size'] > 5 * 1024 * 1024) {
    redirect_with_message(base_url('modules/customers/view.php?id=' . $custId), 'Invalid file format or file size exceeds 5MB.', 'danger');
}

$ext = match($mime) {
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    default           => 'pdf'
};

$cleanOriginalName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['doc_file']['name']);
$savedFileName = 'doc_' . $custId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetDir = UPLOADS_PATH . '/documents';

if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}

$targetPath = $targetDir . '/' . $savedFileName;
$relativeDbPath = 'uploads/documents/' . $savedFileName;

if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $targetPath)) {
    redirect_with_message(base_url('modules/customers/view.php?id=' . $custId), 'Failed to save document file on server.', 'danger');
}

$db = get_db_connection();
$stmt = $db->prepare("
    INSERT INTO `customer_documents` (`customer_id`, `doc_type`, `file_path`, `file_name`)
    VALUES (:cid, :dtype, :fpath, :fname)
");
$stmt->execute([
    'cid'   => $custId,
    'dtype' => $docType,
    'fpath' => $relativeDbPath,
    'fname' => $cleanOriginalName
]);

log_audit('CUSTOMER_DOC_UPLOADED', 'customers', $custId, null, ['doc_type' => $docType, 'file' => $cleanOriginalName]);
redirect_with_message(base_url('modules/customers/view.php?id=' . $custId), 'KYC document uploaded successfully!', 'success');
