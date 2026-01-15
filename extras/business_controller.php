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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'base/fs_divisa_tools.php';

/**
 * Controlador extendido para plugins que necesitan acceso a datos de empresa.
 * Este controlador añade la propiedad $empresa y herramientas de divisa
 * al controlador base fs_controller.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class business_controller extends fs_controller
{

    /**
     * La empresa
     * @var empresa
     */
    public $empresa;

    /**
     *
     * @var fs_divisa_tools
     */
    protected $divisa_tools;

    protected function private_core()
    {
        /// Verificamos datos por defecto del sistema
        if (function_exists('business_data_check_default_data')) {
            business_data_check_default_data($this->db);
        }
        
        /// Inicializamos la empresa
        $this->init_empresa();
        
        /// Establecemos los valores por defecto basados en la empresa
        $this->set_empresa_defaults();
    }

    /**
     * Inicializa la empresa asegurando que esté disponible
     */
    protected function init_empresa()
    {
        if (!isset($this->empresa) || !$this->empresa) {
            $this->empresa = new empresa();
            $empresa_data = $this->empresa->get();
            if ($empresa_data) {
                $this->empresa = $empresa_data;
            }
        }
        
        /// Inicializamos las herramientas de divisa con la divisa de la empresa
        $coddivisa = ($this->empresa && isset($this->empresa->coddivisa) && $this->empresa->coddivisa) 
            ? $this->empresa->coddivisa 
            : 'EUR';
        $this->divisa_tools = new fs_divisa_tools($coddivisa);
    }

    /**
     * Establece los valores por defecto basados en la configuración de la empresa
     */
    protected function set_empresa_defaults()
    {
        if (!isset($this->empresa) || !$this->empresa || !isset($this->empresa->id) || !$this->empresa->id) {
            return;
        }

        if (isset($this->empresa->codejercicio) && $this->empresa->codejercicio) {
            $this->default_items->set_codejercicio($this->empresa->codejercicio);
        }

        if (filter_input(INPUT_COOKIE, 'default_almacen')) {
            $this->default_items->set_codalmacen(filter_input(INPUT_COOKIE, 'default_almacen'));
        } else if (isset($this->empresa->codalmacen) && $this->empresa->codalmacen) {
            $this->default_items->set_codalmacen($this->empresa->codalmacen);
        }

        if (filter_input(INPUT_COOKIE, 'default_formapago')) {
            $this->default_items->set_codpago(filter_input(INPUT_COOKIE, 'default_formapago'));
        } else if (isset($this->empresa->codpago) && $this->empresa->codpago) {
            $this->default_items->set_codpago($this->empresa->codpago);
        }

        if (filter_input(INPUT_COOKIE, 'default_impuesto')) {
            $this->default_items->set_codimpuesto(filter_input(INPUT_COOKIE, 'default_impuesto'));
        }

        if (isset($this->empresa->codpais) && $this->empresa->codpais) {
            $this->default_items->set_codpais($this->empresa->codpais);
        }

        if (isset($this->empresa->codserie) && $this->empresa->codserie) {
            $this->default_items->set_codserie($this->empresa->codserie);
        }

        if (isset($this->empresa->coddivisa) && $this->empresa->coddivisa) {
            $this->default_items->set_coddivisa($this->empresa->coddivisa);
        }
    }

    /**
     * Convierte un precio de la divisa_desde a la divisa especificada
     * @param float $precio
     * @param string $coddivisa_desde
     * @param string $coddivisa
     * @return float
     */
    public function divisa_convert($precio, $coddivisa_desde, $coddivisa)
    {
        return $this->divisa_tools->divisa_convert($precio, $coddivisa_desde, $coddivisa);
    }

    /**
     * Convierte el precio en euros a la divisa preterminada de la empresa.
     * Por defecto usa las tasas de conversión actuales, pero si se especifica
     * coddivisa y tasaconv las usará.
     * @param float $precio
     * @param string $coddivisa
     * @param float $tasaconv
     * @return float
     */
    public function euro_convert($precio, $coddivisa = NULL, $tasaconv = NULL)
    {
        return $this->divisa_tools->euro_convert($precio, $coddivisa, $tasaconv);
    }

    /**
     * Devuelve un string con el número en el formato de número predeterminado.
     * @param float $num
     * @param integer $decimales
     * @param boolean $js
     * @return string
     */
    public function show_numero($num = 0, $decimales = FS_NF0, $js = FALSE)
    {
        return $this->divisa_tools->show_numero($num, $decimales, $js);
    }

    /**
     * Devuelve un string con el precio en el formato predefinido y con la
     * divisa seleccionada (o la predeterminada).
     * @param float $precio
     * @param string $coddivisa
     * @param string $simbolo
     * @param integer $dec nº de decimales
     * @return string
     */
    public function show_precio($precio = 0, $coddivisa = FALSE, $simbolo = TRUE, $dec = FS_NF0)
    {
        return $this->divisa_tools->show_precio($precio, $coddivisa, $simbolo, $dec);
    }

    /**
     * Devuelve el símbolo de divisa predeterminado
     * o bien el símbolo de la divisa seleccionada.
     * @param string $coddivisa
     * @return string
     */
    public function simbolo_divisa($coddivisa = FALSE)
    {
        return $this->divisa_tools->simbolo_divisa($coddivisa);
    }

    /**
     * Limpia la caché de la empresa
     */
    public function clean_empresa_cache()
    {
        if (isset($this->empresa) && method_exists($this->empresa, 'clean_cache')) {
            $this->empresa->clean_cache();
        }
    }

    /**
     * Comprueba si la empresa puede enviar correos electrónicos
     * @return boolean
     */
    public function can_send_mail()
    {
        if (isset($this->empresa) && method_exists($this->empresa, 'can_send_mail')) {
            return $this->empresa->can_send_mail();
        }
        return false;
    }

    /**
     * Crea un nuevo objeto de correo configurado con los datos de la empresa
     * @return \PHPMailer|null
     */
    public function new_mail()
    {
        if (isset($this->empresa) && method_exists($this->empresa, 'new_mail')) {
            return $this->empresa->new_mail();
        }
        return null;
    }

    /**
     * Conecta el objeto mail con los datos de la empresa
     * @param \PHPMailer $mail
     * @return boolean
     */
    public function mail_connect($mail)
    {
        if (isset($this->empresa) && method_exists($this->empresa, 'mail_connect')) {
            return $this->empresa->mail_connect($mail);
        }
        return false;
    }
}
