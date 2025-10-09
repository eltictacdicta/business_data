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
namespace FacturaScripts\model;

/**
 * Forma de pago de una factura.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class forma_pago extends \fs_model
{
    public $codpago;
    public $descripcion;
    public $contado;
    public $plazovencimiento;
    public $domiciliado;
    public $recargo;

    public function __construct($data = FALSE)
    {
        parent::__construct('formaspago');
        if ($data) {
            $this->loadFromData($data);
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
        if (is_null($this->codpago)) {
            return "index.php?page=admin_formas_pago";
        } else {
            return "index.php?page=admin_formas_pago&cod=" . $this->codpago;
        }
    }

    public function get($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codpago = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new \forma_pago($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codpago)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codpago = " . $this->var2str($this->codpago) . ";");
        }
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);

        if (!preg_match("/^[A-Z0-9]{1,10}$/i", $this->codpago)) {
            $this->new_error_msg("Código de forma de pago no válido.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET descripcion = " . $this->var2str($this->descripcion) .
                    ", contado = " . $this->var2str($this->contado) .
                    ", plazovencimiento = " . $this->var2str($this->plazovencimiento) .
                    ", domiciliado = " . $this->var2str($this->domiciliado) .
                    ", recargo = " . $this->var2str($this->recargo) .
                    " WHERE codpago = " . $this->var2str($this->codpago) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codpago,descripcion,contado,plazovencimiento,domiciliado,recargo) VALUES (" .
                    $this->var2str($this->codpago) . "," .
                    $this->var2str($this->descripcion) . "," .
                    $this->var2str($this->contado) . "," .
                    $this->var2str($this->plazovencimiento) . "," .
                    $this->var2str($this->domiciliado) . "," .
                    $this->var2str($this->recargo) . ");";
                return $this->db->exec($sql);
            }
        } else {
            return FALSE;
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE codpago = " . $this->var2str($this->codpago) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $formalist = array();
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY descripcion ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $f) {
                $formalist[] = new \forma_pago($f);
            }
        }
        return $formalist;
    }

    private function clear()
    {
        $this->codpago = NULL;
        $this->descripcion = '';
        $this->contado = TRUE;
        $this->plazovencimiento = 0;
        $this->domiciliado = FALSE;
        $this->recargo = 0;
    }
}
