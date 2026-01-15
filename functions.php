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
 * Este archivo se ejecuta automáticamente cuando se activa el plugin.
 * Define funciones para verificar datos por defecto.
 * La verificación se ejecuta desde business_controller.
 */

/**
 * Verifica e inserta datos por defecto en las tablas básicas del sistema
 * @param fs_db2 $db Instancia de la base de datos
 */
function business_data_check_default_data($db)
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    
    // Verificar almacenes
    $data = $db->select("SELECT COUNT(*) as total FROM almacenes;");
    if ($data && intval($data[0]['total']) == 0) {
        $db->exec("INSERT INTO almacenes (codalmacen,nombre,poblacion,direccion,codpostal,telefono,fax,contacto) "
            . "VALUES ('ALG','ALMACEN GENERAL','','','','','','');");
    }
    
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
    
    // Verificar divisas
    $data = $db->select("SELECT COUNT(*) as total FROM divisas;");
    if ($data && intval($data[0]['total']) == 0) {
        $db->exec("INSERT INTO divisas (coddivisa,descripcion,tasaconv,tasaconv_compra,codiso,simbolo) VALUES "
            . "('EUR','EUROS','1','1','978','€'),"
            . "('USD','DÓLARES EE.UU.','1.129','1.129','840','\$'),"
            . "('GBP','LIBRAS ESTERLINAS','0.865','0.865','826','£'),"
            . "('MXN','PESOS (MXN)','23.3678','23.3678','484','MX\$');");
    }
    
    // Verificar países
    $data = $db->select("SELECT COUNT(*) as total FROM paises;");
    if ($data && intval($data[0]['total']) == 0) {
        $db->exec("INSERT INTO paises (codpais,codiso,nombre,bandera,validarprov) VALUES "
            . "('ESP','ES','España',NULL,TRUE),"
            . "('MEX','MX','México',NULL,FALSE),"
            . "('ARG','AR','Argentina',NULL,FALSE),"
            . "('USA','US','Estados Unidos',NULL,FALSE);");
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
