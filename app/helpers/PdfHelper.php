<?php
/**
 * Helper de Generación de Documentos PDF con Dompdf
 * Sistema de Gestión de Citas Médicas - Clínica Vida Salud
 * Compatible con entornos Docker y Render (bajo consumo de memoria)
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfHelper
{
    /**
     * Instancia y configura el motor Dompdf con opciones optimizadas para Render
     */
    public static function crearInstancia(array $opcionesPersonalizadas = []): Dompdf
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', realpath(__DIR__ . '/../../'));

        foreach ($opcionesPersonalizadas as $clave => $valor) {
            $options->set($clave, $valor);
        }

        return new Dompdf($options);
    }

    /**
     * Renderiza un HTML completo y lo emite al navegador (stream)
     *
     * @param string $html Contenido HTML completo con estilos
     * @param string $nombreArchivo Nombre que tendrá el archivo generado
     * @param bool $descargar true para forzar descarga (attachment), false para inline (impresión / preview)
     * @param string $tamanioHoja 'A4', 'letter', etc.
     * @param string $orientacion 'portrait' o 'landscape'
     */
    public static function streamHtml(
        string $html,
        string $nombreArchivo = 'documento.pdf',
        bool $descargar = false,
        string $tamanioHoja = 'A4',
        string $orientacion = 'portrait'
    ): void {
        $dompdf = self::crearInstancia();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($tamanioHoja, $orientacion);
        $dompdf->render();

        // Limpiar buffers de salida previos para evitar PDFs corruptos
        if (ob_get_length()) {
            ob_end_clean();
        }

        $dompdf->stream($nombreArchivo, [
            'Attachment' => $descargar ? 1 : 0
        ]);
        exit;
    }

    /**
     * Devuelve los estilos CSS base para reportes y documentos médicos institucionales
     */
    public static function getEstilosBase(): string
    {
        return '
        <style>
            @page {
                margin: 18mm 14mm 18mm 14mm;
            }
            body {
                font-family: "DejaVu Sans", Arial, sans-serif;
                font-size: 11px;
                color: #1e293b;
                line-height: 1.45;
                background-color: #ffffff;
                margin: 0;
                padding: 0;
            }
            .header-table {
                width: 100%;
                border-collapse: collapse;
                border-bottom: 2px solid #0284c7;
                padding-bottom: 12px;
                margin-bottom: 18px;
            }
            .header-table td {
                vertical-align: middle;
            }
            .clinic-brand {
                font-size: 20px;
                font-weight: bold;
                color: #0369a1;
                margin: 0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .clinic-tagline {
                font-size: 9px;
                color: #64748b;
                margin: 2px 0 0 0;
            }
            .doc-title-container {
                text-align: right;
            }
            .doc-title {
                font-size: 14px;
                font-weight: bold;
                color: #0f172a;
                margin: 0;
                text-transform: uppercase;
            }
            .doc-meta {
                font-size: 8.5px;
                color: #64748b;
                margin-top: 3px;
            }
            .section-title {
                font-size: 11.5px;
                font-weight: bold;
                color: #0369a1;
                background-color: #f0f9ff;
                border-left: 4px solid #0284c7;
                padding: 5px 8px;
                margin: 14px 0 8px 0;
                text-transform: uppercase;
            }
            .info-grid {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
            }
            .info-grid td {
                padding: 4px 6px;
                vertical-align: top;
                font-size: 10px;
            }
            .label {
                font-weight: bold;
                color: #475569;
                width: 25%;
            }
            .value {
                color: #0f172a;
                width: 75%;
            }
            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 8px;
                margin-bottom: 14px;
            }
            .data-table th {
                background-color: #0284c7;
                color: #ffffff;
                font-weight: bold;
                font-size: 9.5px;
                text-align: left;
                padding: 6px 8px;
                border: 1px solid #0284c7;
                text-transform: uppercase;
            }
            .data-table td {
                font-size: 9.5px;
                padding: 5px 8px;
                border: 1px solid #e2e8f0;
            }
            .data-table tr:nth-child(even) td {
                background-color: #f8fafc;
            }
            .badge {
                display: inline-block;
                padding: 2px 6px;
                font-size: 8.5px;
                font-weight: bold;
                border-radius: 3px;
                text-align: center;
            }
            .badge-primary { background-color: #e0f2fe; color: #0369a1; }
            .badge-success { background-color: #dcfce7; color: #15803d; }
            .badge-warning { background-color: #fef3c7; color: #b45309; }
            .badge-danger  { background-color: #fee2e2; color: #b91c1c; }
            .badge-secondary { background-color: #f1f5f9; color: #475569; }
            .box-destacado {
                background-color: #f8fafc;
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                padding: 8px 10px;
                margin-bottom: 10px;
            }
            .box-alerta {
                background-color: #fffbeb;
                border: 1px solid #fcd34d;
                border-radius: 4px;
                padding: 8px 10px;
                margin-bottom: 10px;
            }
            .footer-legal {
                margin-top: 25px;
                padding-top: 8px;
                border-top: 1px solid #e2e8f0;
                font-size: 8px;
                color: #94a3b8;
                text-align: center;
            }
            .signature-area {
                margin-top: 40px;
                width: 100%;
            }
            .signature-box {
                width: 45%;
                text-align: center;
                border-top: 1px solid #0f172a;
                padding-top: 6px;
                font-size: 9.5px;
                color: #334155;
            }
        </style>
        ';
    }
}

