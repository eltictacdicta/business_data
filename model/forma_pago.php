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
 * Forma de pago de una factura.
 * Clase sin namespace para compatibilidad con facturacion_base.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class forma_pago extends fs_model
{
    private const SQL_SELECT_ALL_FROM = 'SELECT * FROM ';
    private const SQL_WHERE = ' WHERE ';

    /**
     * Clave primaria. Varchar (10).
     * @var string 
     */
    public $codpago;
    public $descripcion;

    /**
     * Pagados -> marca las facturas generadas como pagadas.
     * @var string 
     */
    public $genrecibos;

    /**
     * Código de la cuenta bancaria asociada.
     * @var string 
     */
    public $codcuenta;

    /**
     * Para indicar si hay que mostrar la cuenta bancaria del cliente.
     * @var boolean
     */
    public $domiciliado;

    /**
     * TRUE (por defecto) -> mostrar los datos en documentos de venta,
     * incluida la cuenta bancaria asociada.
     * @var boolean
     */
    public $imprimir;

    /**
     * Sirve para generar la fecha de vencimiento de las facturas.
     * @var string
     */
    public $vencimiento;

    public function __construct($data = FALSE)
    {
        parent::__construct('formaspago');
        if ($data) {
            $this->codpago = $data['codpago'];
            $this->descripcion = $data['descripcion'];
            $this->genrecibos = isset($data['genrecibos']) ? $data['genrecibos'] : 'Emitidos';
            $this->codcuenta = isset($data['codcuenta']) ? $data['codcuenta'] : NULL;
            $this->domiciliado = isset($data['domiciliado']) ? $this->str2bool($data['domiciliado']) : FALSE;
            $this->imprimir = isset($data['imprimir']) ? $this->str2bool($data['imprimir']) : TRUE;
            $this->vencimiento = isset($data['vencimiento']) ? $data['vencimiento'] : '+1day';
        } else {
            $this->clear();
        }
    }

    protected function install()
    {
        return "INSERT INTO " . $this->table_name . " (codpago,descripcion,genrecibos,codcuenta,domiciliado,vencimiento)"
            . " VALUES ('CONT','Al contado','Pagados',NULL,FALSE,'+0day')"
            . ",('TRANS','Transferencia bancaria','Emitidos',NULL,FALSE,'+1month')"
            . ",('TARJETA','Tarjeta de crédito','Pagados',NULL,FALSE,'+0day')"
            . ",('PAYPAL','PayPal','Pagados',NULL,FALSE,'+0day');";
    }

    public function url()
    {
        if (is_null($this->codpago)) {
            return "index.php?page=contabilidad_formas_pago";
        } else {
            return "index.php?page=contabilidad_formas_pago&cod=" . $this->codpago;
        }
    }

    /**
     * Devuelve TRUE si esta es la forma de pago predeterminada de la empresa
     * @return boolean
     */
    public function is_default()
    {
        return ( $this->codpago == $this->default_items->codpago() );
    }

    public function get($cod)
    {
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . "codpago = " . $this->var2str($cod) . ";";
        $data = $this->db->select($sql);
        if ($data) {
            return new forma_pago($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->codpago)) {
            return FALSE;
        } else {
            return $this->db->select(self::SQL_SELECT_ALL_FROM . $this->table_name . self::SQL_WHERE . "codpago = " . $this->var2str($this->codpago) . ";");
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
                    ", genrecibos = " . $this->var2str($this->genrecibos) .
                    ", codcuenta = " . $this->var2str($this->codcuenta) .
                    ", domiciliado = " . $this->var2str($this->domiciliado) .
                    ", imprimir = " . $this->var2str($this->imprimir) .
                    ", vencimiento = " . $this->var2str($this->vencimiento) .
                    " WHERE codpago = " . $this->var2str($this->codpago) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codpago,descripcion,genrecibos,codcuenta,domiciliado,imprimir,vencimiento) VALUES (" .
                    $this->var2str($this->codpago) . "," .
                    $this->var2str($this->descripcion) . "," .
                    $this->var2str($this->genrecibos) . "," .
                    $this->var2str($this->codcuenta) . "," .
                    $this->var2str($this->domiciliado) . "," .
                    $this->var2str($this->imprimir) . "," .
                    $this->var2str($this->vencimiento) . ");";
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
        $sql = self::SQL_SELECT_ALL_FROM . $this->table_name . " ORDER BY descripcion ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $f) {
                $formalist[] = new forma_pago($f);
            }
        }
        return $formalist;
    }

    private function clear()
    {
        $this->codpago = NULL;
        $this->descripcion = '';
        $this->genrecibos = 'Emitidos';
        $this->codcuenta = '';
        $this->domiciliado = FALSE;
        $this->imprimir = TRUE;
        $this->vencimiento = '+1day';
    }
}
