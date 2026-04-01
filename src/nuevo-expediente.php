<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['paciente_email'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $sexo = $_POST['sexo'] ?? 'O';
    $direccion = $_POST['direccion'] ?? null;
    $alergias = $_POST['alergias'] ?? null;
    $antecedentes = $_POST['antecedentes'] ?? null;
    $medicamentos = $_POST['medicamentos_actuales'] ?? null;
    $peso = $_POST['peso'] ?? null;
    $altura = $_POST['altura'] ?? null;
    $notas = $_POST['notas'] ?? null;

    if (empty($email) || empty($nombre)) {
        $error = 'Email y nombre son obligatorios';
    } else {
        // Insertar o actualizar si existe
        $check = $conn->prepare('SELECT id FROM expedientes WHERE paciente_email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $res = $check->get_result();

        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $id = $row['id'];
            $up = $conn->prepare('UPDATE expedientes SET nombre = ?, telefono = ?, fecha_nacimiento = ?, sexo = ?, direccion = ?, alergias = ?, antecedentes = ?, medicamentos_actuales = ?, peso = ?, altura = ?, notas = ? WHERE id = ?');
            $up->bind_param('sssssssssssi', $nombre, $telefono, $fecha_nacimiento, $sexo, $direccion, $alergias, $antecedentes, $medicamentos, $peso, $altura, $notas, $id);
            $up->execute();
            $success = 'Expediente actualizado';
        } else {
            $ins = $conn->prepare('INSERT INTO expedientes (paciente_email, nombre, telefono, fecha_nacimiento, sexo, direccion, alergias, antecedentes, medicamentos_actuales, peso, altura, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('ssssssssssss', $email, $nombre, $telefono, $fecha_nacimiento, $sexo, $direccion, $alergias, $antecedentes, $medicamentos, $peso, $altura, $notas);
            $ins->execute();
            $success = 'Expediente creado';
        }
        header('Location: expedientes.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Expediente</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <div class="logo"><h1>Nuevo Expediente</h1></div>
            <ul class="nav-menu">
                <li><a href="expedientes.php"><i class="fas fa-arrow-left"></i> Volver</a></li>
            </ul>
        </nav>

        <main>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <form method="POST" action="nuevo-expediente.php">
                <div class="form-group">
                    <label>Email (paciente)</label>
                    <input type="email" name="paciente_email" required>
                </div>
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono">
                </div>
                <div class="form-group">
                    <label>Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento">
                </div>
                <div class="form-group">
                    <label>Sexo</label>
                    <select name="sexo"><option value="O">Otro</option><option value="M">Masculino</option><option value="F">Femenino</option></select>
                </div>
                <div class="form-group">
                    <label>Alergias</label>
                    <textarea name="alergias"></textarea>
                </div>
                <div class="form-group">
                    <label>Antecedentes</label>
                    <textarea name="antecedentes"></textarea>
                </div>
                <div class="form-group">
                    <label>Medicamentos actuales</label>
                    <textarea name="medicamentos_actuales"></textarea>
                </div>
                <div class="form-group">
                    <label>Peso</label>
                    <input type="text" name="peso">
                </div>
                <div class="form-group">
                    <label>Altura</label>
                    <input type="text" name="altura">
                </div>
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notas"></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Guardar Expediente</button>
            </form>
        </main>
    </div>
</body>
</html>
