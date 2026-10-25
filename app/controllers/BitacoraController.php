<?php
/**
 * Controlador: BitacoraController (CU-15)
 * Auditoría general del sistema
 */

require_once __DIR__ . '/../models/Bitacora.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class BitacoraController
{
    public function __construct()
    {
        // Solo el Administrador puede consultar bitácoras
        AuthMiddleware::verificarRoles(['Administrador']);
    }

    /**
     * Retorna los registros filtrados
     */
    public function obtenerBitacoras(array $filtros): array
    {
        return Bitacora::listar($filtros, 200);
    }
}
