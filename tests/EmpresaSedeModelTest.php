<?php
/**
 * This file is part of FSFramework originally based on Facturascript 2017
 * Copyright (C) 2026 FSFramework Team
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
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Model-level contract tests for the multi-row `empresa_sede` entity
 * (WU-1, design cases 1-9).
 *
 * The model is exercised DB-free: an anonymous subclass bypasses the
 * `fs_model` constructor and injects a spy database.
 */
final class EmpresaSedeModelSpyDb
{
    /** @var list<string> */
    public array $execStatements = [];

    /** @var list<string> */
    public array $selectStatements = [];

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @var list<list<array<string, mixed>>> */
    public array $selectQueue = [];

    /** @var list<string> */
    public array $sqlToIntCalls = [];

    public $lastId = 1;

    public function var2str($val)
    {
        if ($val === null) {
            return 'NULL';
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        if (is_int($val) || is_float($val)) {
            return (string) $val;
        }

        return "'" . addslashes((string) $val) . "'";
    }

    public function escape_string(string $str): string
    {
        return addslashes($str);
    }

    public function sql_to_int(string $col): string
    {
        $this->sqlToIntCalls[] = $col;

        return 'CAST(' . $col . ' AS INTEGER)';
    }

    public function select($sql, $params = [])
    {
        $this->selectStatements[] = trim((string) $sql);

        if ($this->selectQueue !== []) {
            return array_shift($this->selectQueue);
        }

        return $this->rows;
    }

    public function exec($sql, $transaction = null, $params = [], $batch = false)
    {
        $this->execStatements[] = trim((string) $sql);

        return true;
    }

    public function lastval()
    {
        return $this->lastId;
    }
}

final class EmpresaSedeModelTest extends TestCase
{
    private static bool $baseLoaded = false;

    /** @var list<string> */
    private const EDITABLE_FIELDS = [
        'descripcion',
        'nombre',
        'cifnif',
        'direccion',
        'apartado',
        'codpostal',
        'ciudad',
        'provincia',
        'codpais',
        'email',
        'web',
        'telefono',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$baseLoaded) {
            return;
        }

        require_once FS_FOLDER . '/base/fs_model.php';
        require_once FS_FOLDER . '/base/fs_core_log.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
        self::$baseLoaded = true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        $this->resetFsModelCheckedTables();
    }

    protected function tearDown(): void
    {
        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        parent::tearDown();
    }

    // =====================================================================
    // Case 1 — hydration
    // =====================================================================

    public function testHydratesFromFullRowAndDefaultsEveryOptionalKey(): void
    {
        $sede = $this->makeSede([
            'codsede' => 'S1',
            'descripcion' => 'Sede Norte',
            'nombre' => 'Empresa Norte SL',
            'cifnif' => 'B12345678',
            'direccion' => 'Calle Norte 1',
            'apartado' => '12',
            'codpostal' => '48001',
            'ciudad' => 'Bilbao',
            'provincia' => 'Bizkaia',
            'codpais' => 'ESP',
            'email' => 'norte@example.com',
            'web' => 'https://norte.example.com',
            'telefono' => '600000000',
        ]);

        $this->assertSame('S1', $sede->codsede);
        $this->assertSame('Sede Norte', $sede->descripcion);
        $this->assertSame('Empresa Norte SL', $sede->nombre);
        $this->assertSame('B12345678', $sede->cifnif);
        $this->assertSame('Calle Norte 1', $sede->direccion);
        $this->assertSame('12', $sede->apartado);
        $this->assertSame('48001', $sede->codpostal);
        $this->assertSame('Bilbao', $sede->ciudad);
        $this->assertSame('Bizkaia', $sede->provincia);
        $this->assertSame('ESP', $sede->codpais);
        $this->assertSame('norte@example.com', $sede->email);
        $this->assertSame('https://norte.example.com', $sede->web);
        $this->assertSame('600000000', $sede->telefono);

        $minimal = $this->makeSede(['codsede' => 'S2', 'nombre' => 'Minimal']);
        $this->assertSame('', $minimal->descripcion);
        $this->assertSame('', $minimal->cifnif);
        $this->assertSame('', $minimal->direccion);
        $this->assertSame('', $minimal->apartado);
        $this->assertSame('', $minimal->codpostal);
        $this->assertSame('', $minimal->ciudad);
        $this->assertSame('', $minimal->provincia);
        $this->assertSame('', $minimal->codpais);
        $this->assertSame('', $minimal->email);
        $this->assertSame('', $minimal->web);
        $this->assertSame('', $minimal->telefono);

        $empty = $this->makeSede([]);
        $this->assertNull($empty->codsede);
        $this->assertSame('', $empty->nombre);
    }

    // =====================================================================
    // Cases 2 + 3 — validation and sanitization
    // =====================================================================

    public function testTestRejectsBlankNombre(): void
    {
        $sede = $this->makeSede(['codsede' => 'S1', 'nombre' => '']);

        $this->assertFalse($sede->test());
        $this->assertNotSame([], $sede->errors);
    }

    public function testTestSanitizesEveryEditableField(): void
    {
        $row = ['codsede' => 'S1'];
        foreach (self::EDITABLE_FIELDS as $field) {
            if ($field === 'codsede') {
                continue;
            }
            $row[$field] = '<script>alert(1)</script>';
        }

        $sede = $this->makeSede($row);

        $this->assertTrue($sede->test());
        foreach (self::EDITABLE_FIELDS as $field) {
            $this->assertStringNotContainsString(
                '<',
                (string) $sede->{$field},
                'field ' . $field . ' must be no_html-sanitized'
            );
        }
    }

    // =====================================================================
    // Case 4 — XML schema inventory
    // =====================================================================

    public function testXmlDeclaresExactlyTheNarrowedColumns(): void
    {
        $path = FS_FOLDER . '/plugins/business_data/model/table/empresa_sedes.xml';
        $this->assertFileExists($path);

        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml);

        $columns = [];
        foreach ($xml->columna as $columna) {
            $columns[] = (string) $columna->nombre;
        }

        $expected = [
            'codsede',
            'descripcion',
            'nombre',
            'cifnif',
            'direccion',
            'apartado',
            'codpostal',
            'ciudad',
            'provincia',
            'codpais',
            'email',
            'web',
            'telefono',
        ];

        sort($columns);
        $sortedExpected = $expected;
        sort($sortedExpected);
        $this->assertSame($sortedExpected, $columns, 'the column set must be exactly the 13 narrowed columns');

        foreach (['fax', 'lema', 'pie_factura', 'horario', 'nombrecorto'] as $absent) {
            $this->assertNotContains($absent, $columns, $absent . ' has no PDF consumer and must be absent');
        }

        $raw = (string) file_get_contents($path);
        $this->assertMatchesRegularExpression('/PRIMARY KEY\s*\(\s*codsede\s*\)/i', $raw);
        $this->assertMatchesRegularExpression('/<nombre>codsede<\/nombre>\s*<tipo>character varying\(6\)<\/tipo>/i', $raw);

        // The XML filename must equal the table name.
        $this->assertSame('empresa_sedes', basename($path, '.xml'));
        $this->assertSame('empresa_sedes', $this->makeSede([])->table_name());
    }

    // =====================================================================
    // Case 5 — generated MAX+1 code on insert
    // =====================================================================

    public function testSaveNewRowGeneratesMaxPlusOneAndInserts(): void
    {
        $db = new EmpresaSedeModelSpyDb();
        $db->selectQueue = [[['cod' => '0']], []];
        $sede = $this->makeSede($db);
        $sede->nombre = 'Sede Nueva';
        $sede->direccion = 'Calle 1';
        $sede->cifnif = 'B1';

        $this->assertTrue($sede->save());
        $this->assertSame('1', $sede->codsede);
        $this->assertContains('codsede', $db->sqlToIntCalls);

        $inserts = array_values(array_filter(
            $db->execStatements,
            static fn (string $sql): bool => str_starts_with($sql, 'INSERT')
        ));
        $this->assertCount(1, $inserts);
        $this->assertStringContainsString('empresa_sedes', $inserts[0]);
        $this->assertStringContainsString("'1'", $inserts[0]);
    }

    // =====================================================================
    // Case 6 — update and delete
    // =====================================================================

    public function testSaveExistingRowUpdatesAndDeleteUsesVar2str(): void
    {
        $db = new EmpresaSedeModelSpyDb();
        $db->selectQueue = [[['codsede' => '3']], [['codsede' => '3']]];
        $sede = $this->makeSede($db, ['codsede' => '3', 'nombre' => 'Sede 3', 'direccion' => 'Calle 3']);

        $this->assertTrue($sede->save());

        $updates = array_values(array_filter(
            $db->execStatements,
            static fn (string $sql): bool => str_starts_with($sql, 'UPDATE')
        ));
        $this->assertCount(1, $updates);
        $this->assertStringContainsString("WHERE codsede = '3'", $updates[0]);

        $deleteDb = new EmpresaSedeModelSpyDb();
        $toDelete = $this->makeSede($deleteDb, ['codsede' => '3', 'nombre' => 'Sede 3']);

        $this->assertTrue($toDelete->delete());
        $this->assertSame("DELETE FROM empresa_sedes WHERE codsede = '3';", $deleteDb->execStatements[0]);
    }

    // =====================================================================
    // Case 7 — ordered listing
    // =====================================================================

    public function testAllHydratesRowsInDescripcionThenCodsedeOrder(): void
    {
        $db = new EmpresaSedeModelSpyDb();
        $db->selectQueue = [[
            ['codsede' => '2', 'descripcion' => 'B', 'nombre' => 'B'],
            ['codsede' => '1', 'descripcion' => 'A', 'nombre' => 'A'],
        ]];
        $model = $this->makeSede($db);

        $list = $model->all();

        $this->assertCount(2, $list);
        $this->assertStringContainsString('ORDER BY descripcion ASC, codsede ASC', $db->selectStatements[0]);
        $this->assertSame('2', $list[0]->codsede);
        $this->assertSame('1', $list[1]->codsede);
    }

    // =====================================================================
    // Case 8 — url
    // =====================================================================

    public function testUrlPointsAtAdminEmpresaWithSedesFragment(): void
    {
        $sede = $this->makeSede(['codsede' => '3', 'nombre' => 'Sede 3']);
        $this->assertStringContainsString('admin_empresa', $sede->url());
        $this->assertStringContainsString('#sedes', $sede->url());

        $empty = $this->makeSede([]);
        $this->assertStringContainsString('admin_empresa', $empty->url());
        $this->assertStringContainsString('#sedes', $empty->url());
    }

    // =====================================================================
    // Case 9 — lazy schema, no migration code
    // =====================================================================

    public function testInstallReturnsEmptyString(): void
    {
        $method = new \ReflectionMethod(\empresa_sede::class, 'install');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($this->makeSede([])));
    }

    // =====================================================================
    // Case 26 (model half) — the 12-field contract and descripcion round-trip
    // =====================================================================

    public function testEditableFieldSetIsExactlyTheTwelveNames(): void
    {
        $this->assertSame(self::EDITABLE_FIELDS, \empresa_sede::EDITABLE_FIELDS);

        foreach ([
            'codsede',
            'contintegrada',
            'codalmacen',
            'codserie',
            'fax',
            'lema',
            'pie_factura',
            'horario',
            'nombrecorto',
        ] as $forbidden) {
            $this->assertNotContains($forbidden, \empresa_sede::EDITABLE_FIELDS);
        }
    }

    public function testSaveThenHydrationRoundTripsDescripcionVerbatim(): void
    {
        $db = new EmpresaSedeModelSpyDb();
        $db->selectQueue = [[['cod' => '0']], []];
        $sede = $this->makeSede($db);
        $sede->descripcion = 'Sede Norte';
        $sede->nombre = 'Empresa Norte';
        $sede->direccion = 'Calle 1';
        $sede->cifnif = 'B1';

        $this->assertTrue($sede->save());

        $inserts = array_values(array_filter(
            $db->execStatements,
            static fn (string $sql): bool => str_starts_with($sql, 'INSERT')
        ));
        $this->assertCount(1, $inserts);
        $this->assertStringContainsString("'Sede Norte'", $inserts[0]);

        $readBack = $this->makeSede([
            'codsede' => '1',
            'descripcion' => 'Sede Norte',
            'nombre' => 'Empresa Norte',
        ]);
        $this->assertSame('Sede Norte', $readBack->descripcion);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function makeSede($dbOrData = false, array $row = []): \empresa_sede
    {
        if ($dbOrData instanceof EmpresaSedeModelSpyDb) {
            $data = $row;
            $db = $dbOrData;
        } else {
            $data = is_array($dbOrData) ? $dbOrData : [];
            $db = null;
        }

        return new class($data, $db) extends \empresa_sede {
            /** @var list<string> */
            public array $errors = [];

            public function __construct($data = false, $db = null)
            {
                $this->table_name = 'empresa_sedes';
                $this->db = $db;

                if (is_array($data)) {
                    $this->hydrate($data);
                } else {
                    $this->clear();
                }
            }

            protected function new_error_msg($msg)
            {
                $this->errors[] = (string) $msg;
            }
        };
    }

    private function resetFsModelCheckedTables(): void
    {
        $ref = new \ReflectionClass('fs_model');
        $prop = $ref->getProperty('checked_tables');
        $prop->setAccessible(true);
        // Reset to the pristine "not yet initialized" state: a plain [] would
        // make isset() true and skip fs_model's lazy core_log/base_dir setup.
        $prop->setValue(null, null);
    }
}
