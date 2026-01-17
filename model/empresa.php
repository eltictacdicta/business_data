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
 * Esta clase almacena los principales datos de la empresa.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class empresa extends \fs_model
{
    public $id;
    public $xid;
    public $cifnif;
    public $nombre;
    public $nombrecorto;
    public $administrador;
    public $direccion;
    public $apartado;
    public $codpostal;
    public $ciudad;
    public $provincia;
    public $idprovincia;
    public $codpais;
    public $telefono;
    public $fax;
    public $email;
    public $web;
    public $horario;
    public $contintegrada;
    public $codejercicio;
    public $codalmacen;
    public $coddivisa;
    public $codpago;
    public $codserie;
    public $codcuentarem;
    public $codedi;
    public $logo;
    public $lema;
    public $pie_factura;
    public $recequivalencia;
    public $stockpedidos;

    /**
     * Configuración de email
     * @var array
     */
    public $email_config;

    public function __construct($data = FALSE)
    {
        parent::__construct('empresa');
        if ($data) {
            $this->id = $data['id'];
            $this->xid = isset($data['xid']) ? $data['xid'] : NULL;
            $this->cifnif = $data['cifnif'];
            $this->nombre = $data['nombre'];
            $this->nombrecorto = isset($data['nombrecorto']) ? $data['nombrecorto'] : '';
            $this->administrador = $data['administrador'];
            $this->direccion = $data['direccion'];
            $this->apartado = isset($data['apartado']) ? $data['apartado'] : '';
            $this->codpostal = isset($data['codpostal']) ? $data['codpostal'] : '';
            $this->ciudad = isset($data['ciudad']) ? $data['ciudad'] : '';
            $this->provincia = isset($data['provincia']) ? $data['provincia'] : '';
            $this->idprovincia = isset($data['idprovincia']) ? $data['idprovincia'] : NULL;
            $this->codpais = isset($data['codpais']) ? $data['codpais'] : '';
            $this->telefono = isset($data['telefono']) ? $data['telefono'] : '';
            $this->fax = isset($data['fax']) ? $data['fax'] : '';
            $this->email = isset($data['email']) ? $data['email'] : '';
            $this->web = isset($data['web']) ? $data['web'] : '';
            $this->horario = isset($data['horario']) ? $data['horario'] : '';
            $this->contintegrada = isset($data['contintegrada']) ? $this->str2bool($data['contintegrada']) : FALSE;
            $this->codejercicio = isset($data['codejercicio']) ? $data['codejercicio'] : NULL;
            $this->codalmacen = isset($data['codalmacen']) ? $data['codalmacen'] : NULL;
            $this->coddivisa = isset($data['coddivisa']) ? $data['coddivisa'] : NULL;
            $this->codpago = isset($data['codpago']) ? $data['codpago'] : NULL;
            $this->codserie = isset($data['codserie']) ? $data['codserie'] : NULL;
            $this->codcuentarem = isset($data['codcuentarem']) ? $data['codcuentarem'] : NULL;
            $this->codedi = isset($data['codedi']) ? $data['codedi'] : NULL;
            $this->logo = isset($data['logo']) ? $data['logo'] : '';
            $this->lema = isset($data['lema']) ? $data['lema'] : '';
            $this->pie_factura = isset($data['pie_factura']) ? $data['pie_factura'] : '';
            $this->recequivalencia = isset($data['recequivalencia']) ? $this->str2bool($data['recequivalencia']) : FALSE;
            $this->stockpedidos = isset($data['stockpedidos']) ? $this->str2bool($data['stockpedidos']) : FALSE;

            // Inicializar configuración de email
            $this->email_config = array(
                'mail_mailer' => 'smtp',
                'mail_host' => '',
                'mail_port' => 587,
                'mail_user' => '',
                'mail_password' => '',
                'mail_enc' => 'tls',
                'mail_low_security' => false,
                'mail_bcc' => '',
                'mail_firma' => ''
            );
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
        return "index.php?page=admin_empresa";
    }

    public function get($id = NULL)
    {
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY id ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            return new \empresa($data[0]);
        } else {
            return FALSE;
        }
    }

    public function exists()
    {
        if (is_null($this->id)) {
            return FALSE;
        } else {
            return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";");
        }
    }

    public function test()
    {
        $this->cifnif = $this->no_html($this->cifnif);
        $this->nombre = $this->no_html($this->nombre);
        $this->nombrecorto = $this->no_html($this->nombrecorto);
        $this->administrador = $this->no_html($this->administrador);
        $this->direccion = $this->no_html($this->direccion);
        $this->apartado = $this->no_html($this->apartado);
        $this->codpostal = $this->no_html($this->codpostal);
        $this->ciudad = $this->no_html($this->ciudad);
        $this->provincia = $this->no_html($this->provincia);
        $this->codpais = $this->no_html($this->codpais);
        $this->telefono = $this->no_html($this->telefono);
        $this->fax = $this->no_html($this->fax);
        $this->email = $this->no_html($this->email);
        $this->web = $this->no_html($this->web);
        $this->horario = $this->no_html($this->horario);

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET cifnif = " . $this->var2str($this->cifnif) .
                    ", nombre = " . $this->var2str($this->nombre) .
                    ", nombrecorto = " . $this->var2str($this->nombrecorto) .
                    ", administrador = " . $this->var2str($this->administrador) .
                    ", direccion = " . $this->var2str($this->direccion) .
                    ", apartado = " . $this->var2str($this->apartado) .
                    ", codpostal = " . $this->var2str($this->codpostal) .
                    ", ciudad = " . $this->var2str($this->ciudad) .
                    ", provincia = " . $this->var2str($this->provincia) .
                    ", idprovincia = " . $this->var2str($this->idprovincia) .
                    ", codpais = " . $this->var2str($this->codpais) .
                    ", telefono = " . $this->var2str($this->telefono) .
                    ", fax = " . $this->var2str($this->fax) .
                    ", email = " . $this->var2str($this->email) .
                    ", web = " . $this->var2str($this->web) .
                    ", horario = " . $this->var2str($this->horario) .
                    ", contintegrada = " . $this->var2str($this->contintegrada) .
                    ", codejercicio = " . $this->var2str($this->codejercicio) .
                    ", codalmacen = " . $this->var2str($this->codalmacen) .
                    ", coddivisa = " . $this->var2str($this->coddivisa) .
                    ", codpago = " . $this->var2str($this->codpago) .
                    ", codserie = " . $this->var2str($this->codserie) .
                    ", logo = " . $this->var2str($this->logo) .
                    ", lema = " . $this->var2str($this->lema) .
                    ", pie_factura = " . $this->var2str($this->pie_factura) .
                    ", recequivalencia = " . $this->var2str($this->recequivalencia) .
                    ", stockpedidos = " . $this->var2str($this->stockpedidos) .
                    " WHERE id = " . $this->var2str($this->id) . ";";
                return $this->db->exec($sql);
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (xid,cifnif,nombre,nombrecorto,administrador," .
                    "direccion,apartado,codpostal,ciudad,provincia,idprovincia,codpais,telefono,fax," .
                    "email,web,horario,contintegrada,codejercicio,codalmacen,coddivisa,codpago," .
                    "codserie,logo,lema,pie_factura,recequivalencia,stockpedidos) VALUES (" .
                    $this->var2str($this->xid) . "," .
                    $this->var2str($this->cifnif) . "," .
                    $this->var2str($this->nombre) . "," .
                    $this->var2str($this->nombrecorto) . "," .
                    $this->var2str($this->administrador) . "," .
                    $this->var2str($this->direccion) . "," .
                    $this->var2str($this->apartado) . "," .
                    $this->var2str($this->codpostal) . "," .
                    $this->var2str($this->ciudad) . "," .
                    $this->var2str($this->provincia) . "," .
                    $this->var2str($this->idprovincia) . "," .
                    $this->var2str($this->codpais) . "," .
                    $this->var2str($this->telefono) . "," .
                    $this->var2str($this->fax) . "," .
                    $this->var2str($this->email) . "," .
                    $this->var2str($this->web) . "," .
                    $this->var2str($this->horario) . "," .
                    $this->var2str($this->contintegrada) . "," .
                    $this->var2str($this->codejercicio) . "," .
                    $this->var2str($this->codalmacen) . "," .
                    $this->var2str($this->coddivisa) . "," .
                    $this->var2str($this->codpago) . "," .
                    $this->var2str($this->codserie) . "," .
                    $this->var2str($this->logo) . "," .
                    $this->var2str($this->lema) . "," .
                    $this->var2str($this->pie_factura) . "," .
                    $this->var2str($this->recequivalencia) . "," .
                    $this->var2str($this->stockpedidos) . ");";
                if ($this->db->exec($sql)) {
                    $this->id = $this->db->lastval();
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
        $sql = "DELETE FROM " . $this->table_name . " WHERE id = " . $this->var2str($this->id) . ";";
        return $this->db->exec($sql);
    }

    /**
     * Verifica si la empresa puede enviar emails
     * @return boolean
     */
    public function can_send_mail()
    {
        return isset($this->email_config['mail_host']) && !empty($this->email_config['mail_host']);
    }

    /**
     * Crea una nueva instancia de PHPMailer configurada
     * @return \PHPMailer
     */
    public function new_mail()
    {
        $mail = new \PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->WordWrap = 50;
        $mail->Mailer = isset($this->email_config['mail_mailer']) ? $this->email_config['mail_mailer'] : 'smtp';
        $mail->SMTPAuth = true;
        $mail->Host = isset($this->email_config['mail_host']) ? $this->email_config['mail_host'] : '';
        $mail->Username = isset($this->email_config['mail_user']) ? $this->email_config['mail_user'] : '';
        $mail->Password = isset($this->email_config['mail_password']) ? $this->email_config['mail_password'] : '';
        $mail->Port = isset($this->email_config['mail_port']) ? $this->email_config['mail_port'] : 587;
        $mail->From = $this->email;
        $mail->FromName = $this->nombre;

        if (isset($this->email_config['mail_enc']) && $this->email_config['mail_enc'] != '') {
            $mail->SMTPSecure = $this->email_config['mail_enc'];
        }

        if (isset($this->email_config['mail_low_security']) && $this->email_config['mail_low_security']) {
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        }

        return $mail;
    }

    /**
     * Conecta con el servidor de email
     * @param \PHPMailer $mail
     * @return boolean
     */
    public function mail_connect($mail)
    {
        try {
            $mail->SMTPConnect();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Limpia la caché de la empresa
     */
    public function clean_cache()
    {
        // Método para compatibilidad
    }

    private function clear()
    {
        $this->id = NULL;
        $this->xid = '';
        $this->cifnif = '';
        $this->nombre = '';
        $this->nombrecorto = '';
        $this->administrador = '';
        $this->direccion = '';
        $this->apartado = '';
        $this->codpostal = '';
        $this->ciudad = '';
        $this->provincia = '';
        $this->idprovincia = NULL;
        $this->codpais = '';
        $this->telefono = '';
        $this->fax = '';
        $this->email = '';
        $this->web = '';
        $this->horario = '';
        $this->contintegrada = FALSE;
        $this->codejercicio = '';
        $this->codalmacen = '';
        $this->coddivisa = '';
        $this->codpago = '';
        $this->codserie = '';
        $this->codcuentarem = '';
        $this->codedi = '';
        $this->logo = '';
        $this->lema = '';
        $this->pie_factura = '';
        $this->recequivalencia = FALSE;
        $this->stockpedidos = FALSE;

        // Inicializar configuración de email
        $this->email_config = array(
            'mail_mailer' => 'smtp',
            'mail_host' => '',
            'mail_port' => 587,
            'mail_user' => '',
            'mail_password' => '',
            'mail_enc' => 'tls',
            'mail_low_security' => false,
            'mail_bcc' => '',
            'mail_firma' => ''
        );
    }
}
