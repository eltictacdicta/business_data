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
 * Un país, por ejemplo España.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class pais extends \fs_model
{
    public $codpais;
    public $nombre;
    public $codiso;

    public function __construct($data = FALSE)
    {
        parent::__construct('paises');
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
        if (is_null($this->codpais)) {
            return "index.php?page=admin_paises";
        } else {
            return "index.php?page=admin_paises&cod=" . $this->codpais;
        }
    }

    public function get($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codpais = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new \pais($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codpais)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codpais = " . $this->var2str($this->codpais) . ";");
        }
    }

    public function test()
    {
        $this->nombre = $this->no_html($this->nombre);
        $this->codiso = $this->no_html($this->codiso);

        if (!preg_match("/^[A-Z0-9]{1,20}$/i", $this->codpais)) {
            $this->new_error_msg("Código de país no válido.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET nombre = " . $this->var2str($this->nombre) .
                    ", codiso = " . $this->var2str($this->codiso) .
                    " WHERE codpais = " . $this->var2str($this->codpais) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codpais,nombre,codiso) VALUES (" .
                    $this->var2str($this->codpais) . "," .
                    $this->var2str($this->nombre) . "," .
                    $this->var2str($this->codiso) . ");";
                return $this->db->exec($sql);
            }
        } else {
            return FALSE;
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE codpais = " . $this->var2str($this->codpais) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $paislist = array();
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY nombre ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $p) {
                $paislist[] = new \pais($p);
            }
        }
        return $paislist;
    }

    private function clear()
    {
        $this->codpais = NULL;
        $this->nombre = '';
        $this->codiso = '';
    }
}
