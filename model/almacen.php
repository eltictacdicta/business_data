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
 * El almacén donde están físicamente los artículos.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class almacen extends \fs_model
{
    /**
     * Clave primaria. Varchar (4).
     * @var string
     */
    public $codalmacen;
    
    /**
     * Nombre del almacén.
     * @var string
     */
    public $nombre;
    
    /**
     * Dirección del almacén.
     * @var string
     */
    public $direccion;
    
    /**
     * Código postal.
     * @var string
     */
    public $codpostal;
    
    /**
     * Población.
     * @var string
     */
    public $poblacion;
    
    /**
     * Provincia.
     * @var string
     */
    public $provincia;
    
    /**
     * Código del país.
     * @var string
     */
    public $codpais;
    
    /**
     * Apartado de correos.
     * @var string
     */
    public $apartado;
    
    /**
     * Teléfono.
     * @var string
     */
    public $telefono;
    
    /**
     * Fax.
     * @var string
     */
    public $fax;
    
    /**
     * Persona de contacto.
     * @var string
     */
    public $contacto;
    
    /**
     * Observaciones.
     * @var string
     */
    public $observaciones;
    
    /**
     * ID de la provincia.
     * @var integer
     */
    public $idprovincia;
    
    /**
     * Porcentaje sobre PVP.
     * @var float
     */
    public $porpvp;
    
    /**
     * Tipo de valoración.
     * @var string
     */
    public $tipovaloracion;

    public function __construct($data = FALSE)
    {
        parent::__construct('almacenes');
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
        if (is_null($this->codalmacen)) {
            return "index.php?page=admin_almacenes";
        } else {
            return "index.php?page=admin_almacenes&cod=" . $this->codalmacen;
        }
    }

    public function get($cod)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codalmacen = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new \almacen($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codalmacen)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codalmacen = " . $this->var2str($this->codalmacen) . ";");
        }
    }

    public function test()
    {
        $this->nombre = $this->no_html($this->nombre);
        $this->direccion = $this->no_html($this->direccion);
        $this->codpostal = $this->no_html($this->codpostal);
        $this->poblacion = $this->no_html($this->poblacion);
        $this->provincia = $this->no_html($this->provincia);
        $this->apartado = $this->no_html($this->apartado);
        $this->telefono = $this->no_html($this->telefono);
        $this->fax = $this->no_html($this->fax);
        $this->contacto = $this->no_html($this->contacto);
        $this->observaciones = $this->no_html($this->observaciones);

        if (!preg_match("/^[A-Z0-9]{1,4}$/i", $this->codalmacen)) {
            $this->new_error_msg("Código de almacén no válido.");
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET nombre = " . $this->var2str($this->nombre) .
                    ", direccion = " . $this->var2str($this->direccion) .
                    ", codpostal = " . $this->var2str($this->codpostal) .
                    ", poblacion = " . $this->var2str($this->poblacion) .
                    ", provincia = " . $this->var2str($this->provincia) .
                    ", codpais = " . $this->var2str($this->codpais) .
                    ", apartado = " . $this->var2str($this->apartado) .
                    ", telefono = " . $this->var2str($this->telefono) .
                    ", fax = " . $this->var2str($this->fax) .
                    ", contacto = " . $this->var2str($this->contacto) .
                    ", observaciones = " . $this->var2str($this->observaciones) .
                    ", idprovincia = " . $this->var2str($this->idprovincia) .
                    ", porpvp = " . $this->var2str($this->porpvp) .
                    ", tipovaloracion = " . $this->var2str($this->tipovaloracion) .
                    " WHERE codalmacen = " . $this->var2str($this->codalmacen) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codalmacen,nombre,direccion,codpostal," .
                    "poblacion,provincia,codpais,apartado,telefono,fax,contacto,observaciones," .
                    "idprovincia,porpvp,tipovaloracion) VALUES (" .
                    $this->var2str($this->codalmacen) . "," .
                    $this->var2str($this->nombre) . "," .
                    $this->var2str($this->direccion) . "," .
                    $this->var2str($this->codpostal) . "," .
                    $this->var2str($this->poblacion) . "," .
                    $this->var2str($this->provincia) . "," .
                    $this->var2str($this->codpais) . "," .
                    $this->var2str($this->apartado) . "," .
                    $this->var2str($this->telefono) . "," .
                    $this->var2str($this->fax) . "," .
                    $this->var2str($this->contacto) . "," .
                    $this->var2str($this->observaciones) . "," .
                    $this->var2str($this->idprovincia) . "," .
                    $this->var2str($this->porpvp) . "," .
                    $this->var2str($this->tipovaloracion) . ");";
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
        $sql = "DELETE FROM " . $this->table_name . " WHERE codalmacen = " . $this->var2str($this->codalmacen) . ";";
        return $this->db->exec($sql);
    }

    public function all()
    {
        $almacenlist = array();
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY nombre ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $almacenlist[] = new \almacen($a);
            }
        }
        return $almacenlist;
    }

    private function clear()
    {
        $this->codalmacen = NULL;
        $this->nombre = '';
        $this->direccion = '';
        $this->codpostal = '';
        $this->poblacion = '';
        $this->provincia = '';
        $this->codpais = '';
        $this->apartado = '';
        $this->telefono = '';
        $this->fax = '';
        $this->contacto = '';
        $this->observaciones = '';
        $this->idprovincia = NULL;
        $this->porpvp = 0;
        $this->tipovaloracion = '';
    }
}
