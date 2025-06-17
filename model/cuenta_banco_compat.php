<?php
/**
 * Archivo de compatibilidad para la clase cuenta_banco sin namespace
 */

if (!class_exists('cuenta_banco', false)) {
    if (!class_exists('FacturaScripts\\model\\cuenta_banco')) {
        require_once __DIR__ . '/cuenta_banco.php';
    }

    class cuenta_banco extends \FacturaScripts\model\cuenta_banco
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }

        public function get($cod)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codcuenta = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new cuenta_banco($data[0]);
            } else {
                return FALSE;
            }
        }

        public function all()
        {
            $cuentalist = array();
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY descripcion ASC;";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $c) {
                    $cuentalist[] = new cuenta_banco($c);
                }
            }
            return $cuentalist;
        }

        /**
         * Devuelve todas las cuentas bancarias de la empresa
         * Método requerido por facturacion_base
         */
        public function all_from_empresa()
        {
            return $this->all();
        }
    }
} 