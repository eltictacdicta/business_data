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
 * Ejercicio contable. Es el periodo en el que se agrupan asientos, facturas, albaranes...
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class ejercicio extends \fs_model
{
    public $codejercicio;
    public $nombre;
    public $fechainicio;
    public $fechafin;
    public $estado;
    public $longsubcuenta;
    public $plancontable;

    public function __construct($data = FALSE)
    {
        parent::__construct('ejercicios');
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
        if (is_null($this->codejercicio)) {
            return "index.php?page=admin_ejercicios";
        } else {
            return "index.php?page=admin_ejercicios&cod=" . $this->codejercicio;
        }
    }

    public function get($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new \ejercicio($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codejercicio)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($this->codejercicio) . ";");
        }
    }

    public function test()
    {
        $this->nombre = $this->no_html($this->nombre);
        $this->plancontable = $this->no_html($this->plancontable);

        if (!preg_match("/^[A-Z0-9]{1,4}$/i", $this->codejercicio)) {
            $this->new_error_msg("Código de ejercicio no válido.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET nombre = " . $this->var2str($this->nombre) .
                    ", fechainicio = " . $this->var2str($this->fechainicio) .
                    ", fechafin = " . $this->var2str($this->fechafin) .
                    ", estado = " . $this->var2str($this->estado) .
                    ", longsubcuenta = " . $this->var2str($this->longsubcuenta) .
                    ", plancontable = " . $this->var2str($this->plancontable) .
                    " WHERE codejercicio = " . $this->var2str($this->codejercicio) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codejercicio,nombre,fechainicio,fechafin,estado,longsubcuenta,plancontable) VALUES (" .
                    $this->var2str($this->codejercicio) . "," .
                    $this->var2str($this->nombre) . "," .
                    $this->var2str($this->fechainicio) . "," .
                    $this->var2str($this->fechafin) . "," .
                    $this->var2str($this->estado) . "," .
                    $this->var2str($this->longsubcuenta) . "," .
                    $this->var2str($this->plancontable) . ");";
                return $this->db->exec($sql);
            }
        } else {
            return FALSE;
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($this->codejercicio) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $ejerciciolist = array();
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY fechainicio DESC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $e) {
                $ejerciciolist[] = new \ejercicio($e);
            }
        }
        return $ejerciciolist;
    }

    private function clear()
    {
        $this->codejercicio = NULL;
        $this->nombre = '';
        $this->fechainicio = date('01-01-Y');
        $this->fechafin = date('31-12-Y');
        $this->estado = 'ABIERTO';
        $this->longsubcuenta = 10;
        $this->plancontable = '';
    }
}
