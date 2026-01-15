<?php
/**
 * Archivo de compatibilidad para la clase divisa sin namespace
 * Este archivo permite que plugins como facturacion_base usen new divisa()
 * sin conflictos de namespace
 */

// Solo crear la clase si no existe ya
if (!class_exists('divisa', false)) {
    // Asegurar que la clase con namespace esté cargada
    if (!class_exists('FacturaScripts\\model\\divisa')) {
        require_once __DIR__ . '/divisa.php';
    }

    /**
     * Clase wrapper para divisa sin namespace
     * Permite usar new divisa() en lugar de new \FacturaScripts\model\divisa()
     */
    class divisa extends \FacturaScripts\model\divisa
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }


    }
}