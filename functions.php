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

/**
 * Verifica e inserta datos por defecto en las tablas propias de business_data.
 * Catálogo (divisas, almacenes, países) lo siembra catalogo_core al activarse.
 *
 * @param fs_db2 $db Instancia de la base de datos
 */
function business_data_check_default_data($db)
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    
    // Verificar series
    $data = $db->select("SELECT COUNT(*) as total FROM series;");
    if ($data && intval($data[0]['total']) == 0) {
        $db->exec("INSERT INTO series (codserie,descripcion,siniva,irpf) VALUES "
            . "('A','SERIE A',FALSE,'0'),('R','RECTIFICATIVAS',FALSE,'0');");
    }
    
    // Verificar formas de pago
    $data = $db->select("SELECT COUNT(*) as total FROM formaspago;");
    if ($data && intval($data[0]['total']) == 0) {
        $db->exec("INSERT INTO formaspago (codpago,descripcion,genrecibos,codcuenta,domiciliado,vencimiento) VALUES "
            . "('CONT','Al contado','Pagados',NULL,FALSE,'+0day'),"
            . "('TRANS','Transferencia bancaria','Emitidos',NULL,FALSE,'+1month'),"
            . "('TARJETA','Tarjeta de crédito','Pagados',NULL,FALSE,'+0day'),"
            . "('PAYPAL','PayPal','Pagados',NULL,FALSE,'+0day');");
    }
    
    // Verificar empresa (si no existe, crear una por defecto)
    $data = $db->select("SELECT COUNT(*) as total FROM empresa;");
    if ($data && intval($data[0]['total']) == 0) {
        $db->exec("INSERT INTO empresa (nombre,nombrecorto,cifnif,administrador,direccion,"
            . "codalmacen,codserie,codpago,coddivisa,codpais) VALUES "
            . "('Mi Empresa','MI EMP','00000000A','Administrador','Dirección de la empresa',"
            . "'ALG','A','CONT','EUR','ESP');");
    }
}
