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

        /**
         * Devuelve el ejercicio con codejercicio = $cod
         * @param string $cod
         * @return boolean|ejercicio
         */
        public function get($cod)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($cod) . ";";
            $data = $this->db->select($sql);
            if ($data) {
                return new ejercicio($data[0]);
            }
            return FALSE;
        }

        /**
         * Devuelve el ejercicio para la fecha indicada.
         * Si no existe, lo crea.
         * @param string $fecha
         * @param boolean $solo_abierto
         * @param boolean $crear
         * @return boolean|ejercicio
         */
        public function get_by_fecha($fecha, $solo_abierto = TRUE, $crear = TRUE)
        {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE fechainicio <= "
                . $this->var2str($fecha) . " AND fechafin >= " . $this->var2str($fecha) . ";";

            $data = $this->db->select($sql);
            if ($data) {
                $eje = new ejercicio($data[0]);
                if ($eje->abierto() || !$solo_abierto) {
                    return $eje;
                }

                return FALSE;
            } else if ($crear) {
                $eje = new ejercicio();
                $eje->codejercicio = $eje->get_new_codigo(Date('Y', strtotime($fecha)));
                $eje->nombre = Date('Y', strtotime($fecha));
                $eje->fechainicio = Date('1-1-Y', strtotime($fecha));
                $eje->fechafin = Date('31-12-Y', strtotime($fecha));

                if (strtotime($fecha) < 1) {
                    $this->new_error_msg("Fecha no válida: " . $fecha);
                } else if ($eje->save()) {
                    return $eje;
                }
            }

            return FALSE;
        }

        /**
         * Devuelve un array con todos los ejercicios
         * @return ejercicio[]
         */
        public function all()
        {
            $listae = $this->cache->get_array('m_ejercicio_all');
            if (empty($listae)) {
                $data = $this->db->select("SELECT * FROM " . $this->table_name . " ORDER BY fechainicio DESC;");
                if ($data) {
                    foreach ($data as $e) {
                        $listae[] = new ejercicio($e);
                    }
                }
                $this->cache->set('m_ejercicio_all', $listae);
            }
            return $listae;
        }

        /**
         * Devuelve un array con todos los ejercicio abiertos
         * @return ejercicio[]
         */
        public function all_abiertos()
        {
            $listae = $this->cache->get_array('m_ejercicio_all_abiertos');
            if (empty($listae)) {
                $sql = "SELECT * FROM " . $this->table_name . " WHERE estado = 'ABIERTO' ORDER BY codejercicio DESC;";
                $data = $this->db->select($sql);
                if ($data) {
                    foreach ($data as $e) {
                        $listae[] = new ejercicio($e);
                    }
                }
                $this->cache->set('m_ejercicio_all_abiertos', $listae);
            }
            return $listae;
        }
    }
}
