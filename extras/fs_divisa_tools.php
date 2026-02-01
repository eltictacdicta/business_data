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
 * Currency tools for formatting prices and converting between currencies.
 * This class is part of the business_data plugin and provides currency-related
 * functionality for business applications.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class fs_divisa_tools
{

    /**
     * Default currency code
     * @var string
     */
    private static $coddivisa;

    /**
     * Cached list of all currencies
     * @var array
     */
    private static $divisas;


    public function __construct($coddivisa = '')
    {
        if (!isset(self::$coddivisa)) {
            self::$coddivisa = $coddivisa;

            if (class_exists('divisa')) {
                $divisa_model = new divisa();
                self::$divisas = $divisa_model->all();
            } else {
                self::$divisas = [];
            }
        }
    }

    /**
     * Returns the currency symbol for the default currency
     * or for the specified currency code.
     * 
     * @param string $coddivisa
     * 
     * @return string
     */
    public function simbolo_divisa($coddivisa = FALSE)
    {
        if ($coddivisa === FALSE) {
            $coddivisa = self::$coddivisa;
        }

        foreach (self::$divisas as $divisa) {
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
     * Returns a formatted price string with the currency symbol
     * (or the default currency).
     * 
     * @param float  $precio
     * @param string $coddivisa
     * @param string $simbolo
     * @param int    $dec number of decimals
     * 
     * @return string
     */
    public function show_precio($precio = 0, $coddivisa = FALSE, $simbolo = TRUE, $dec = FS_NF0)
    {
        if ($coddivisa === FALSE) {
            $coddivisa = self::$coddivisa;
        }

        // Ensure values are never null to avoid deprecation in PHP 8+
        $precio = $precio ?? 0;
        $dec = $dec ?? 0;

        if (FS_POS_DIVISA == 'right') {
            if ($simbolo) {
                return number_format($precio, $dec, FS_NF1, FS_NF2) . ' ' . $this->simbolo_divisa($coddivisa);
            }

            return number_format($precio, $dec, FS_NF1, FS_NF2) . ' ' . $coddivisa;
        }

        if ($simbolo) {
            return $this->simbolo_divisa($coddivisa) . number_format($precio, $dec, FS_NF1, FS_NF2);
        }

        return $coddivisa . ' ' . number_format($precio, $dec, FS_NF1, FS_NF2);
    }

    /**
     * Returns a formatted number string using the default number format.
     * 
     * @param float   $num
     * @param int     $decimales
     * @param boolean $js
     * 
     * @return string
     */
    public function show_numero($num = 0, $decimales = FS_NF0, $js = FALSE)
    {
        // Ensure values are never null to avoid deprecation in PHP 8+
        $num = $num ?? 0;
        $decimales = $decimales ?? 0;

        if ($js) {
            return number_format($num, $decimales, '.', '');
        }

        return number_format($num, $decimales, FS_NF1, FS_NF2);
    }

    /**
     * Converts a price from euros to the company's default currency.
     * By default uses current conversion rates, but if coddivisa and tasaconv
     * are specified, those will be used instead.
     * 
     * @param float  $precio
     * @param string $coddivisa
     * @param float  $tasaconv
     * 
     * @return float
     */
    public function euro_convert($precio, $coddivisa = NULL, $tasaconv = NULL)
    {
        if (self::$coddivisa == 'EUR') {
            return $precio;
        }

        if ($coddivisa !== NULL && $tasaconv !== NULL) {
            if (self::$coddivisa == $coddivisa) {
                return $precio * $tasaconv;
            }

            $original = $precio * $tasaconv;
            return $this->divisa_convert($original, $coddivisa, self::$coddivisa);
        }

        return $this->divisa_convert($precio, 'EUR', self::$coddivisa);
    }

    /**
     * Converts a price from one currency to another
     * 
     * @param float  $precio
     * @param string $coddivisa_desde
     * @param string $coddivisa
     * 
     * @return float
     */
    public function divisa_convert($precio, $coddivisa_desde, $coddivisa)
    {
        if ($coddivisa_desde != $coddivisa) {
            $divisa = $divisa_desde = FALSE;

            // Search for currencies in the list
            foreach (self::$divisas as $div) {
                if ($div->coddivisa == $coddivisa) {
                    $divisa = $div;
                } else if ($div->coddivisa == $coddivisa_desde) {
                    $divisa_desde = $div;
                }
            }

            if ($divisa && $divisa_desde) {
                $precio = $precio / $divisa_desde->tasaconv * $divisa->tasaconv;
            }
        }

        return $precio;
    }

    /**
     * Get the default currency code
     * 
     * @return string
     */
    public function get_coddivisa()
    {
        return self::$coddivisa;
    }

    /**
     * Get all loaded currencies
     * 
     * @return array
     */
    public function get_divisas()
    {
        return self::$divisas;
    }
}
