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

        /**
         * Override del método get para devolver instancia sin namespace
         */
        public function get($cod)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE coddivisa = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new divisa($data[0]);
            } else {
                return FALSE;
            }
        }

        /**
         * Override del método all para devolver instancias sin namespace
         */
        public function all()
        {
            $divisalist = array();
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY descripcion ASC;";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $d) {
                    $divisalist[] = new divisa($d);
                }
            }
            return $divisalist;
        }
    }
} 