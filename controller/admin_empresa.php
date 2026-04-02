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
if (!class_exists('fs_divisa_tools', false) && file_exists(__DIR__ . '/../extras/fs_divisa_tools.php')) {
    require_once __DIR__ . '/../extras/fs_divisa_tools.php';
}

require_once 'base/fs_default_items.php';

/**
 * Controlador de admin -> empresa.
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class admin_empresa extends fs_controller
{

    private const DEFAULT_MAIL_BODY = "Buenos días, le adjunto su #DOCUMENTO#.\n#FIRMA#";
    private const LOGO_PNG_PATH = 'images/logo.png';
    private const LOGO_JPG_PATH = 'images/logo.jpg';

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

    /**
     * Traducciones de documentos (FACTURA, ALBARAN, etc.)
     * @var array
     */
    public $traducciones = array();

    public function __construct()
    {
        /// Check for undefined constants to prevent crashes in standalone/testing contexts
        if (!defined('FS_MYDOCS')) {
            define('FS_MYDOCS', '');
        }
        if (!defined('FS_TMP_NAME')) {
            define('FS_TMP_NAME', '');
        }

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

        if (!isset($this->empresa) || !$this->empresa || empty($this->empresa->id)) {
            return;
        }

        $mappings = [
            'codejercicio' => 'set_codejercicio',
            'codalmacen' => 'set_codalmacen',
            'codpago' => 'set_codpago',
            'codpais' => 'set_codpais',
            'codserie' => 'set_codserie',
            'coddivisa' => 'set_coddivisa',
        ];

        foreach ($mappings as $property => $setter) {
            if (!empty($this->empresa->{$property})) {
                $this->default_items->{$setter}($this->empresa->{$property});
            }
        }
    }

    protected function private_core()
    {
        $this->initialize_empresa();
        $this->initialize_default_items();
        $this->initializeModels();

        $fsvar = new fs_var();
        $this->loadConfigDefaults($fsvar);
        $this->loadTraducciones();
        $this->initializeDivisaTools();

        $this->dispatchAction($fsvar);
        $this->load_logo();

        $subcuenta = filter_input(INPUT_GET, 'subcuenta');
        if ($subcuenta) {
            $this->buscar_subcuenta($subcuenta);
        }
    }

    private function initializeModels(): void
    {
        $this->almacen = new almacen();
        $this->cuenta_banco = new cuenta_banco();
        $this->divisa = new divisa();
        $this->ejercicio = new ejercicio();
        $this->forma_pago = new forma_pago();
        $this->serie = new serie();
        $this->pais = new pais();
    }

    private function loadConfigDefaults($fsvar): void
    {
        $this->impresion = array(
            'print_ref' => '1',
            'print_dto' => '1',
            'print_alb' => '0',
            'print_formapago' => '1'
        );
        $this->impresion = $fsvar->array_get($this->impresion, FALSE);

        $this->email_plantillas = array(
            'mail_factura' => self::DEFAULT_MAIL_BODY,
            'mail_albaran' => self::DEFAULT_MAIL_BODY,
            'mail_pedido' => self::DEFAULT_MAIL_BODY,
            'mail_presupuesto' => self::DEFAULT_MAIL_BODY,
        );
        $this->email_plantillas = $fsvar->array_get($this->email_plantillas, FALSE);
    }

    private function loadTraducciones(): void
    {
        $defaults = [
            'FACTURA' => 'factura',
            'FACTURAS' => 'facturas',
            'FACTURA_SIMPLIFICADA' => 'factura simplificada',
            'FACTURA_RECTIFICATIVA' => 'factura rectificativa',
            'ALBARAN' => 'albarán',
            'ALBARANES' => 'albaranes',
            'PEDIDO' => 'pedido',
            'PEDIDOS' => 'pedidos',
            'PRESUPUESTO' => 'presupuesto',
            'PRESUPUESTOS' => 'presupuestos',
            'PROVINCIA' => 'provincia',
            'APARTADO' => 'apartado',
            'CIFNIF' => 'CIF/NIF',
            'IVA' => 'IVA',
            'IRPF' => 'IRPF',
            'NUMERO2' => 'número 2',
            'SERIE' => 'serie',
            'SERIES' => 'series',
        ];

        foreach ($defaults as $key => $default) {
            $constant = 'FS_' . $key;
            $this->traducciones[$key] = defined($constant) ? constant($constant) : $default;
        }
    }

    private function initializeDivisaTools(): void
    {
        $coddivisa = ($this->empresa && $this->empresa->coddivisa) ? $this->empresa->coddivisa : 'EUR';
        $this->divisa_tools = new fs_divisa_tools($coddivisa);
    }

    /**
     * Establece un almacén como predeterminado para este usuario.
     * @param string $cod el código del almacén
     */
    protected function save_codalmacen($cod)
    {
        $this->setPreferenceCookie('default_almacen', $cod);
        $this->default_items->set_codalmacen($cod);
    }

    /**
     * Establece un impuesto (IVA) como predeterminado para este usuario.
     * @param string $cod el código del impuesto
     */
    protected function save_codimpuesto($cod)
    {
        $this->setPreferenceCookie('default_impuesto', $cod);
        $this->default_items->set_codimpuesto($cod);
    }

    /**
     * Establece una forma de pago como predeterminada para este usuario.
     * @param string $cod el código de la forma de pago
     */
    protected function save_codpago($cod)
    {
        $this->setPreferenceCookie('default_formapago', $cod);
        $this->default_items->set_codpago($cod);
    }

    private function dispatchAction($fsvar): void
    {
        if (filter_input(INPUT_POST, 'nombre')) {
            $this->handleEmpresaSave($fsvar);
        } else if (filter_input(INPUT_POST, 'logo')) {
            $this->cambiar_logo();
        } else if (filter_input(INPUT_GET, 'delete_logo')) {
            $this->delete_logo();
        } else if (filter_input(INPUT_POST, 'delete_cuenta')) {
            $this->handleDeleteCuenta();
        } else if (filter_input(INPUT_POST, 'iban')) {
            $this->handleSaveCuenta();
        } else {
            $this->fix_logo();
        }
    }

    private function handleEmpresaSave($fsvar): void
    {
        $this->applyEmpresaFields();
        $this->applyEmailConfig();

        if ($this->empresa->save()) {
            $this->save_codalmacen(filter_input(INPUT_POST, 'codalmacen'));
            $this->save_codpago(filter_input(INPUT_POST, 'codpago'));
            $this->new_message('Datos guardados correctamente.');
            $this->mail_test();
            $this->initialize_default_items();
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }

        $this->savePrintConfig($fsvar);
        $this->saveEmailTemplates($fsvar);
        $this->save_traducciones();
    }

    private function applyEmpresaFields(): void
    {
        $fields = [
            'nombre', 'nombrecorto', 'cifnif', 'administrador', 'codpais',
            'provincia', 'ciudad', 'direccion', 'codpostal', 'apartado',
            'telefono', 'fax', 'web', 'email', 'lema', 'horario',
            'codejercicio', 'codserie', 'coddivisa', 'codpago', 'codalmacen',
            'pie_factura',
        ];
        foreach ($fields as $field) {
            $value = filter_input(INPUT_POST, $field);
            if ($value !== NULL) {
                $this->empresa->{$field} = $value;
            }
        }
        $this->empresa->contintegrada = (bool) filter_input(INPUT_POST, 'contintegrada');
        $this->empresa->recequivalencia = (bool) filter_input(INPUT_POST, 'recequivalencia');
    }

    private function applyEmailConfig(): void
    {
        $this->empresa->email_config['mail_password'] = filter_input(INPUT_POST, 'mail_password');
        $this->empresa->email_config['mail_bcc'] = filter_input(INPUT_POST, 'mail_bcc');
        $this->empresa->email_config['mail_firma'] = filter_input(INPUT_POST, 'mail_firma');
        $this->empresa->email_config['mail_mailer'] = filter_input(INPUT_POST, 'mail_mailer');
        $this->empresa->email_config['mail_host'] = filter_input(INPUT_POST, 'mail_host');
        $this->empresa->email_config['mail_port'] = intval(filter_input(INPUT_POST, 'mail_port'));
        $this->empresa->email_config['mail_enc'] = strtolower(filter_input(INPUT_POST, 'mail_enc'));
        $this->empresa->email_config['mail_user'] = filter_input(INPUT_POST, 'mail_user');
        $this->empresa->email_config['mail_low_security'] = (bool) filter_input(INPUT_POST, 'mail_low_security');
    }

    private function savePrintConfig($fsvar): void
    {
        $this->impresion['print_ref'] = (filter_input(INPUT_POST, 'print_ref') ? 1 : 0);
        $this->impresion['print_dto'] = (filter_input(INPUT_POST, 'print_dto') ? 1 : 0);
        $this->impresion['print_alb'] = (filter_input(INPUT_POST, 'print_alb') ? 1 : 0);
        $this->impresion['print_formapago'] = (filter_input(INPUT_POST, 'print_formapago') ? 1 : 0);
        $fsvar->array_save($this->impresion);
    }

    private function saveEmailTemplates($fsvar): void
    {
        $this->email_plantillas['mail_factura'] = filter_input(INPUT_POST, 'mail_factura');
        $this->email_plantillas['mail_albaran'] = filter_input(INPUT_POST, 'mail_albaran');
        if (filter_input(INPUT_POST, 'mail_pedido')) {
            $this->email_plantillas['mail_pedido'] = filter_input(INPUT_POST, 'mail_pedido');
            $this->email_plantillas['mail_presupuesto'] = filter_input(INPUT_POST, 'mail_presupuesto');
        }
        $fsvar->array_save($this->email_plantillas);
    }

    private function handleDeleteCuenta(): void
    {
        $cuenta = $this->cuenta_banco->get(filter_input(INPUT_GET, 'delete_cuenta'));
        if (!$cuenta) {
            $this->new_error_msg('Cuenta bancaria no encontrada.');
            return;
        }

        if ($cuenta->delete()) {
            $this->new_message('Cuenta bancaria eliminada correctamente.');
        } else {
            $this->new_error_msg('Imposible eliminar la cuenta bancaria.');
        }
    }

    private function handleSaveCuenta(): void
    {
        $codcuenta = filter_input(INPUT_POST, 'codcuenta');
        $cuentab = $codcuenta ? $this->cuenta_banco->get($codcuenta) : new cuenta_banco();

        $cuentab->descripcion = filter_input(INPUT_POST, 'descripcion');
        $cuentab->iban = filter_input(INPUT_POST, 'iban');
        $cuentab->swift = filter_input(INPUT_POST, 'swift');
        $cuentab->codsubcuenta = filter_input(INPUT_POST, 'codsubcuenta') ?: NULL;

        if ($cuentab->save()) {
            $this->new_message('Cuenta bancaria guardada correctamente.');
        } else {
            $this->new_error_msg('Imposible guardar la cuenta bancaria.');
        }
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
            rename('tmp/' . FS_TMP_NAME . 'logo.png', FS_MYDOCS . self::LOGO_PNG_PATH);
        } else if (file_exists('tmp/' . FS_TMP_NAME . 'logo.jpg')) {
            rename('tmp/' . FS_TMP_NAME . 'logo.jpg', FS_MYDOCS . self::LOGO_JPG_PATH);
        }
    }

    private function load_logo()
    {
        $this->logo = '';
        if (file_exists(FS_MYDOCS . self::LOGO_PNG_PATH)) {
            $this->logo = self::LOGO_PNG_PATH;
        } else if (file_exists(FS_MYDOCS . self::LOGO_JPG_PATH)) {
            $this->logo = self::LOGO_JPG_PATH;
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
                copy($_FILES['fimagen']['tmp_name'], FS_MYDOCS . self::LOGO_PNG_PATH);
            } else {
                copy($_FILES['fimagen']['tmp_name'], FS_MYDOCS . self::LOGO_JPG_PATH);
            }

            $this->new_message('Logotipo guardado correctamente.');
        }
    }

    private function delete_logo()
    {
        if (file_exists(FS_MYDOCS . self::LOGO_PNG_PATH)) {
            unlink(FS_MYDOCS . self::LOGO_PNG_PATH);
            $this->new_message('Logotipo borrado correctamente.');
        } else if (file_exists(FS_MYDOCS . self::LOGO_JPG_PATH)) {
            unlink(FS_MYDOCS . self::LOGO_JPG_PATH);
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

    /**
     * Guarda las traducciones de documentos en config2.php
     */
    private function save_traducciones()
    {
        $traducciones_keys = [
            'FACTURA', 'FACTURAS', 'FACTURA_SIMPLIFICADA', 'FACTURA_RECTIFICATIVA',
            'ALBARAN', 'ALBARANES', 'PEDIDO', 'PEDIDOS', 'PRESUPUESTO', 'PRESUPUESTOS',
            'PROVINCIA', 'APARTADO', 'CIFNIF', 'IVA', 'IRPF', 'NUMERO2', 'SERIE', 'SERIES'
        ];

        $changed = false;
        foreach ($traducciones_keys as $key) {
            $value = filter_input(INPUT_POST, $key);
            if ($value !== null && $value !== '') {
                $this->traducciones[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            // Guardar en config2.php usando fs_settings
            if (class_exists('fs_settings')) {
                $settings = new fs_settings();
                foreach ($this->traducciones as $key => $value) {
                    $settings->set($key, $value);
                }
                $settings->save();
            }
        }
    }
}

