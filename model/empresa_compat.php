<?php
/**
 * Archivo de compatibilidad para la clase empresa sin namespace
 */

if (!class_exists('empresa', false)) {
    if (!class_exists('FacturaScripts\\model\\empresa')) {
        require_once __DIR__ . '/empresa.php';
    }

    class empresa extends \FacturaScripts\model\empresa
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }

        public function get($id = NULL)
        {
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY id ASC;";
            $data = $this->db->select($sql);
            if ($data) {
                return new empresa($data[0]);
            } else {
                return FALSE;
            }
        }
    }
} 