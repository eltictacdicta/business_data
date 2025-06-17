<?php
/**
 * Archivo de compatibilidad para la clase almacen sin namespace
 * Este archivo permite que plugins como facturacion_base usen new almacen()
 * sin conflictos de namespace
 */

// Solo crear la clase si no existe ya
if (!class_exists('almacen', false)) {
    // Asegurar que la clase con namespace esté cargada
    if (!class_exists('FacturaScripts\\model\\almacen')) {
        require_once __DIR__ . '/almacen.php';
    }

    /**
     * Clase wrapper para almacen sin namespace
     * Permite usar new almacen() en lugar de new \FacturaScripts\model\almacen()
     */
    class almacen extends \FacturaScripts\model\almacen
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
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codalmacen = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new almacen($data[0]);
            } else {
                return FALSE;
            }
        }

        /**
         * Override del método all para devolver instancias sin namespace
         */
        public function all()
        {
            $almacenlist = array();
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY nombre ASC;";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $a) {
                    $almacenlist[] = new almacen($a);
                }
            }
            return $almacenlist;
        }
    }
} 