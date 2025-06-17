<?php
/**
 * Archivo de compatibilidad para la clase forma_pago sin namespace
 */

if (!class_exists('forma_pago', false)) {
    if (!class_exists('FacturaScripts\\model\\forma_pago')) {
        require_once __DIR__ . '/forma_pago.php';
    }

    class forma_pago extends \FacturaScripts\model\forma_pago
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }

        public function get($cod)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codpago = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new forma_pago($data[0]);
            } else {
                return FALSE;
            }
        }

        public function all()
        {
            $formalist = array();
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY descripcion ASC;";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $f) {
                    $formalist[] = new forma_pago($f);
                }
            }
            return $formalist;
        }
    }
} 