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
require_once dirname(__DIR__, 3) . '/base/fs_default_items.php';
// Lazy model autoloading is disabled for plugin models in some contexts, so the
// sede entity is required explicitly (the guarded class_exists keeps this a
// no-op when it is already loaded).
if (!class_exists('empresa_sede', false)) {
    require_once dirname(__DIR__) . '/model/empresa_sede.php';
}

/**
 * Controlador de admin -> empresa.
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class admin_empresa extends fs_controller
{

    private const LOGO_PNG_PATH = 'images/logo.png';
    private const LOGO_JPG_PATH = 'images/logo.jpg';

    /** @var bool True when catalogo_core is active and catalog models are available. */
    public $catalog_available = false;

    public $almacen;
    public $cuenta_banco;
    public $divisa;
    public $ejercicio;
    public $forma_pago;
    public $impresion = array();
    public $serie;
    public $pais;

    /**
     * @var empresa_sede
     */
    public $empresa_sede;

    /**
     * Sedes de la empresa, cargadas para el panel.
     * @var list<empresa_sede>
     */
    public $sedes = array();

    /**
     * @var empresa
     */
    public $empresa;

    /**
     * @var fs_default_items
     */
    public $default_items;

    /**
     * @var string
     */
    public $logo = '';

    /**
     * Traducciones de documentos (FACTURA, ALBARAN, etc.)
     * @var array
     */
    public $traducciones = array();

    /**
     * Settings loaded from factura_pdf1 plugin (if active).
     * @var array<string, mixed>|null
     */
    public $pdf_settings = null;

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
        $this->loadPdfPluginSettings();

        $this->dispatchAction($fsvar);
        $this->loadSedes();
        $this->load_logo();

        $subcuenta = filter_input(INPUT_GET, 'subcuenta');
        if ($subcuenta) {
            $this->buscar_subcuenta($subcuenta);
        }
    }

    private function initializeModels(): void
    {
        $this->cuenta_banco = new cuenta_banco();
        $this->ejercicio = new ejercicio();
        $this->forma_pago = new forma_pago();
        $this->serie = new serie();
        $this->empresa_sede = new empresa_sede();

        $this->catalog_available = $this->isCatalogAvailable();
        if ($this->catalog_available) {
            $this->almacen = new almacen();
            $this->divisa = new divisa();
            $this->pais = new pais();
        }
    }

    private function isCatalogAvailable(): bool
    {
        return in_array('catalogo_core', $GLOBALS['plugins'] ?? [], true)
            && class_exists('divisa', false)
            && class_exists('almacen', false)
            && class_exists('pais', false);
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

    /**
     * Resuelve qué acción aplica a partir de los arrays de la petición.
     *
     * Función pura a propósito: `filter_input()` lee la request real y no se
     * puede sustituir en PHPUnit, así que el orden de precedencia (el riesgo
     * más grave de este cambio) se vuelve testeable sin base de datos, sesión
     * ni SAPI.
     *
     * Los marcadores de sede se evalúan ANTES de `nombre`: un formulario de
     * sede publica un campo `nombre` obligatorio y, sin esta precedencia, la
     * petición caería en `handleEmpresaSave()` y sobrescribiría la fila de la
     * empresa base.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $get
     */
    public static function resolveAction(array $post, array $get): string
    {
        if (self::posted($post, 'save_sede')) {
            return 'sede_save';
        }

        if (self::posted($post, 'delete_sede')) {
            return 'sede_delete';
        }

        if (self::posted($post, 'nombre')) {
            return 'empresa';
        }

        if (self::posted($post, 'logo')) {
            return 'logo';
        }

        if (self::posted($get, 'delete_logo')) {
            return 'delete_logo';
        }

        // Asimetría heredada, preservada a propósito: el despacho lee
        // `delete_cuenta` de POST mientras handleDeleteCuenta() lo lee de GET.
        // No "corregir" aquí.
        if (self::posted($post, 'delete_cuenta')) {
            return 'cuenta_delete';
        }

        if (self::posted($post, 'iban')) {
            return 'cuenta_save';
        }

        return 'none';
    }

    /**
     * Réplica de la veracidad de `filter_input(INPUT_*, $key)`: `NULL` cuando
     * la clave no existe y `FALSE` cuando el valor es un array. Mantiene el
     * comportamiento heredado sin depender de la SAPI real.
     *
     * @param array<string, mixed> $values
     */
    private static function posted(array $values, string $key): bool
    {
        if (!array_key_exists($key, $values) || is_array($values[$key])) {
            return false;
        }

        return (bool) $values[$key];
    }

    private function dispatchAction($fsvar): void
    {
        switch (self::resolveAction($_POST, $_GET)) {
            case 'sede_save':
                $this->handleSaveSede();
                break;

            case 'sede_delete':
                $this->handleDeleteSede();
                break;

            case 'empresa':
                $this->handleEmpresaSave($fsvar);
                break;

            case 'logo':
                $this->cambiar_logo();
                break;

            case 'delete_logo':
                $this->delete_logo();
                break;

            case 'cuenta_delete':
                $this->handleDeleteCuenta();
                break;

            case 'cuenta_save':
                $this->handleSaveCuenta();
                break;

            default:
                $this->fix_logo();
                break;
        }
    }

    private function loadSedes(): void
    {
        $this->sedes = array();
        if ($this->empresa_sede instanceof empresa_sede) {
            $this->sedes = $this->empresa_sede->all();
        }
    }

    /**
     * Proyecta un POST a los campos editables de una sede. Se limita a la
     * lista editable de la entidad para que un formulario de sede nunca pueda
     * escribir los campos de la empresa base.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function sedeFieldsFromPost(array $post): array
    {
        $fields = array();
        foreach (empresa_sede::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $post)) {
                $fields[$field] = $post[$field];
            }
        }

        return $fields;
    }

    private function handleSaveSede(): void
    {
        // Explicit CSRF guard. The page gate (pre_private_core() ->
        // validateCsrf()) only blocks invalid tokens in strict mode; with
        // FS_CSRF_SOFT=true it returns TRUE and private_core() still runs. This
        // check rejects the write in every mode, mirroring the tpvmod_settings
        // mapping path. The sede form is a POST form carrying csrf_field().
        if (!$this->isCsrfValid()) {
            $this->new_error_msg('Token de seguridad inválido.');
            return;
        }

        $codsede = filter_input(INPUT_POST, 'codsede');
        $sede = $codsede ? $this->empresa_sede->get($codsede) : new empresa_sede();
        if (!$sede instanceof empresa_sede) {
            $this->new_error_msg('Sede no encontrada.');
            return;
        }

        foreach (self::sedeFieldsFromPost($_POST) as $field => $value) {
            $sede->{$field} = $value;
        }

        if ($sede->save()) {
            $this->new_message('Sede guardada correctamente.');
        } else {
            $this->new_error_msg('Imposible guardar la sede.');
        }
    }

    private function handleDeleteSede(): void
    {
        // Explicit CSRF guard, same rationale as handleSaveSede().
        //
        // Unlike the pre-existing `delete_cuenta` sibling (a GET link with no
        // token, handled by handleDeleteCuenta()), `delete_sede` is triggered
        // by a submit button inside the per-sede POST form that carries
        // {{ csrf_field() }} (view/block/admin_empresa_sedes.html.twig). The
        // trigger is therefore genuinely token-carrying and this handler can
        // reject an invalid token directly; no GET link is involved.
        if (!$this->isCsrfValid()) {
            $this->new_error_msg('Token de seguridad inválido.');
            return;
        }

        $codsede = filter_input(INPUT_POST, 'delete_sede');
        $sede = $codsede ? $this->empresa_sede->get($codsede) : false;
        if (!$sede instanceof empresa_sede) {
            $this->new_error_msg('Sede no encontrada.');
            return;
        }

        if ($sede->delete()) {
            $this->new_message('Sede eliminada correctamente.');
        } else {
            $this->new_error_msg('Imposible eliminar la sede.');
        }
    }

    private function handleEmpresaSave($fsvar): void
    {
        $this->applyEmpresaFields();

        if ($this->empresa->save()) {
            $this->save_codalmacen(filter_input(INPUT_POST, 'codalmacen'));
            $this->save_codpago(filter_input(INPUT_POST, 'codpago'));
            $this->new_message('Datos guardados correctamente.');
            $this->initialize_default_items();
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }

        $this->savePrintConfig($fsvar);
        $this->savePdfPluginSettings();
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

    private function savePrintConfig($fsvar): void
    {
        $this->impresion['print_ref'] = (filter_input(INPUT_POST, 'print_ref') ? 1 : 0);
        $this->impresion['print_dto'] = (filter_input(INPUT_POST, 'print_dto') ? 1 : 0);
        $this->impresion['print_alb'] = (filter_input(INPUT_POST, 'print_alb') ? 1 : 0);
        $this->impresion['print_formapago'] = (filter_input(INPUT_POST, 'print_formapago') ? 1 : 0);
        $fsvar->array_save($this->impresion);
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
     * Check whether the factura_pdf1 plugin is both loaded and active.
     */
    private function isPdfPluginActive(): bool
    {
        return in_array('factura_pdf1', $GLOBALS['plugins'] ?? [], true)
            && class_exists(\FSFramework\Plugins\factura_pdf1\Services\SettingsService::class, true);
    }

    /**
     * Load factura_pdf1 settings when the plugin is active.
     */
    private function loadPdfPluginSettings(): void
    {
        if (!$this->isPdfPluginActive()) {
            return;
        }

        $service = new \FSFramework\Plugins\factura_pdf1\Services\SettingsService();
        $this->pdf_settings = $service->load();
    }

    /**
     * Save factura_pdf1 settings submitted from the print section.
     */
    private function savePdfPluginSettings(): void
    {
        if ($this->pdf_settings === null) {
            return;
        }
        if (!$this->isPdfPluginActive()) {
            return;
        }

        $disposicion = filter_input(INPUT_POST, 'disposicion_cabecera', FILTER_VALIDATE_INT);
        if ($disposicion !== null && $disposicion !== false) {
            $this->pdf_settings['disposicion_cabecera'] = $disposicion;
            $service = new \FSFramework\Plugins\factura_pdf1\Services\SettingsService();
            $service->save($this->pdf_settings);
        }
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

        if ($changed && !self::persistTraducciones($this->traducciones)) {
            // Never report success when nothing was written: the previous guard
            // used the autoloading existence check, which is FALSE on the
            // modern entry path, so the write was silently skipped.
            $this->new_error_msg('No se pudieron guardar las traducciones de los documentos.');
        }
    }

    /**
     * Persiste las traducciones de documentos en config2.php.
     *
     * Seam puro a propósito, igual que `resolveAction()`: `save_traducciones()`
     * lee de `filter_input()`, que no se puede sustituir en PHPUnit, así que el
     * camino real de escritura se vuelve testeable desde un entry point limpio
     * sin SAPI, sesión ni base de datos.
     *
     * El guard de `fs_settings` NO se duplica aquí: vive en el único accesor
     * del plugin, `empresa_sede::settings()`, que carga la clase bajo demanda.
     * Comprobar su existencia con el formulario de autoload devuelve FALSE en
     * el entry path moderno y saltaba la escritura en silencio.
     *
     * @param array<string, string> $traducciones
     * @return bool true cuando config2 se ha escrito
     */
    public static function persistTraducciones(array $traducciones): bool
    {
        $settings = empresa_sede::settings();

        foreach ($traducciones as $key => $value) {
            $settings->set($key, $value);
        }

        return $settings->save();
    }
}

