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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

/**
 * Una divisa (moneda) con su símbolo y su tasa de conversión respecto al euro.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class divisa extends \fs_model
{
    public $coddivisa;
    public $descripcion;
    public $codiso;
    public $simbolo;
    public $tasaconv;
    public $tasaconvcompra;

    public function __construct($data = FALSE)
    {
        parent::__construct('divisas');
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
        if (is_null($this->coddivisa)) {
            return "index.php?page=admin_divisas";
        } else {
            return "index.php?page=admin_divisas&cod=" . $this->coddivisa;
        }
    }

    public function get($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE coddivisa = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new \divisa($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->coddivisa)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE coddivisa = " . $this->var2str($this->coddivisa) . ";");
        }
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);
        $this->codiso = $this->no_html($this->codiso);
        $this->simbolo = $this->no_html($this->simbolo);

        if (!preg_match("/^[A-Z0-9]{1,3}$/i", $this->coddivisa)) {
            $this->new_error_msg("Código de divisa no válido.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET descripcion = " . $this->var2str($this->descripcion) .
                    ", codiso = " . $this->var2str($this->codiso) .
                    ", simbolo = " . $this->var2str($this->simbolo) .
                    ", tasaconv = " . $this->var2str($this->tasaconv) .
                    ", tasaconvcompra = " . $this->var2str($this->tasaconvcompra) .
                    " WHERE coddivisa = " . $this->var2str($this->coddivisa) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (coddivisa,descripcion,codiso,simbolo,tasaconv,tasaconvcompra) VALUES (" .
                    $this->var2str($this->coddivisa) . "," .
                    $this->var2str($this->descripcion) . "," .
                    $this->var2str($this->codiso) . "," .
                    $this->var2str($this->simbolo) . "," .
                    $this->var2str($this->tasaconv) . "," .
                    $this->var2str($this->tasaconvcompra) . ");";
                return $this->db->exec($sql);
            }
        } else {
            return FALSE;
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE coddivisa = " . $this->var2str($this->coddivisa) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $divisalist = array();
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY descripcion ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $divisalist[] = new \divisa($d);
            }
        }
        return $divisalist;
    }

    private function clear()
    {
        $this->coddivisa = NULL;
        $this->descripcion = '';
        $this->codiso = '';
        $this->simbolo = '';
        $this->tasaconv = 1;
        $this->tasaconvcompra = 1;
    }
}
