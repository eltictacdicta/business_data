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

    /**
     * Structural contract of `empresa_sedes.xml` that IS verifiable without a
     * database: the file is well-formed, its shape matches the framework's
     * schema format (`<tabla>` root with only `<columna>`/`<restriccion>`
     * children, as declared by `empresa.xml`/`cuentasbanco.xml`), it declares
     * exactly the 13 narrowed columns with the same `tipo`/`nulo` that
     * `empresa.xml` uses for the 11 shared names, and its filename equals the
     * model's table name -- the framework resolves the XML *by table name*
     * (`fs_model::get_base_dir()` / `get_xml_table()`), so a mismatch would only
     * surface at runtime.
     *
     * NOT verified here: the actual `CREATE TABLE` DDL executed against a live
     * database. `fs_model::check_table()` runs it only inside the real
     * constructor, which requires a live DB connection; this suite is DB-free on
     * purpose and must not create or drop tables in the dev database. That step
     * is covered by the manual smoke test recorded in `tasks.md` (task 10.6).
     */
    public function testSchemaXmlIsWellFormedAndMatchesTheFrameworkAndEmpresaTypes(): void
    {
        $path = FS_FOLDER . '/plugins/business_data/model/table/empresa_sedes.xml';
        $this->assertFileExists($path);

        // --- well-formed XML, no parser diagnostics -------------------------
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = simplexml_load_file($path);
        $parseErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        $this->assertNotFalse($xml, 'the schema XML must parse as XML');
        $this->assertSame(
            [],
            array_map(static fn (\LibXMLError $error): string => trim($error->message), $parseErrors),
            'the schema XML must be well-formed'
        );

        // --- framework schema shape (empresa.xml / cuentasbanco.xml) --------
        $this->assertSame('tabla', $xml->getName(), 'the root element must match the framework schema format');

        $childNames = [];
        foreach ($xml->children() as $child) {
            $childNames[] = $child->getName();
        }
        $childNames = array_values(array_unique($childNames));
        sort($childNames);
        $this->assertSame(
            ['columna', 'restriccion'],
            $childNames,
            'only <columna> and <restriccion> children are part of the framework schema format'
        );

        // --- exactly the 13 narrowed columns, with the binding design types -
        $expected = [
            'codsede' => ['character varying(6)', 'NO'],
            'descripcion' => ['character varying(100)', 'YES'],
            'nombre' => ['character varying(100)', 'NO'],
            'cifnif' => ['character varying(30)', 'NO'],
            'direccion' => ['character varying(100)', 'NO'],
            'apartado' => ['character varying(10)', 'YES'],
            'codpostal' => ['character varying(10)', 'YES'],
            'ciudad' => ['character varying(100)', 'YES'],
            'provincia' => ['character varying(100)', 'YES'],
            'codpais' => ['character varying(20)', 'YES'],
            'email' => ['character varying(100)', 'YES'],
            'web' => ['character varying(100)', 'YES'],
            'telefono' => ['character varying(20)', 'YES'],
        ];

        $declared = $this->parseSchemaColumns($path);
        $this->assertSame(
            array_keys($expected),
            array_keys($declared),
            'the XML must declare exactly the 13 narrowed columns in design order'
        );

        foreach ($expected as $name => [$type, $null]) {
            $this->assertSame($type, $declared[$name]['tipo'], $name . ' must use the design type');
            $this->assertSame($null, $declared[$name]['nulo'], $name . ' must use the design nullability');
        }

        // --- cross-check the 11 shared names against empresa.xml -----------
        $empresa = $this->parseSchemaColumns(FS_FOLDER . '/plugins/business_data/model/table/empresa.xml');
        $this->assertArrayHasKey('cifnif', $empresa, 'sanity: the company schema must be readable');
        $shared = 0;
        foreach ($expected as $name => $definition) {
            if (!array_key_exists($name, $empresa)) {
                continue;
            }
            $shared++;
            $this->assertSame(
                $empresa[$name]['tipo'],
                $declared[$name]['tipo'],
                $name . ' must reuse the type declared by empresa.xml'
            );
            $this->assertSame(
                $empresa[$name]['nulo'],
                $declared[$name]['nulo'],
                $name . ' must reuse the nullability declared by empresa.xml'
            );
        }
        $this->assertSame(11, $shared, 'exactly 11 of the 13 columns are shared with empresa.xml');
        $this->assertSame(
            'empresa_sedes_pkey',
            (string) $xml->restriccion[0]->nombre,
            'the primary key constraint must be named empresa_sedes_pkey'
        );
        $this->assertSame('PRIMARY KEY (codsede)', (string) $xml->restriccion[0]->consulta);

        // --- filename == table name (how the framework finds the XML) ------
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

    /**
     * Sequential uniqueness: the second save must observe the advanced `MAX()`
     * and generate a DIFFERENT code. The spy answers the first `MAX()` with 1
     * and the second with 2, so two successive saves must produce two distinct
     * non-empty codes -- an implementation that ignored `MAX()` (e.g. hardcoded
     * `'1'`) would collide on the primary key and fail here.
     */
    public function testTwoSuccessiveSavesProduceTwoDifferentNonEmptyCodes(): void
    {
        $db = new EmpresaSedeModelSpyDb();
        // One queue entry per emitted SELECT, in execution order:
        // save 1 -> MAX (1) ; save 1 -> exists (empty) ; save 2 -> MAX (2) ; save 2 -> exists (empty)
        $db->selectQueue = [[['cod' => '1']], [], [['cod' => '2']], []];

        $first = $this->makeSede($db);
        $first->nombre = 'Sede Primera';
        $this->assertTrue($first->save());

        $second = $this->makeSede($db);
        $second->nombre = 'Sede Segunda';
        $this->assertTrue($second->save());

        $this->assertNotSame('', $first->codsede);
        $this->assertNotSame('', $second->codsede);
        $this->assertNotSame(
            $first->codsede,
            $second->codsede,
            'two successive saves must not reuse the same generated code'
        );
        $this->assertSame('2', $first->codsede);
        $this->assertSame('3', $second->codsede);

        $inserts = array_values(array_filter(
            $db->execStatements,
            static fn (string $sql): bool => str_starts_with($sql, 'INSERT')
        ));
        $this->assertCount(2, $inserts);
        $this->assertStringContainsString("'2'", $inserts[0]);
        $this->assertStringContainsString("'3'", $inserts[1]);
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

    /**
     * Honest coverage: the previous name claimed to prove the
     * `descripcion ASC, codsede ASC` ordering, but the spy returned rows that
     * were ALREADY in that order, so nothing about ordering was actually
     * exercised. Real DB-side ordering cannot be proven DB-free (there is no
     * database here and the spy does not sort). What this test truly proves:
     *
     *  1. `all()` emits the `ORDER BY descripcion ASC, codsede ASC` clause
     *     (the only ordering contract reachable without a database), and
     *  2. `all()` hydrates EVERY returned row with its own field values,
     *     preserving the order the database returned (it does not sort or
     *     drop rows in PHP).
     */
    public function testAllEmitsOrderByClauseAndHydratesEveryReturnedRow(): void
    {
        $db = new EmpresaSedeModelSpyDb();
        // Deliberately NOT sorted by descripcion: B before A. If the model
        // reordered rows in PHP this test would catch it, and the ORDER BY
        // assertion proves the clause the database is asked to honour.
        $db->selectQueue = [[
            ['codsede' => '2', 'descripcion' => 'B', 'nombre' => 'Sede B', 'ciudad' => 'Bilbao'],
            ['codsede' => '1', 'descripcion' => 'A', 'nombre' => 'Sede A', 'ciudad' => 'Alicante'],
        ]];
        $model = $this->makeSede($db);

        $list = $model->all();

        $this->assertCount(2, $list);
        $this->assertStringContainsString(
            'ORDER BY descripcion ASC, codsede ASC',
            $db->selectStatements[0],
            'all() must ask the database to order by descripcion then codsede'
        );

        // Every returned row is hydrated with its own values, in returned order.
        $this->assertSame('2', $list[0]->codsede);
        $this->assertSame('B', $list[0]->descripcion);
        $this->assertSame('Sede B', $list[0]->nombre);
        $this->assertSame('Bilbao', $list[0]->ciudad);
        $this->assertSame('1', $list[1]->codsede);
        $this->assertSame('A', $list[1]->descripcion);
        $this->assertSame('Sede A', $list[1]->nombre);
        $this->assertSame('Alicante', $list[1]->ciudad);
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

    /**
     * Real round-trip. The previous test only asserted that the INSERT string
     * contained `'Sede Norte'` and then hydrated from a hand-written literal
     * array; nothing tied the two halves together, so the name overstated the
     * coverage.
     *
     * This test parses the INSERT statement `save()` actually emitted (column
     * list + value list), rebuilds the row from it, hydrates a brand-new entity
     * from *that* row and asserts every one of the 13 fields survives verbatim.
     * The values include a double quote, an ampersand and an embedded comma, so
     * both the SQL string quoting and the column/value split are exercised.
     */
    public function testSaveThenHydrationRoundTripsThePersistedRowVerbatim(): void
    {
        $db = new EmpresaSedeModelSpyDb();
        $db->selectQueue = [[['cod' => '0']], []];
        $sede = $this->makeSede($db);
        $sede->descripcion = 'Sede "Norte" & Sur';
        $sede->nombre = 'Empresa Norte';
        $sede->cifnif = 'B1';
        $sede->direccion = 'Calle Mayor, 1';
        $sede->apartado = '12';
        $sede->codpostal = '48001';
        $sede->ciudad = 'Bilbao';
        $sede->provincia = 'Bizkaia';
        $sede->codpais = 'ESP';
        $sede->email = 'norte@example.com';
        $sede->web = 'https://norte.example.com';
        $sede->telefono = '600111222';

        $this->assertTrue($sede->save());

        $inserts = array_values(array_filter(
            $db->execStatements,
            static fn (string $sql): bool => str_starts_with($sql, 'INSERT')
        ));
        $this->assertCount(1, $inserts);

        // The row as it was actually persisted.
        $persisted = $this->parseInsertRow($inserts[0]);
        $this->assertCount(13, $persisted, 'the INSERT must persist all 13 columns');

        // Re-hydrate from that row and assert verbatim survival.
        $readBack = $this->makeSede($persisted);
        foreach ([
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
        ] as $field) {
            $this->assertSame(
                $sede->{$field},
                $readBack->{$field},
                'the persisted row must round-trip ' . $field . ' verbatim'
            );
        }

        // descripcion explicitly: it is the selector label and must not be lost.
        $this->assertSame('Sede &quot;Norte&quot; &amp; Sur', $readBack->descripcion);
        $this->assertSame('Calle Mayor, 1', $readBack->direccion);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Parses a framework table-description XML (`model/table/*.xml`) into
     * `name => ['tipo' => ..., 'nulo' => 'YES'|'NO']`, mirroring how
     * `fs_model::get_xml_table()` reads it (`<nulo>` absent means `YES`).
     *
     * @return array<string, array{tipo: string, nulo: string}>
     */
    private function parseSchemaColumns(string $path): array
    {
        $this->assertFileExists($path);
        $xml = simplexml_load_file($path);
        $this->assertNotFalse($xml, 'schema ' . $path . ' must parse');

        $columns = [];
        foreach ($xml->columna as $columna) {
            $name = (string) $columna->nombre;
            $this->assertNotSame('', $name, 'every <columna> must declare a <nombre>');
            $this->assertArrayNotHasKey($name, $columns, 'duplicate column ' . $name);

            $null = strtoupper(trim((string) $columna->nulo));
            $columns[$name] = [
                'tipo' => (string) $columna->tipo,
                'nulo' => $null === '' ? 'YES' : $null,
            ];
        }

        return $columns;
    }

    /**
     * Extracts a `column => value` map from an INSERT statement emitted by
     * `save()`, undoing the `var2str()` quoting, so a test can hydrate a new
     * entity from *the row that was actually persisted* instead of a literal
     * array written by hand.
     *
     * Note on the escape branch: `empresa_sede::test()` runs `no_html()`
     * (which turns `'` into `&#39;`) before `save()` builds the SQL, so a raw
     * single quote cannot reach the INSERT in production today. The unescape is
     * kept for correctness of the parser, not as a claimed coverage target.
     *
     * @return array<string, mixed>
     */
    private function parseInsertRow(string $sql): array
    {
        $pattern = '/INSERT\s+INTO\s+\S+\s*\((?<columns>[^)]*)\)\s*VALUES\s*\((?<values>.*)\)\s*;?\s*$/is';
        if (!preg_match($pattern, $sql, $matches)) {
            $this->fail('could not parse the INSERT statement: ' . $sql);
        }

        $columns = array_map('trim', explode(',', $matches['columns']));
        $values = $this->parseSqlValueList($matches['values']);

        $this->assertSame(
            count($columns),
            count($values),
            'every INSERT column must have exactly one value'
        );

        return array_combine($columns, $values);
    }

    /**
     * @return list<mixed>
     */
    private function parseSqlValueList(string $list): array
    {
        $values = [];
        $length = strlen($list);
        $position = 0;

        while ($position < $length) {
            while ($position < $length && ($list[$position] === ' ' || $list[$position] === ',')) {
                $position++;
            }
            if ($position >= $length) {
                break;
            }

            if ($list[$position] === "'") {
                $position++;
                $buffer = '';
                while ($position < $length) {
                    if ($list[$position] === '\\' && $position + 1 < $length) {
                        $buffer .= $list[$position + 1];
                        $position += 2;
                        continue;
                    }
                    if ($list[$position] === "'") {
                        $position++;
                        break;
                    }
                    $buffer .= $list[$position];
                    $position++;
                }
                $values[] = $buffer;
                continue;
            }

            $start = $position;
            while ($position < $length && $list[$position] !== ',') {
                $position++;
            }
            $token = trim(substr($list, $start, $position - $start));
            $values[] = strtoupper($token) === 'NULL' ? null : $token;
        }

        return $values;
    }

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
