<?php
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$rolNombre = strtolower(trim($_SESSION['rol_nombre'] ?? ''));
if ($rolNombre !== 'coordinador') {
    header('Location: ../login.php');
    exit;
}

$usuarioNombre = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BICERGAM - Coordinador</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
<header class="header-main">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <img src="../imagenes/sena-logo.png" alt="SENA" class="sena-logo-img">
        <div>
            <h1 class="header-title">Panel Coordinador</h1>
            <div class="user-greeting">Bienvenido: <strong><?= $usuarioNombre ?></strong> <span class="role-badge">(Coordinador)</span></div>
        </div>
    </div>
    <div class="header-right">
        <a href="coordinador_profile.php" class="btn btn-secondary" style="margin-right: 10px;">Mi Perfil</a>
        <a href="../logout.php" class="btn btn-logout">Cerrar Sesión</a>
    </div>
</header>

<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-logo">
            <img src="../imagenes/sena-logo.png" alt="SENA">
        </div>
        <div class="sidebar-group">
            <h4>Coordinación</h4>
            <a href="index.php" class="sidebar-link sidebar-link--primary">Inicio</a>
            <a href="#" class="sidebar-link">Gestión de Lotes</a>
            <a href="#" class="sidebar-link">Aprobación de Solicitudes</a>
        </div>
        <div class="sidebar-group">
            <h4>Referencias</h4>
            <a href="#" class="sidebar-link">Fichas Técnicas</a>
            <a href="#" class="sidebar-link">Reportes</a>
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
                <h2>Panel de Coordinador</h2>
                <p class="dashboard-subtitle">Este espacio es la base para el CRUD de coordinador. Mantiene la misma estética que el panel de instructor.</p>
            </div>
        </div>

        <div class="panel-card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <div>
                    <h3>Acciones principales</h3>
                    <p>En esta carpeta se implementará la gestión de coordinadores, instructores, lotes y otros recursos.</p>
                </div>
                <div class="actions-bar" style="border: none; padding: 0; margin: 0;">
                    <a href="#" class="btn btn-sena">Nueva acción</a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Elemento</th>
                        <th>Estado</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Gestión de usuarios</td>
                        <td>Disponible</td>
                        <td>Próxima implementación del CRUD de coordinador.</td>
                    </tr>
                    <tr>
                        <td>Validación de lotes</td>
                        <td>Disponible</td>
                        <td>Espacio preparado para administrar lotes y seguimientos.</td>
                    </tr>
                    <tr>
                        <td>Informes</td>
                        <td>Disponible</td>
                        <td>Se mostrará información relevante para coordinación.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>
