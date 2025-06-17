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
 * Una serie de facturación o contabilidad, para agrupar documentos de compra y/o venta
 * y para tener distinta numeración en cada serie.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class serie extends \fs_model
{
    public $codserie;
    public $descripcion;
    public $siniva;
    public $irpf;
    public $codcuenta;
    public $idcuenta;
    public $codejercicio;
    public $numfactura;

    public function __construct($data = FALSE)
    {
        parent::__construct('series');
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
        if (is_null($this->codserie)) {
            return "index.php?page=admin_series";
        } else {
            return "index.php?page=admin_series&cod=" . $this->codserie;
        }
    }

    public function get($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codserie = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new \serie($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codserie)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codserie = " . $this->var2str($this->codserie) . ";");
        }
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);
        $this->codcuenta = $this->no_html($this->codcuenta);
        $this->codejercicio = $this->no_html($this->codejercicio);

        if (!preg_match("/^[A-Z0-9]{1,2}$/i", $this->codserie)) {
            $this->new_error_msg("Código de serie no válido.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET descripcion = " . $this->var2str($this->descripcion) .
                    ", siniva = " . $this->var2str($this->siniva) .
                    ", irpf = " . $this->var2str($this->irpf) .
                    ", codcuenta = " . $this->var2str($this->codcuenta) .
                    ", idcuenta = " . $this->var2str($this->idcuenta) .
                    ", codejercicio = " . $this->var2str($this->codejercicio) .
                    ", numfactura = " . $this->var2str($this->numfactura) .
                    " WHERE codserie = " . $this->var2str($this->codserie) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codserie,descripcion,siniva,irpf," .
                    "codcuenta,idcuenta,codejercicio,numfactura) VALUES (" .
                    $this->var2str($this->codserie) . "," .
                    $this->var2str($this->descripcion) . "," .
                    $this->var2str($this->siniva) . "," .
                    $this->var2str($this->irpf) . "," .
                    $this->var2str($this->codcuenta) . "," .
                    $this->var2str($this->idcuenta) . "," .
                    $this->var2str($this->codejercicio) . "," .
                    $this->var2str($this->numfactura) . ");";
                if ($this->db->exec($sql)) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            }
        } else {
            return FALSE;
        }
    }

    public function delete()
    {
        $sql = "DELETE FROM " . $this->table_name . " WHERE codserie = " . $this->var2str($this->codserie) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $serielist = array();
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY codserie ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $s) {
                $serielist[] = new \serie($s);
            }
        }
        return $serielist;
    }

    private function clear()
    {
        $this->codserie = NULL;
        $this->descripcion = '';
        $this->siniva = FALSE;
        $this->irpf = 0;
        $this->codcuenta = '';
        $this->idcuenta = NULL;
        $this->codejercicio = '';
        $this->numfactura = 1;
    }
}
