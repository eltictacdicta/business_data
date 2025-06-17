<?php
/**
 * Archivo de compatibilidad para la clase ejercicio sin namespace
 */

if (!class_exists('ejercicio', false)) {
    if (!class_exists('FacturaScripts\\model\\ejercicio')) {
        require_once __DIR__ . '/ejercicio.php';
    }

    class ejercicio extends \FacturaScripts\model\ejercicio
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }

        public function get($cod)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new ejercicio($data[0]);
            } else {
                return FALSE;
            }
        }

        public function all()
        {
            $ejerciciolist = array();
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY fechainicio DESC;";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $e) {
                    $ejerciciolist[] = new ejercicio($e);
                }
            }
            return $ejerciciolist;
        }
    }
} 