<?php
/**
 * This file is part of FSFramework originally based on Facturascript 2017
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
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

namespace Tests\BusinessData;

use PHPUnit\Framework\TestCase;

class BusinessDataModelTest extends TestCase
{
    protected function setUp(): void
    {
        global $plugins;
        $plugins = [];

        require_once FS_FOLDER . '/base/fs_model.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa.php';
        require_once FS_FOLDER . '/plugins/business_data/model/ejercicio.php';
        require_once FS_FOLDER . '/plugins/business_data/model/serie.php';
        require_once FS_FOLDER . '/plugins/catalogo_core/model/core/divisa.php';
    }

    public function testEmpresaHydratesFromArray(): void
    {
        $model = new \empresa([
            'id' => 1,
            'cifnif' => 'B12345678',
            'nombre' => 'Empresa Test',
            'nombrecorto' => 'Test',
            'administrador' => 'Admin',
            'direccion' => 'Calle Test 123',
            'codpostal' => '28001',
            'ciudad' => 'Madrid',
            'provincia' => 'Madrid',
            'codpais' => 'ESP',
            'email' => 'test@empresa.com',
            'codejercicio' => '2026',
            'codalmacen' => 'ALM1',
            'coddivisa' => 'EUR',
            'codserie' => 'A',
            'codpago' => 'CONT',
            'codcuentarem' => '430',
        ]);

        $this->assertSame('B12345678', $model->cifnif);
        $this->assertSame('Empresa Test', $model->nombre);
        $this->assertSame('test@empresa.com', $model->email);
        $this->assertSame('2026', $model->codejercicio);
        $this->assertSame('ALM1', $model->codalmacen);
    }

    public function testEmpresaDefaultState(): void
    {
        $model = new \empresa(false);

        $this->assertIsObject($model);
    }

    public function testEjercicioHydratesFromArray(): void
    {
        $model = new \ejercicio([
            'codejercicio' => '2026',
            'nombre' => 'Ejercicio 2026',
            'fechainicio' => '2026-01-01',
            'fechafin' => '2026-12-31',
            'estado' => 'ABIERTO',
            'idasientocierre' => null,
            'idasientopyg' => null,
            'idasientoapertura' => null,
            'plancontable' => '08',
            'longsubcuenta' => 0,
        ]);

        $this->assertSame('2026', $model->codejercicio);
        $this->assertSame('Ejercicio 2026', $model->nombre);
        $this->assertSame('ABIERTO', $model->estado);
    }

    public function testEjercicioDefaultState(): void
    {
        $model = new \ejercicio(false);

        $this->assertIsObject($model);
        $this->assertNull($model->codejercicio);
    }

    public function testSerieHydratesFromArray(): void
    {
        $model = new \serie([
            'codserie' => 'A',
            'descripcion' => 'Serie A Standard',
            'siniva' => false,
            'irpf' => 15.0,
            'codejercicio' => '2026',
        ]);

        $this->assertSame('A', $model->codserie);
        $this->assertSame('Serie A Standard', $model->descripcion);
        $this->assertFalse($model->siniva);
        $this->assertSame('2026', $model->codejercicio);
    }

    public function testSerieDefaultState(): void
    {
        $model = new \serie(false);

        $this->assertIsObject($model);
        $this->assertNull($model->codserie);
    }

    public function testDivisaProxyHydratesFromArray(): void
    {
        $model = new \divisa([
            'coddivisa' => 'EUR',
            'descripcion' => 'Euro',
            'codiso' => 'EUR',
            'simbolo' => '€',
            'tasaconv' => '1.0000',
        ]);

        $this->assertSame('EUR', $model->coddivisa);
        $this->assertSame('Euro', $model->descripcion);
        $this->assertSame('€', $model->simbolo);
    }

    public function testDivisaProxyDefaultState(): void
    {
        $model = new \divisa(false);

        $this->assertIsObject($model);
    }
}
