<?php
/**
 * Layout: Header común
 * Sistema de Gestión de Citas Médicas - Clínica Vida Salud
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'Clínica Vida Salud';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema Web para la Gestión y Control de Citas Médicas - Clínica Vida Salud">
    <meta name="author" content="Franco Alvis Jhosmar Kevin - Grupo #26">
    <title><?= htmlspecialchars($pageTitle) ?> | Clínica Vida Salud</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Print.js para impresión directa de documentos y fichas PDF -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.css">

    <!-- Estilos personalizados con control de versión anti-caché -->
    <link rel="stylesheet" href="/clinicaVidaSalud/assets/css/custom.css?v=2.2">
</head>
<body>
<div class="app-wrapper">
<script>
    // Restaurar inmediatamente el estado del sidebar antes del primer renderizado para evitar parpadeos
    if (window.innerWidth >= 768 && localStorage.getItem('clinica_sidebar_collapsed') === 'true') {
        document.querySelector('.app-wrapper')?.classList.add('sidebar-collapsed');
    }
</script>
