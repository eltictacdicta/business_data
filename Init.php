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

namespace FSFramework\Plugins\business_data;

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
        $this->loadEmpresa();

        $coddivisa = $this->empresa->coddivisa ?? 'EUR';
        $twig->addGlobal('empresa_coddivisa', $coddivisa);
        $twig->addGlobal('empresa_simbolo', $this->getSimboloDivisa($coddivisa));
        $twig->addGlobal('business_data_js', 'plugins/business_data/view/js/business_base.js');

        $this->registerDocumentGlobals($twig);
        $this->registerNumberFormatGlobals($twig);
    }

    private function registerDocumentGlobals(\Twig\Environment $twig): void
    {
        $documentConstants = [
            'fs_factura' => ['FS_FACTURA', 'factura'],
            'fs_facturas' => ['FS_FACTURAS', 'facturas'],
            'fs_factura_simplificada' => ['FS_FACTURA_SIMPLIFICADA', 'factura simplificada'],
            'fs_factura_rectificativa' => ['FS_FACTURA_RECTIFICATIVA', 'factura rectificativa'],
            'fs_albaran' => ['FS_ALBARAN', 'albarán'],
            'fs_albaranes' => ['FS_ALBARANES', 'albaranes'],
            'fs_pedido' => ['FS_PEDIDO', 'pedido'],
            'fs_pedidos' => ['FS_PEDIDOS', 'pedidos'],
            'fs_presupuesto' => ['FS_PRESUPUESTO', 'presupuesto'],
            'fs_presupuestos' => ['FS_PRESUPUESTOS', 'presupuestos'],
            'fs_provincia' => ['FS_PROVINCIA', 'provincia'],
            'fs_apartado' => ['FS_APARTADO', 'apartado'],
            'fs_cifnif' => ['FS_CIFNIF', 'CIF/NIF'],
            'fs_iva' => ['FS_IVA', 'IVA'],
            'fs_irpf' => ['FS_IRPF', 'IRPF'],
            'fs_numero2' => ['FS_NUMERO2', 'número 2'],
            'fs_serie' => ['FS_SERIE', 'serie'],
            'fs_series' => ['FS_SERIES', 'series'],
        ];

        foreach ($documentConstants as $twigVar => [$constant, $default]) {
            $twig->addGlobal($twigVar, defined($constant) ? constant($constant) : $default);
        }
    }

    private function registerNumberFormatGlobals(\Twig\Environment $twig): void
    {
        $numberConstants = [
            'fs_nf0' => ['FS_NF0', 2],
            'fs_nf1' => ['FS_NF1', ','],
            'fs_nf2' => ['FS_NF2', '.'],
            'fs_pos_divisa' => ['FS_POS_DIVISA', 'right'],
        ];

        foreach ($numberConstants as $twigVar => [$constant, $default]) {
            $twig->addGlobal($twigVar, defined($constant) ? constant($constant) : $default);
        }
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
