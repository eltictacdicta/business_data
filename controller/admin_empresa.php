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
require_once 'base/fs_divisa_tools.php';
require_once 'base/fs_default_items.php';

// Cargar clases de compatibilidad
require_once dirname(__FILE__) . '/../model/empresa_compat.php';
require_once dirname(__FILE__) . '/../model/almacen_compat.php';
require_once dirname(__FILE__) . '/../model/cuenta_banco_compat.php';
require_once dirname(__FILE__) . '/../model/divisa_compat.php';
require_once dirname(__FILE__) . '/../model/ejercicio_compat.php';
require_once dirname(__FILE__) . '/../model/forma_pago_compat.php';
require_once dirname(__FILE__) . '/../model/serie_compat.php';
require_once dirname(__FILE__) . '/../model/pais_compat.php';

/**
 * Controlador de admin -> empresa.
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class admin_empresa extends fs_controller
{

    public $almacen;
    public $cuenta_banco;
    public $divisa;
    public $ejercicio;
    public $forma_pago;
    public $impresion = array();
    public $serie;
    public $pais;

    /**
     * @var empresa
     */
    public $empresa;

    /**
     * @var fs_divisa_tools
     */
    protected $divisa_tools;

    /**
     * @var fs_default_items
     */
    public $default_items;

    /**
     * @var array
     */
    public $email_plantillas = array();

    /**
     * @var string
     */
    public $logo = '';

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Empresa / web', 'admin', TRUE, TRUE);

        // Inicializar empresa antes que cualquier otra cosa
        $this->initialize_empresa();

        // Initialize default items and empresa-related settings
        $this->initialize_default_items();
    }

    /**
     * Inicializa la empresa asegurando que esté disponible
     */
    protected function initialize_empresa()
    {
        if (!isset($this->empresa) || !$this->empresa) {
            $this->empresa = new empresa();
            $empresa_data = $this->empresa->get();
            if ($empresa_data) {
                $this->empresa = $empresa_data;
            } else {
                // Si no hay datos de empresa, asegurar que el objeto tenga propiedades básicas inicializadas
                $this->empresa->nombre = '';
                $this->empresa->nombrecorto = '';
                $this->empresa->web = '';
                $this->empresa->email = '';
                $this->empresa->email_config = array(
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
    }

    /**
     * Inicializa los valores por defecto para la empresa
     */
    protected function initialize_default_items()
    {
        if (!isset($this->default_items)) {
            $this->default_items = new fs_default_items();
        }

        if (isset($this->empresa) && $this->empresa && isset($this->empresa->id) && $this->empresa->id) {
            // Set all default items based on empresa configuration
            if (isset($this->empresa->codejercicio) && $this->empresa->codejercicio) {
                $this->default_items->set_codejercicio($this->empresa->codejercicio);
            }
            if (isset($this->empresa->codalmacen) && $this->empresa->codalmacen) {
                $this->default_items->set_codalmacen($this->empresa->codalmacen);
            }
            if (isset($this->empresa->codpago) && $this->empresa->codpago) {
                $this->default_items->set_codpago($this->empresa->codpago);
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
    }

    protected function private_core()
    {
        /// Asegurar que la empresa esté inicializada
        $this->initialize_empresa();

        // Initialize default items when empresa data changes
        $this->initialize_default_items();

        /// inicializamos para que se creen las tablas
        $this->almacen = new almacen();
        $this->cuenta_banco = new cuenta_banco();
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->forma_pago = new forma_pago();
        $this->serie = new serie();
        $this->pais = new pais();

        $fsvar = new fs_var();

        /// obtenemos los datos de configuración de impresión
        $this->impresion = array(
            'print_ref' => '1',
            'print_dto' => '1',
            'print_alb' => '0',
            'print_formapago' => '1'
        );
        $this->impresion = $fsvar->array_get($this->impresion, FALSE);

        /// obtenemos los datos de las plantillas de emails
        $this->email_plantillas = array(
            'mail_factura' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_albaran' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_pedido' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
            'mail_presupuesto' => "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#",
        );
        $this->email_plantillas = $fsvar->array_get($this->email_plantillas, FALSE);

        /// Inicializamos las herramientas de divisa con la divisa de la empresa
        $coddivisa = ($this->empresa && $this->empresa->coddivisa) ? $this->empresa->coddivisa : 'EUR';
        $this->divisa_tools = new fs_divisa_tools($coddivisa);

        if (filter_input(INPUT_POST, 'nombre')) {
            $fields = [
                'nombre',
                'nombrecorto',
                'cifnif',
                'administrador',
                'codpais',
                'provincia',
                'ciudad',
                'direccion',
                'codpostal',
                'apartado',
                'telefono',
                'fax',
                'web',
                'email',
                'lema',
                'horario',
                'codejercicio',
                'codserie',
                'coddivisa',
                'codpago',
                'codalmacen',
                'pie_factura'
            ];
            foreach ($fields as $field) {
                $value = filter_input(INPUT_POST, $field);
                if ($value !== NULL) {
                    $this->empresa->{$field} = $value;
                }
            }
            $this->empresa->contintegrada = (bool) filter_input(INPUT_POST, 'contintegrada');
            $this->empresa->recequivalencia = (bool) filter_input(INPUT_POST, 'recequivalencia');

            /// configuración de email
            $this->empresa->email_config['mail_password'] = filter_input(INPUT_POST, 'mail_password');
            $this->empresa->email_config['mail_bcc'] = filter_input(INPUT_POST, 'mail_bcc');
            $this->empresa->email_config['mail_firma'] = filter_input(INPUT_POST, 'mail_firma');
            $this->empresa->email_config['mail_mailer'] = filter_input(INPUT_POST, 'mail_mailer');
            $this->empresa->email_config['mail_host'] = filter_input(INPUT_POST, 'mail_host');
            $this->empresa->email_config['mail_port'] = intval(filter_input(INPUT_POST, 'mail_port'));
            $this->empresa->email_config['mail_enc'] = strtolower(filter_input(INPUT_POST, 'mail_enc'));
            $this->empresa->email_config['mail_user'] = filter_input(INPUT_POST, 'mail_user');
            $this->empresa->email_config['mail_low_security'] = (bool) filter_input(INPUT_POST, 'mail_low_security');

            if ($this->empresa->save()) {
                /// guardamos las opciones por defecto de almacén y forma de pago
                $this->save_codalmacen(filter_input(INPUT_POST, 'codalmacen'));
                $this->save_codpago(filter_input(INPUT_POST, 'codpago'));

                $this->new_message('Datos guardados correctamente.');
                $this->mail_test();

                // Actualizamos los valores por defecto después de guardar
                $this->initialize_default_items();
            } else {
                $this->new_error_msg('Error al guardar los datos.');
            }

            /// guardamos los datos de impresión
            $this->impresion['print_ref'] = (filter_input(INPUT_POST, 'print_ref') ? 1 : 0);
            $this->impresion['print_dto'] = (filter_input(INPUT_POST, 'print_dto') ? 1 : 0);
            $this->impresion['print_alb'] = (filter_input(INPUT_POST, 'print_alb') ? 1 : 0);
            $this->impresion['print_formapago'] = (filter_input(INPUT_POST, 'print_formapago') ? 1 : 0);
            $fsvar->array_save($this->impresion);

            /// guardamos las plantillas de emails
            $this->email_plantillas['mail_factura'] = filter_input(INPUT_POST, 'mail_factura');
            $this->email_plantillas['mail_albaran'] = filter_input(INPUT_POST, 'mail_albaran');
            if (filter_input(INPUT_POST, 'mail_pedido')) {
                $this->email_plantillas['mail_pedido'] = filter_input(INPUT_POST, 'mail_pedido');
                $this->email_plantillas['mail_presupuesto'] = filter_input(INPUT_POST, 'mail_presupuesto');
            }
            $fsvar->array_save($this->email_plantillas);
        } else if (filter_input(INPUT_POST, 'logo')) {
            $this->cambiar_logo();
        } else if (filter_input(INPUT_GET, 'delete_logo')) {
            $this->delete_logo();
        } else if (filter_input(INPUT_GET, 'delete_cuenta')) { /// eliminar cuenta bancaria
            $cuenta = $this->cuenta_banco->get(filter_input(INPUT_GET, 'delete_cuenta'));
            if ($cuenta) {
                if ($cuenta->delete()) {
                    $this->new_message('Cuenta bancaria eliminada correctamente.');
                } else {
                    $this->new_error_msg('Imposible eliminar la cuenta bancaria.');
                }
            } else {
                $this->new_error_msg('Cuenta bancaria no encontrada.');
            }
        } else if (filter_input(INPUT_POST, 'iban')) { /// añadir/modificar cuenta bancaria
            if (filter_input(INPUT_POST, 'codcuenta')) {
                $cuentab = $this->cuenta_banco->get(filter_input(INPUT_POST, 'codcuenta'));
            } else {
                $cuentab = new cuenta_banco();
            }

            $cuentab->descripcion = filter_input(INPUT_POST, 'descripcion');
            $cuentab->iban = filter_input(INPUT_POST, 'iban');
            $cuentab->swift = filter_input(INPUT_POST, 'swift');

            $cuentab->codsubcuenta = NULL;
            if (filter_input(INPUT_POST, 'codsubcuenta')) {
                $cuentab->codsubcuenta = filter_input(INPUT_POST, 'codsubcuenta');
            }

            if ($cuentab->save()) {
                $this->new_message('Cuenta bancaria guardada correctamente.');
            } else {
                $this->new_error_msg('Imposible guardar la cuenta bancaria.');
            }
        } else {
            $this->fix_logo();
        }

        $this->load_logo();

        // Llamando la funcion que realiza el autocomplete
        if (filter_input(INPUT_GET, 'subcuenta')) {
            $this->buscar_subcuenta(filter_input(INPUT_GET, 'subcuenta'));
        }
    }

    /**
     * Establece un almacén como predeterminado para este usuario.
     * @param string $cod el código del almacén
     */
    protected function save_codalmacen($cod)
    {
        setcookie('default_almacen', $cod, time() + FS_COOKIES_EXPIRE);
        $this->default_items->set_codalmacen($cod);
    }

    /**
     * Establece un impuesto (IVA) como predeterminado para este usuario.
     * @param string $cod el código del impuesto
     */
    protected function save_codimpuesto($cod)
    {
        setcookie('default_impuesto', $cod, time() + FS_COOKIES_EXPIRE);
        $this->default_items->set_codimpuesto($cod);
    }

    /**
     * Establece una forma de pago como predeterminada para este usuario.
     * @param string $cod el código de la forma de pago
     */
    protected function save_codpago($cod)
    {
        setcookie('default_formapago', $cod, time() + FS_COOKIES_EXPIRE);
        $this->default_items->set_codpago($cod);
    }

    private function mail_test()
    {
        if (!isset($this->empresa) || !$this->empresa || false === $this->empresa->can_send_mail()) {
            return;
        }

        /// Es imprescindible OpenSSL para enviar emails con los principales proveedores
        if (false === extension_loaded('openssl')) {
            $this->new_error_msg('No se encuentra la extensión OpenSSL, imprescindible para enviar emails.');
            return;
        }

        $mail = $this->empresa->new_mail();
        $mail->Timeout = 3;
        $mail->FromName = $this->user->nick;
        $mail->Subject = 'TEST';
        $mail->AltBody = 'TEST';
        $mail->msgHTML('TEST');
        $mail->isHTML(TRUE);

        if ($this->empresa->mail_connect($mail)) {
            /// OK
            return;
        }

        $this->new_error_msg('No se ha podido conectar por email. ¿La contraseña es correcta?');
        if ($mail->Host == 'smtp.gmail.com') {
            $this->new_error_msg('Aunque la contraseña de gmail sea correcta, en ciertas '
                . 'situaciones los servidores de gmail bloquean la conexión. '
                . 'Para superar esta situación debes crear y usar una '
                . '<a href="https://support.google.com/accounts/answer/185833?hl=es" '
                . 'target="_blank">contraseña de aplicación</a>');
            return;
        }

        $this->new_error_msg("¿<a href='" . FS_COMMUNITY_URL . "/contacto' target='_blank'>Necesitas ayuda</a>?");
    }

    private function fix_logo()
    {
        if (!file_exists(FS_MYDOCS . 'images')) {
            @mkdir(FS_MYDOCS . 'images', 0777, TRUE);
        }

        if (file_exists('tmp/' . FS_TMP_NAME . 'logo.png')) {
            rename('tmp/' . FS_TMP_NAME . 'logo.png', FS_MYDOCS . 'images/logo.png');
        } else if (file_exists('tmp/' . FS_TMP_NAME . 'logo.jpg')) {
            rename('tmp/' . FS_TMP_NAME . 'logo.jpg', FS_MYDOCS . 'images/logo.jpg');
        }
    }

    private function load_logo()
    {
        $this->logo = '';
        if (file_exists(FS_MYDOCS . 'images/logo.png')) {
            $this->logo = 'images/logo.png';
        } else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
            $this->logo = 'images/logo.jpg';
        }
    }

    private function cambiar_logo()
    {
        if (isset($_FILES['fimagen']) && is_uploaded_file($_FILES['fimagen']['tmp_name'])) {
            if (!file_exists(FS_MYDOCS . 'images')) {
                @mkdir(FS_MYDOCS . 'images', 0777, TRUE);
            }
            $this->delete_logo();

            if (substr(strtolower($_FILES['fimagen']['name']), -3) == 'png') {
                copy($_FILES['fimagen']['tmp_name'], FS_MYDOCS . "images/logo.png");
            } else {
                copy($_FILES['fimagen']['tmp_name'], FS_MYDOCS . "images/logo.jpg");
            }

            $this->new_message('Logotipo guardado correctamente.');
        }
    }

    private function delete_logo()
    {
        if (file_exists(FS_MYDOCS . 'images/logo.png')) {
            unlink(FS_MYDOCS . 'images/logo.png');
            $this->new_message('Logotipo borrado correctamente.');
        } else if (file_exists(FS_MYDOCS . 'images/logo.jpg')) {
            unlink(FS_MYDOCS . 'images/logo.jpg');
            $this->new_message('Logotipo borrado correctamente.');
        }
    }

    public function encriptaciones()
    {
        return array(
            'ssl' => 'SSL',
            'tls' => 'TLS',
            '' => 'Ninguna'
        );
    }

    public function mailers()
    {
        return array(
            'mail' => 'Mail',
            'sendmail' => 'SendMail',
            'smtp' => 'SMTP'
        );
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
        if (isset($this->empresa)) {
            $this->empresa->clean_cache();
        }
    }

    /**
     * Comprueba si la empresa puede enviar correos electrónicos
     * @return boolean
     */
    public function can_send_mail()
    {
        return $this->empresa->can_send_mail();
    }

    /**
     * Crea un nuevo objeto de correo configurado con los datos de la empresa
     * @return \PHPMailer
     */
    public function new_mail()
    {
        return $this->empresa->new_mail();
    }

    /**
     * Conecta el objeto mail con los datos de la empresa
     * @param \PHPMailer $mail
     * @return boolean
     */
    public function mail_connect($mail)
    {
        return $this->empresa->mail_connect($mail);
    }

    /**
     * Devuelve TRUE si las configuraciones no_html de la empresa
     * @param string $txt
     * @return string
     */
    public function no_html($txt)
    {
        return $this->empresa->no_html($txt);
    }

    private function buscar_subcuenta($aux)
    {
        /// desactivamos la plantilla HTML
        $this->template = FALSE;

        $json = [];
        $subcuenta = new subcuenta();
        $ejercicio = $this->ejercicio->get_by_fecha($this->today());
        foreach ($subcuenta->search_by_ejercicio($ejercicio->codejercicio, $aux) as $subc) {
            $json[] = [
                'value' => $subc->codsubcuenta . ' - ' . $subc->descripcion,
                'data' => $subc->codsubcuenta,
                'saldo' => $subc->saldo,
                'link' => $subc->url()
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(array('query' => $aux, 'suggestions' => $json));
    }
}

