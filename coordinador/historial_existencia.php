<?php
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador' && $rolNombre !== 'coordinacion') {
    header('Location: ../login.php');
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');

$photoPath = null;
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = __DIR__ . '/../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
    if (file_exists($candidate)) {
        $photoPath = '../uploads/profiles/' . intval($_SESSION['usuario_id']) . '.' . $ext;
        break;
    }
}

// Obtener certificados de existencia
try {
    $sql = "SELECT ce.*, lr.LOTE_NOMBRE, lr.ID_SOLICITANTE, u.NOMBRE, u.APELLIDO
            FROM certificado_existencia ce
            INNER JOIN lote_requerimiento lr ON ce.ID_LOTE = lr.ID_LOTE
            INNER JOIN usuario u ON lr.ID_SOLICITANTE = u.ID_USUARIO
            ORDER BY ce.ID_CERTIFICADO DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $certificados = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Error fetching certificados: ' . $e->getMessage());
    $certificados = [];
}

$total = count($certificados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificados de Existencia - BICERGAM</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>

<header class="dashboard-header">
    <div class="header-brand" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" style="height:36px; width:auto;">
        <a href="index.php" class="btn-inicio-nav">Inicio</a>
    </div>
    <div class="header-user">
        <div class="header-user-text">
            Bienvenido: <strong><?= $usuarioNombre ?></strong>
            <span class="header-user-role">(Coordinador)</span>
        </div>
        <a href="coordinador_profile.php" class="header-avatar-link" title="Editar perfil">
            <?php if ($photoPath): ?>
                <img src="<?= htmlspecialchars($photoPath) ?>" alt="Foto perfil" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar"><?= strtoupper(substr($usuarioNombre, 0, 1)) ?></div>
            <?php endif; ?>
        </a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center;"><img src="../imagenes/sena-logo.png" alt="SENA"></a>
        </div>
        <div class="sidebar-group">
            <h4>Gestión de Lotes</h4>
            <a href="index.php" class="sidebar-link">Inicio (HUD)</a>
            <a href="revisar_lotes.php" class="sidebar-link">Revisar Lotes</a>
            <a href="historial_decisiones.php" class="sidebar-link">Historial Decisiones</a>
        </div>
        <div class="sidebar-group">
            <h4>Consultas</h4>
            <a href="instructores.php" class="sidebar-link">Instructores</a>
            <a href="fichas_tecnicas_coordinador.php" class="sidebar-link">Fichas Técnicas</a>
            <a href="historial_existencia.php" class="sidebar-link sidebar-link--primary active">Certificados Existencia</a>
        </div>
        <div class="sidebar-group sidebar-group--session">
            <h4>Sesión</h4>
            <a href="coordinador_profile.php" class="sidebar-link">Editar Perfil</a>
            <a href="../logout.php" class="sidebar-link sidebar-link--logout">Cerrar Sesión</a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <div>
                <h2>Certificados de Existencia</h2>
                <p class="dashboard-subtitle">Historial de certificados de existencia emitidos para lotes.</p>
            </div>
        </div>

        <div class="panel-card">
            <div style="margin-bottom: 20px;">
                <h3>Total de Certificados: <?= $total ?></h3>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr>
                        <th>ID Certificado</th>
                        <th>ID Lote</th>
                        <th>Nombre Lote</th>
                        <th>Instructor</th>
                        <th>Número Certificado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($certificados)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">No hay certificados de existencia registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($certificados as $cert): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?= htmlspecialchars($cert['ID_CERTIFICADO']) ?></td>
                                <td style="padding: 12px;">
                                    <a href="revisar_lote.php?id=<?= htmlspecialchars($cert['ID_LOTE']) ?>" style="color: #39A900; text-decoration: none;">
                                        <?= htmlspecialchars($cert['ID_LOTE']) ?>
                                    </a>
                                </td>
                                <td style="padding: 12px;"><?= htmlspecialchars($cert['LOTE_NOMBRE']) ?></td>
                                <td style="padding: 12px;"><?= htmlspecialchars($cert['NOMBRE'] . ' ' . $cert['APELLIDO']) ?></td>
                                <td style="padding: 12px;"><?= htmlspecialchars($cert['NUMERO_CERTIFICADO']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>
