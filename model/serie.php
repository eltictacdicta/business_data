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
 * Una serie de facturación o contabilidad, para agrupar documentos de compra y/o venta
 * y para tener distinta numeración en cada serie.
 * Clase sin namespace para compatibilidad con facturacion_base.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class serie extends fs_model
{
    private const SQL_SELECT_ALL_FROM = 'SELECT * FROM ';
    private const SQL_WHERE = ' WHERE ';

    /**
     * Clave primaria. Varchar (2).
     * @var string 
     */
    public $codserie;
    public $descripcion;

    /**
     * TRUE -> las facturas asociadas no encluyen IVA.
     * @var boolean
     */
    public $siniva;

    /**
     * % de retención IRPF de las facturas asociadas.
     * @var float
     */
    public $irpf;

    /**
     * Código de cuenta contable.
     * @var string
     */
    public $codcuenta;

    /**
     * ID de cuenta contable.
     * @var integer
     */
    public $idcuenta;

    /**
     * ejercicio para el que asignamos la numeración inicial de la serie.
     * @var string
     */
    public $codejercicio;

    /**
     * numeración inicial para las facturas de esta serie.
     * @var integer
     */
    public $numfactura;

    public function __construct($data = FALSE)
    {
        parent::__construct('series');
        if ($data) {
            $this->codserie = $data['codserie'];
            $this->descripcion = $data['descripcion'];
            $this->siniva = $this->str2bool($data['siniva']);
            $this->irpf = floatval($data['irpf']);
            $this->codcuenta = isset($data['codcuenta']) ? $data['codcuenta'] : NULL;
            $this->idcuenta = isset($data['idcuenta']) ? $data['idcuenta'] : NULL;
            $this->codejercicio = isset($data['codejercicio']) ? $data['codejercicio'] : NULL;
            $this->numfactura = isset($data['numfactura']) ? max(array(1, intval($data['numfactura']))) : 1;
        } else {
            $this->clear();
        }
    }

    protected function install()
    {
        return "INSERT INTO " . $this->table_name . " (codserie,descripcion,siniva,irpf) VALUES "
            . "('A','SERIE A',FALSE,'0'),('R','RECTIFICATIVAS',FALSE,'0');";
    }

    public function url()
    {
        if (is_null($this->codserie)) {
            return "index.php?page=contabilidad_series";
        } else {
            return "index.php?page=contabilidad_series#" . $this->codserie;
        }
    }

    /**
     * Devuelve TRUE si la serie es la predeterminada de la empresa
     * @return bool
     */
    public function is_default()
    {
        return ( $this->codserie == $this->default_items->codserie() );
    }

    public function get($cod)
    {
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . "codserie = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new serie($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codserie)) {
            return FALSE;
        } else {
            return $this->db->select(self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . "codserie = " . $this->var2str($this->codserie) . ";");
        }
    }

    public function test()
    {
        $this->codserie = trim($this->codserie);
        $this->descripcion = $this->no_html($this->descripcion);

        if ($this->numfactura < 1) {
            $this->numfactura = 1;
        }

        if (!preg_match("/^[A-Z0-9]{1,2}$/i", $this->codserie)) {
            $this->new_error_msg("Código de serie no válido.");
            return FALSE;
        } else if (strlen($this->descripcion) < 1 || strlen($this->descripcion) > 100) {
            $this->new_error_msg("Descripción de serie no válida.");
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
                return $this->db->exec($sql);
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
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . " ORDER BY codserie ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $s) {
                $serielist[] = new serie($s);
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
        $this->codcuenta = NULL;
        $this->idcuenta = NULL;
        $this->codejercicio = NULL;
        $this->numfactura = 1;
    }
}
