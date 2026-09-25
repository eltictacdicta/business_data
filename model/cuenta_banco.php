<?php
/**
 * This file is part of FSFramework originally based on Facturascript 2017
 * Copyright (C) 2025 Javier Trujillo <mistertekcom@gmail.com>
 * Copyright (C) 2013-2020 Carlos Garcia Gomez <neorazorx@gmail.com> (lead developer of Facturascript)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * Una cuenta bancaria de la propia empresa.
 * Clase sin namespace para compatibilidad con facturacion_base.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class cuenta_banco extends fs_model
{
    private const SQL_SELECT_ALL_FROM = 'SELECT * FROM ';
    private const SQL_WHERE = ' WHERE ';
    private const PK_CODCUENTA = 'codcuenta = ';

    public $codcuenta;
    public $descripcion;
    public $iban;
    public $swift;
    public $codsubcuenta;

    public function __construct($data = FALSE)
    {
        parent::__construct('cuentasbanco');
        if ($data) {
            $this->codcuenta = $data['codcuenta'] ?? null;
            $this->descripcion = $data['descripcion'] ?? '';
            $this->iban = $data['iban'] ?? '';
            $this->swift = $data['swift'] ?? '';
            $this->codsubcuenta = $data['codsubcuenta'] ?? null;
        } else {
            $this->clear();
        }
    }

    protected function install()
    {
        return '';
    }

    public function url()
    {
        if (is_null($this->codcuenta)) {
            return "index.php?page=admin_cuentas_banco";
        } else {
            return "index.php?page=admin_cuentas_banco&cod=" . $this->codcuenta;
        }
    }

    public function get($cod)
    {
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . self::PK_CODCUENTA . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new cuenta_banco($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codcuenta)) {
            return FALSE;
        } else {
            return $this->db->select(self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . self::PK_CODCUENTA . $this->var2str($this->codcuenta) . ";");
        }
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);
        $this->iban = $this->no_html($this->iban);
        $this->swift = $this->no_html($this->swift);

        if ($this->codsubcuenta !== null) {
            $this->codsubcuenta = $this->no_html($this->codsubcuenta);
            if ($this->codsubcuenta === '') {
                $this->codsubcuenta = null;
            }
        }

        $codcuenta = (string) ($this->codcuenta ?? '');
        if ($codcuenta === '' || !preg_match('/^[A-Z0-9]{1,6}$/i', $codcuenta)) {
            $this->new_error_msg("Código de cuenta bancaria no válido.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if (!$this->exists() && ($this->codcuenta === null || $this->codcuenta === '')) {
            $this->codcuenta = $this->get_new_codigo();
        }

        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET descripcion = " . $this->var2str($this->descripcion) .
                    ", iban = " . $this->var2str($this->iban) .
                    ", swift = " . $this->var2str($this->swift) .
                    ", codsubcuenta = " . $this->var2str($this->codsubcuenta) .
                    self::SQL_WHERE . self::PK_CODCUENTA . $this->var2str($this->codcuenta) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codcuenta,descripcion,iban,swift,codsubcuenta) VALUES (" .
                    $this->var2str($this->codcuenta) . "," .
                    $this->var2str($this->descripcion) . "," .
                    $this->var2str($this->iban) . "," .
                    $this->var2str($this->swift) . "," .
                    $this->var2str($this->codsubcuenta) . ");";
                return $this->db->exec($sql);
            }
        } else {
            return FALSE;
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . self::SQL_WHERE . self::PK_CODCUENTA . $this->var2str($this->codcuenta) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $cuentalist = array();
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . " ORDER BY descripcion ASC;";
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
     * 
     * @return array Lista de cuentas bancarias
     */
    public function all_from_empresa()
    {
        // En este caso, todas las cuentas banco pertenecen a la empresa
        // Si en el futuro necesitas filtrar por empresa específica, 
        // puedes modificar esta consulta
        return $this->all();
    }

    private function get_new_codigo()
    {
        $sql = 'SELECT MAX(' . $this->db->sql_to_int('codcuenta') . ') as cod FROM ' . $this->table_name . ';';
        $data = $this->db->select($sql);
        if ($data) {
            return (string) (1 + intval($data[0]['cod']));
        }

        return '1';
    }

    private function clear()
    {
        $this->codcuenta = NULL;
        $this->descripcion = '';
        $this->iban = '';
        $this->swift = '';
        $this->codsubcuenta = NULL;
    }
}
