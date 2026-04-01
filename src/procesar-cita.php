<?php
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Obtener datos del formulario
    
    // Habilitar que mysqli lance excepciones en errores (mejor manejo)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $nombre   = $_POST['nombre'] ?? '';
    $email    = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $medico   = $_POST['medico_id'] ?? '';
    $fecha    = $_POST['fecha'] ?? '';
    $hora     = $_POST['hora'] ?? '';
    $motivo   = $_POST['motivo'] ?? '';

    // Validación básica
    if (empty($nombre) || empty($email) || empty($telefono) || empty($medico) || empty($fecha) || empty($hora)) {
        header("Location: agendar-cita.php?mensaje=❌ Todos los campos son obligatorios");
        exit();
    }

    try {
        // Iniciar transacción para mantener consistencia
        $conn->begin_transaction();

        // --- Manejo del expediente (si se enviaron datos) ---
        $expediente_id = null;
        $exp_paciente_email = $email;
        $exp_fecha_nacimiento = $_POST['exp_fecha_nacimiento'] ?? null;
        $exp_sexo = $_POST['exp_sexo'] ?? null;
        $exp_direccion = $_POST['exp_direccion'] ?? null;
        $exp_alergias = $_POST['exp_alergias'] ?? null;
        $exp_antecedentes = $_POST['exp_antecedentes'] ?? null;
        $exp_medicamentos = $_POST['exp_medicamentos'] ?? null;
        $exp_peso = $_POST['exp_peso'] ?? null;
        $exp_altura = $_POST['exp_altura'] ?? null;
        $exp_notas = $_POST['exp_notas'] ?? null;

        $hasExpedienteData = ($exp_fecha_nacimiento || $exp_sexo || $exp_direccion || $exp_alergias || $exp_antecedentes || $exp_medicamentos || $exp_peso || $exp_altura || $exp_notas);

        if ($hasExpedienteData) {
            // Verificar si ya existe expediente para este email
            $q = $conn->prepare("SELECT id FROM expedientes WHERE paciente_email = ? LIMIT 1");
            $q->bind_param("s", $exp_paciente_email);
            $q->execute();
            $res = $q->get_result();

            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $expediente_id = $row['id'];
                // Actualizar expediente
                $up = $conn->prepare("UPDATE expedientes SET nombre = ?, telefono = ?, fecha_nacimiento = ?, sexo = ?, direccion = ?, alergias = ?, antecedentes = ?, medicamentos_actuales = ?, peso = ?, altura = ?, notas = ? WHERE id = ?");
                $up->bind_param("ssssssssssi", $nombre, $telefono, $exp_fecha_nacimiento, $exp_sexo, $exp_direccion, $exp_alergias, $exp_antecedentes, $exp_medicamentos, $exp_peso, $exp_altura, $exp_notas, $expediente_id);
                $up->execute();
            } else {
                // Insertar nuevo expediente
                $ins = $conn->prepare("INSERT INTO expedientes (paciente_email, nombre, telefono, fecha_nacimiento, sexo, direccion, alergias, antecedentes, medicamentos_actuales, peso, altura, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("ssssssssssss", $exp_paciente_email, $nombre, $telefono, $exp_fecha_nacimiento, $exp_sexo, $exp_direccion, $exp_alergias, $exp_antecedentes, $exp_medicamentos, $exp_peso, $exp_altura, $exp_notas);
                $ins->execute();
                $expediente_id = $conn->insert_id;
            }
        }

        // 🔥 Evitar citas duplicadas (mismo médico, fecha y hora)
        $check = $conn->prepare("SELECT id FROM citas WHERE medico_id = ? AND fecha = ? AND hora = ?");
        $check->bind_param("iss", $medico, $fecha, $hora);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            header("Location: agendar-cita.php?mensaje=⚠️ Ese horario ya está ocupado");
            exit();
        }

        // Insertar cita (vinculando expediente si aplica)
        if ($expediente_id) {
            $stmt = $conn->prepare("INSERT INTO citas 
                (paciente_nombre, paciente_email, paciente_telefono, medico_id, fecha, hora, motivo, expediente_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisssi", $nombre, $email, $telefono, $medico, $fecha, $hora, $motivo, $expediente_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO citas 
                (paciente_nombre, paciente_email, paciente_telefono, medico_id, fecha, hora, motivo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisss", $nombre, $email, $telefono, $medico, $fecha, $hora, $motivo);
        }

        $stmt->execute();
        $cita_id = $conn->insert_id;

        // --- Generar PDFs (si está disponible Dompdf) ---
        $exportsDir = __DIR__ . '/exports';
        if (!is_dir($exportsDir)) {
            mkdir($exportsDir, 0755, true);
        }

        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            try {
                // Generar PDF de expediente
                if ($expediente_id) {
                    $expQ = $conn->prepare("SELECT * FROM expedientes WHERE id = ? LIMIT 1");
                    $expQ->bind_param("i", $expediente_id);
                    $expQ->execute();
                    $expData = $expQ->get_result()->fetch_assoc();

                    $htmlExp = '<h1>Expediente Clínico</h1>';
                    $htmlExp .= '<p><strong>Nombre:</strong> '.htmlspecialchars($expData['nombre']).'</p>';
                    $htmlExp .= '<p><strong>Email:</strong> '.htmlspecialchars($expData['paciente_email']).'</p>';
                    $htmlExp .= '<p><strong>Teléfono:</strong> '.htmlspecialchars($expData['telefono']).'</p>';
                    $htmlExp .= '<p><strong>Fecha Nac.:</strong> '.htmlspecialchars($expData['fecha_nacimiento']).'</p>';
                    $htmlExp .= '<p><strong>Sexo:</strong> '.htmlspecialchars($expData['sexo']).'</p>';
                    $htmlExp .= '<p><strong>Alergias:</strong><br>'.nl2br(htmlspecialchars($expData['alergias'])).'</p>';
                    $htmlExp .= '<p><strong>Antecedentes:</strong><br>'.nl2br(htmlspecialchars($expData['antecedentes'])).'</p>';
                    $htmlExp .= '<p><strong>Medicamentos actuales:</strong><br>'.nl2br(htmlspecialchars($expData['medicamentos_actuales'])).'</p>';
                    $htmlExp .= '<p><strong>Notas:</strong><br>'.nl2br(htmlspecialchars($expData['notas'])).'</p>';

                    $pdfFileExp = $exportsDir . '/expediente_' . $expediente_id . '.pdf';
                    $dompdf = new Dompdf\Dompdf();
                    $dompdf->loadHtml($htmlExp);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    file_put_contents($pdfFileExp, $dompdf->output());
                }

                // Generar PDF de la cita
                $citaQ = $conn->prepare("SELECT c.*, m.nombre as medico_nombre, m.especialidad FROM citas c LEFT JOIN medicos m ON c.medico_id = m.id WHERE c.id = ? LIMIT 1");
                $citaQ->bind_param("i", $cita_id);
                $citaQ->execute();
                $citaData = $citaQ->get_result()->fetch_assoc();

                $htmlCita = '<h1>Comprobante de Cita</h1>';
                $htmlCita .= '<p><strong>Paciente:</strong> '.htmlspecialchars($citaData['paciente_nombre']).'</p>';
                $htmlCita .= '<p><strong>Email:</strong> '.htmlspecialchars($citaData['paciente_email']).'</p>';
                $htmlCita .= '<p><strong>Teléfono:</strong> '.htmlspecialchars($citaData['paciente_telefono']).'</p>';
                $htmlCita .= '<p><strong>Médico:</strong> '.htmlspecialchars($citaData['medico_nombre']).' ('.htmlspecialchars($citaData['especialidad']).')</p>';
                $htmlCita .= '<p><strong>Fecha:</strong> '.htmlspecialchars($citaData['fecha']).' <strong>Hora:</strong> '.htmlspecialchars($citaData['hora']).'</p>';
                $htmlCita .= '<p><strong>Motivo:</strong><br>'.nl2br(htmlspecialchars($citaData['motivo'])).'</p>';

                $pdfFileCita = $exportsDir . '/cita_' . $cita_id . '.pdf';
                $dompdf2 = new Dompdf\Dompdf();
                $dompdf2->loadHtml($htmlCita);
                $dompdf2->setPaper('A4', 'portrait');
                $dompdf2->render();
                file_put_contents($pdfFileCita, $dompdf2->output());

            } catch (Exception $ex) {
                // no bloquear el flujo si falla la generación de PDFs
                error_log('Error generando PDF: ' . $ex->getMessage());
            }
        }

        $conn->commit();

        header("Location: mis-citas.php?mensaje=✅ Cita agendada correctamente");
        exit();

    } catch (Exception $e) {
        // Intentar rollback si la transacción está activa
        if ($conn && $conn->errno) {
            $conn->rollback();
        }
        error_log('procesar-cita.php - Exception: ' . $e->getMessage());
        header("Location: agendar-cita.php?mensaje=Error al guardar cita");
        exit();
    }
}
?>
