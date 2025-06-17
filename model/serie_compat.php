<?php
/**
 * Archivo de compatibilidad para la clase serie sin namespace
 */

if (!class_exists('serie', false)) {
    if (!class_exists('FacturaScripts\\model\\serie')) {
        require_once __DIR__ . '/serie.php';
    }

    class serie extends \FacturaScripts\model\serie
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }

        public function get($cod)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codserie = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new serie($data[0]);
            } else {
                return FALSE;
            }
        }

        public function all()
        {
            $serielist = array();
            $sql = "SELECT * FROM " . $this->table_name . " ORDER BY descripcion ASC;";
            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $s) {
                    $serielist[] = new serie($s);
                }
            }
            return $serielist;
        }
    }
} 