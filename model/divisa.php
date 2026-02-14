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
 * Una divisa (moneda) con su símbolo y su tasa de conversión respecto al euro.
 * Clase sin namespace para compatibilidad con facturacion_base.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class divisa extends fs_model
{
    private const SQL_SELECT_ALL_FROM = 'SELECT * FROM ';
    private const SQL_WHERE = ' WHERE ';

    public $coddivisa;
    public $descripcion;
    public $codiso;
    public $simbolo;
    public $tasaconv;
    public $tasaconv_compra;

    public function __construct($data = FALSE)
    {
        parent::__construct('divisas');
        if ($data) {
            $this->coddivisa = $data['coddivisa'];
            $this->descripcion = $data['descripcion'];
            $this->tasaconv = floatval($data['tasaconv']);
            $this->codiso = isset($data['codiso']) ? $data['codiso'] : NULL;
            $this->simbolo = isset($data['simbolo']) ? $data['simbolo'] : '?';
            $this->tasaconv_compra = isset($data['tasaconv_compra']) ? floatval($data['tasaconv_compra']) : floatval($data['tasaconv']);
        } else {
            $this->clear();
        }
    }

    protected function install()
    {
        return "INSERT INTO " . $this->table_name . " (coddivisa,descripcion,tasaconv,tasaconv_compra,codiso,simbolo)"
            . " VALUES ('EUR','EUROS','1','1','978','€')"
            . ",('ARS','PESOS (ARG)','16.684','16.684','32','AR$')"
            . ",('CLP','PESOS (CLP)','704.0227','704.0227','152','CLP$')"
            . ",('COP','PESOS (COP)','3140.6803','3140.6803','170','CO$')"
            . ",('DOP','PESOS DOMINICANOS','49.7618','49.7618','214','RD$')"
            . ",('GBP','LIBRAS ESTERLINAS','0.865','0.865','826','£')"
            . ",('HTG','GOURDES','72.0869','72.0869','322','G')"
            . ",('MXN','PESOS (MXN)','23.3678','23.3678','484','MX$')"
            . ",('PAB','BALBOAS','1.128','1.128','590','B')"
            . ",('PEN','SOLES','3.736','3.736','604','S/')"
            . ",('PYG','GUARANÍ','6750','6750','4217','Gs')"
            . ",('USD','DÓLARES EE.UU.','1.129','1.129','840','$')"
            . ",('VEF','BOLÍVARES','10.6492','10.6492','937','Bs');";
    }

    public function url()
    {
        return "index.php?page=admin_divisas";
    }

    /**
     * Devuelve TRUE si esta es la divisa predeterminada de la empresa
     * @return boolean
     */
    public function is_default()
    {
        return ($this->coddivisa == $this->default_items->coddivisa());
    }

    public function get($cod)
    {
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . "coddivisa = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new static($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->coddivisa)) {
            return FALSE;
        } else {
            return $this->db->select(self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . "coddivisa = " . $this->var2str($this->coddivisa) . ";");
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
                    ", tasaconv_compra = " . $this->var2str($this->tasaconv_compra) .
                    " WHERE coddivisa = " . $this->var2str($this->coddivisa) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (coddivisa,descripcion,codiso,simbolo,tasaconv,tasaconv_compra) VALUES (" .
                    $this->var2str($this->coddivisa) . "," .
                    $this->var2str($this->descripcion) . "," .
                    $this->var2str($this->codiso) . "," .
                    $this->var2str($this->simbolo) . "," .
                    $this->var2str($this->tasaconv) . "," .
                    $this->var2str($this->tasaconv_compra) . ");";
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
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . " ORDER BY descripcion ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $divisalist[] = new static($d);
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
        $this->tasaconv_compra = 1;
    }
}
