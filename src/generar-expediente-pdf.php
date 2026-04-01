<?php
require_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'ID inválido';
    exit();
}

$id = (int)$_GET['id'];

// Obtener expediente
$q = $conn->prepare('SELECT * FROM expedientes WHERE id = ? LIMIT 1');
$q->bind_param('i', $id);
$q->execute();
$exp = $q->get_result()->fetch_assoc();

if (!$exp) {
    header('HTTP/1.1 404 Not Found');
    echo 'Expediente no encontrado';
    exit();
}

// Generar PDF con Dompdf
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Si Dompdf no está instalado, devolver error
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Dompdf no instalado';
    exit();
}


require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;

$html = '<h1>Expediente Clínico</h1>';
$html .= '<p><strong>ID:</strong> ' . $exp['id'] . '</p>';
$html .= '<p><strong>Nombre:</strong> ' . htmlspecialchars($exp['nombre']) . '</p>';
$html .= '<p><strong>Email:</strong> ' . htmlspecialchars($exp['paciente_email']) . '</p>';
$html .= '<p><strong>Teléfono:</strong> ' . htmlspecialchars($exp['telefono']) . '</p>';
$html .= '<p><strong>Fecha Nac.:</strong> ' . htmlspecialchars($exp['fecha_nacimiento']) . '</p>';
$html .= '<p><strong>Sexo:</strong> ' . htmlspecialchars($exp['sexo']) . '</p>';
$html .= '<p><strong>Alergias:</strong><br>' . nl2br(htmlspecialchars($exp['alergias'])) . '</p>';
$html .= '<p><strong>Antecedentes:</strong><br>' . nl2br(htmlspecialchars($exp['antecedentes'])) . '</p>';
$html .= '<p><strong>Medicamentos actuales:</strong><br>' . nl2br(htmlspecialchars($exp['medicamentos_actuales'])) . '</p>';
$html .= '<p><strong>Notas:</strong><br>' . nl2br(htmlspecialchars($exp['notas'])) . '</p>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$exportsDir = __DIR__ . '/exports';
if (!is_dir($exportsDir)) mkdir($exportsDir, 0755, true);

$filePath = $exportsDir . '/expediente_' . $exp['id'] . '.pdf';
file_put_contents($filePath, $dompdf->output());

// Enviar PDF al navegador para descarga
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="expediente_' . $exp['id'] . '.pdf"');
readfile($filePath);
exit();
