<?php
/**
 * This file is part of FSFramework originally based on Facturascript 2017
 * Copyright (C) 2025 Javier Trujillo <mistertekcom@gmail.com>
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

namespace FacturaScripts\Plugins\business_data;

use FSFramework\Event\FSEventDispatcher;
use FSFramework\Event\TwigInitEvent;

/**
 * Initialization class for business_data plugin.
 * Provides:
 * - Twig global variables for currency (empresa_coddivisa, empresa_simbolo)
 * - Twig global variables for document translations (fs_factura, fs_albaran, etc.)
 * - Currency formatting tools
 */
class Init
{
    /**
     * @var \empresa|null
     */
    private $empresa = null;

    /**
     * @var array
     */
    private $divisas = [];

    public function init(): void
    {
        $dispatcher = FSEventDispatcher::getInstance();

        // Subscribe to Twig init to register global variables
        $dispatcher->addListener(TwigInitEvent::NAME, function (TwigInitEvent $event) {
            $this->registerTwigGlobals($event->getTwig());
            $this->registerTwigFunctions($event->getTwig());
        });
    }

    /**
     * Register global variables for Twig templates
     */
    private function registerTwigGlobals(\Twig\Environment $twig): void
    {
        // Load empresa data
        $this->loadEmpresa();

        // Currency variables
        $coddivisa = $this->empresa->coddivisa ?? 'EUR';
        $simbolo = $this->getSimboloDivisa($coddivisa);

        $twig->addGlobal('empresa_coddivisa', $coddivisa);
        $twig->addGlobal('empresa_simbolo', $simbolo);

        // Inject business_base.js script into head (relative path from web root)
        $twig->addGlobal('business_data_js', 'plugins/business_data/view/js/business_base.js');

        // Document translation variables
        $twig->addGlobal('fs_factura', defined('FS_FACTURA') ? FS_FACTURA : 'factura');
        $twig->addGlobal('fs_facturas', defined('FS_FACTURAS') ? FS_FACTURAS : 'facturas');
        $twig->addGlobal('fs_factura_simplificada', defined('FS_FACTURA_SIMPLIFICADA') ? FS_FACTURA_SIMPLIFICADA : 'factura simplificada');
        $twig->addGlobal('fs_factura_rectificativa', defined('FS_FACTURA_RECTIFICATIVA') ? FS_FACTURA_RECTIFICATIVA : 'factura rectificativa');
        $twig->addGlobal('fs_albaran', defined('FS_ALBARAN') ? FS_ALBARAN : 'albarán');
        $twig->addGlobal('fs_albaranes', defined('FS_ALBARANES') ? FS_ALBARANES : 'albaranes');
        $twig->addGlobal('fs_pedido', defined('FS_PEDIDO') ? FS_PEDIDO : 'pedido');
        $twig->addGlobal('fs_pedidos', defined('FS_PEDIDOS') ? FS_PEDIDOS : 'pedidos');
        $twig->addGlobal('fs_presupuesto', defined('FS_PRESUPUESTO') ? FS_PRESUPUESTO : 'presupuesto');
        $twig->addGlobal('fs_presupuestos', defined('FS_PRESUPUESTOS') ? FS_PRESUPUESTOS : 'presupuestos');
        $twig->addGlobal('fs_provincia', defined('FS_PROVINCIA') ? FS_PROVINCIA : 'provincia');
        $twig->addGlobal('fs_apartado', defined('FS_APARTADO') ? FS_APARTADO : 'apartado');
        $twig->addGlobal('fs_cifnif', defined('FS_CIFNIF') ? FS_CIFNIF : 'CIF/NIF');
        $twig->addGlobal('fs_iva', defined('FS_IVA') ? FS_IVA : 'IVA');
        $twig->addGlobal('fs_irpf', defined('FS_IRPF') ? FS_IRPF : 'IRPF');
        $twig->addGlobal('fs_numero2', defined('FS_NUMERO2') ? FS_NUMERO2 : 'número 2');
        $twig->addGlobal('fs_serie', defined('FS_SERIE') ? FS_SERIE : 'serie');
        $twig->addGlobal('fs_series', defined('FS_SERIES') ? FS_SERIES : 'series');

        // Number format variables
        $twig->addGlobal('fs_nf0', defined('FS_NF0') ? FS_NF0 : 2);
        $twig->addGlobal('fs_nf1', defined('FS_NF1') ? FS_NF1 : ',');
        $twig->addGlobal('fs_nf2', defined('FS_NF2') ? FS_NF2 : '.');
        $twig->addGlobal('fs_pos_divisa', defined('FS_POS_DIVISA') ? FS_POS_DIVISA : 'right');
    }

    /**
     * Register Twig functions for currency formatting
     */
    private function registerTwigFunctions(\Twig\Environment $twig): void
    {
        // simbolo_divisa function
        try {
            $twig->addFunction(new \Twig\TwigFunction('simbolo_divisa', function ($coddivisa = null) {
                return $this->getSimboloDivisa($coddivisa);
            }));
        } catch (\LogicException $e) {
            // Function already registered
        }

        // show_precio function
        try {
            $twig->addFunction(new \Twig\TwigFunction('show_precio', function ($precio = 0, $coddivisa = null, $simbolo = true, $dec = null) {
                return $this->showPrecio($precio, $coddivisa, $simbolo, $dec);
            }));
        } catch (\LogicException $e) {
            // Function already registered
        }

        // show_numero function
        try {
            $twig->addFunction(new \Twig\TwigFunction('show_numero', function ($num = 0, $decimales = null, $js = false) {
                return $this->showNumero($num, $decimales, $js);
            }));
        } catch (\LogicException $e) {
            // Function already registered
        }
    }

    /**
     * Load empresa data from database
     */
    private function loadEmpresa(): void
    {
        if ($this->empresa !== null) {
            return;
        }

        // Try to load empresa model
        if (class_exists('empresa')) {
            $empresaModel = new \empresa();
            $this->empresa = $empresaModel->get();
            if (!$this->empresa) {
                $this->empresa = new \stdClass();
                $this->empresa->coddivisa = 'EUR';
            }
        } else {
            $this->empresa = new \stdClass();
            $this->empresa->coddivisa = 'EUR';
        }

        // Load divisas
        $this->loadDivisas();
    }

    /**
     * Load all divisas from database
     */
    private function loadDivisas(): void
    {
        if (!empty($this->divisas)) {
            return;
        }

        if (class_exists('divisa')) {
            $divisaModel = new \divisa();
            $this->divisas = $divisaModel->all();
        } else {
            $this->divisas = [];
        }
    }

    /**
     * Get currency symbol for the given currency code
     * 
     * @param string|null $coddivisa Currency code, null for default
     * @return string
     */
    public function getSimboloDivisa($coddivisa = null): string
    {
        $this->loadEmpresa();

        if ($coddivisa === null || $coddivisa === false) {
            $coddivisa = $this->empresa->coddivisa ?? 'EUR';
        }

        foreach ($this->divisas as $divisa) {
            if ($divisa->coddivisa == $coddivisa) {
                return $divisa->simbolo;
            }
        }

        // Default symbols for common currencies
        $defaultSymbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'MXN' => 'MX$',
        ];

        return $defaultSymbols[$coddivisa] ?? '€';
    }

    /**
     * Format a price with currency symbol
     * 
     * @param float $precio
     * @param string|null $coddivisa
     * @param bool $simbolo
     * @param int|null $dec
     * @return string
     */
    public function showPrecio($precio = 0, $coddivisa = null, $simbolo = true, $dec = null): string
    {
        $this->loadEmpresa();

        if ($coddivisa === null || $coddivisa === false) {
            $coddivisa = $this->empresa->coddivisa ?? 'EUR';
        }

        $precio = $precio ?? 0;
        $dec = $dec ?? (defined('FS_NF0') ? FS_NF0 : 2);
        $nf1 = defined('FS_NF1') ? FS_NF1 : ',';
        $nf2 = defined('FS_NF2') ? FS_NF2 : '.';
        $posDivisa = defined('FS_POS_DIVISA') ? FS_POS_DIVISA : 'right';

        $formattedNumber = number_format($precio, $dec, $nf1, $nf2);

        if ($posDivisa == 'right') {
            if ($simbolo) {
                return $formattedNumber . ' ' . $this->getSimboloDivisa($coddivisa);
            }
            return $formattedNumber . ' ' . $coddivisa;
        }

        if ($simbolo) {
            return $this->getSimboloDivisa($coddivisa) . $formattedNumber;
        }

        return $coddivisa . ' ' . $formattedNumber;
    }

    /**
     * Format a number
     * 
     * @param float $num
     * @param int|null $decimales
     * @param bool $js
     * @return string
     */
    public function showNumero($num = 0, $decimales = null, $js = false): string
    {
        $num = $num ?? 0;
        $decimales = $decimales ?? (defined('FS_NF0') ? FS_NF0 : 2);

        if ($js) {
            return number_format($num, $decimales, '.', '');
        }

        $nf1 = defined('FS_NF1') ? FS_NF1 : ',';
        $nf2 = defined('FS_NF2') ? FS_NF2 : '.';

        return number_format($num, $decimales, $nf1, $nf2);
    }
}
