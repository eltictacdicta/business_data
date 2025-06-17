<?php
/**
 * Archivo de compatibilidad para la clase pais sin namespace
 * Este archivo permite que plugins como facturacion_base usen new pais()
 * sin conflictos de namespace
 */

// Solo crear la clase si no existe ya
if (!class_exists('pais', false)) {
    // Asegurar que la clase con namespace esté cargada
    if (!class_exists('FacturaScripts\\model\\pais')) {
        require_once __DIR__ . '/pais.php';
    }

    /**
     * Clase wrapper para pais sin namespace
     * Permite usar new pais() en lugar de new \FacturaScripts\model\pais()
     */
    class pais extends \FacturaScripts\model\pais
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }

        /**
         * Override del método get para devolver instancia sin namespace
         */
        public function get($cod)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codpais = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new pais($data[0]);
            } else {
                return FALSE;
            }
        }

        /**
         * Override del método all para devolver instancias sin namespace
         */
        public function all()
        {
            $paislist = array();
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY nombre ASC;";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $p) {
                    $paislist[] = new pais($p);
                }
            }
            return $paislist;
        }
    }
} 