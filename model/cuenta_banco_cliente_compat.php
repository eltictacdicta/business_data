<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2018 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Archivo de compatibilidad para la clase cuenta_banco_cliente sin namespace
 */
if (!class_exists('cuenta_banco_cliente', false)) {
    if (!class_exists('FacturaScripts\\model\\cuenta_banco_cliente')) {
        require_once __DIR__ . '/cuenta_banco_cliente.php';
    }

    class cuenta_banco_cliente extends \FacturaScripts\model\cuenta_banco_cliente
    {
        public function __construct($data = FALSE)
        {
            parent::__construct($data);
        }

        public function get($cod)
        {
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codcuenta = " . $this->var2str($cod) . ";");
            if ($data) {
                return new cuenta_banco_cliente($data[0]);
            }

            return FALSE;
        }

        public function all_from_cliente($codcli)
        {
            $clist = array();
            $sql = "SELECT * FROM " . $this->table_name . " WHERE codcliente = " . $this->var2str($codcli)
                . " ORDER BY codcuenta DESC;";

            $data = $this->db->select($sql);
            if ($data) {
                foreach ($data as $d) {
                    $clist[] = new cuenta_banco_cliente($d);
                }
            }

            return $clist;
        }
    }
}
