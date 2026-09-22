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
 * Mapping, resolution and transient `toEmpresa()` contract tests for
 * `empresa_sede` (WU-1, design cases 10-21).
 */
final class EmpresaSedeResolutionSpyDb
{
    /** @var list<string> */
    public array $execStatements = [];

    /** @var list<string> */
    public array $selectStatements = [];

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
        return 'CAST(' . $col . ' AS INTEGER)';
    }

    public function select($sql, $params = [])
    {
        $this->selectStatements[] = trim((string) $sql);

        return [];
    }

    public function exec($sql, $transaction = null, $params = [], $batch = false)
    {
        $this->execStatements[] = trim((string) $sql);

        return true;
    }

    public function lastval()
    {
        return 1;
    }
}

final class EmpresaSedeResolutionTest extends TestCase
{
    private static bool $baseLoaded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$baseLoaded) {
            return;
        }

        require_once FS_FOLDER . '/base/fs_model.php';
        require_once FS_FOLDER . '/base/fs_core_log.php';
        require_once FS_FOLDER . '/base/fs_settings.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
        self::$baseLoaded = true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        $this->resetEmpresaRowCache();
        $this->resetFsModelCheckedTables();
    }

    protected function tearDown(): void
    {
        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        $this->resetEmpresaRowCache();

        parent::tearDown();
    }

    // =====================================================================
    // Cases 10-13 — every null case, DB-free
    // =====================================================================

    public function testUnknownTipoReturnsNullAndNeverCallsTheLoader(): void
    {
        $calls = 0;
        $loader = function (string $cod) use (&$calls) {
            $calls++;

            return null;
        };

        $result = \empresa_sede::resolveForDocumentType('factura_simplificada', $this->makeBase(), $loader);

        $this->assertNull($result);
        $this->assertSame(0, $calls, 'unknown tipo must never reach the loader');
    }

    public function testAbsentMappingKeyReturnsNullAndNeverCallsTheLoader(): void
    {
        $GLOBALS['config2'] = [];
        $calls = 0;
        $loader = function (string $cod) use (&$calls) {
            $calls++;

            return null;
        };

        $result = \empresa_sede::resolveForDocumentType('factura', $this->makeBase(), $loader);

        $this->assertNull($result);
        $this->assertSame(0, $calls, 'an absent mapping key must never reach the loader');
    }

    public function testEmptyMappingKeyReturnsNullAndNeverCallsTheLoader(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => ''];
        $calls = 0;
        $loader = function (string $cod) use (&$calls) {
            $calls++;

            return null;
        };

        $result = \empresa_sede::resolveForDocumentType('factura', $this->makeBase(), $loader);

        $this->assertNull($result);
        $this->assertSame(0, $calls, 'an empty mapping key must never reach the loader');
    }

    public function testDanglingCodsedeReturnsNull(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => 'NOPE'];

        $result = \empresa_sede::resolveForDocumentType(
            'factura',
            $this->makeBase(),
            static fn (string $cod) => null
        );

        $this->assertNull($result);
    }

    // =====================================================================
    // Case 14 — mapped sede is hydrated transiently, no persistence
    // =====================================================================

    public function testMappedSedeIsHydratedTransientlyWithoutPersistence(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => 'S1'];

        $sedeDb = new EmpresaSedeResolutionSpyDb();
        $sede = $this->makeSede($sedeDb, [
            'codsede' => 'S1',
            'nombre' => 'Sede Norte',
            'cifnif' => 'B1',
            'direccion' => 'Calle Norte 1',
            'codpais' => 'ESP',
            'telefono' => '600111222',
            'web' => '',
        ]);

        $base = $this->makeBase(['nombre' => 'Empresa Base', 'codpais' => 'FRA', 'telefono' => '600999']);

        $result = \empresa_sede::resolveForDocumentType(
            'factura',
            $base,
            static fn (string $cod) => $cod === 'S1' ? $sede : null
        );

        $this->assertInstanceOf(\empresa::class, $result);
        $this->assertSame('Sede Norte', $result->nombre);
        $this->assertSame('ESP', $result->codpais);
        $this->assertSame('600111222', $result->telefono1);

        $this->assertSame([], $sedeDb->execStatements, 'the transient hydration must not write to the database');
        $this->assertSame([], $base->writes, 'the base company must not be saved, deleted nor exists()-checked');
        $this->assertSame('Empresa Base', $base->nombre, 'the base company must stay unmodified');
    }

    // =====================================================================
    // Cases 15 + 16 — field merge and telephone mapping
    // =====================================================================

    public function testToEmpresaMergesFieldsAndKeepsTheBaseUnmodified(): void
    {
        $sede = $this->makeSede([
            'codsede' => 'S1',
            'nombre' => 'Sede Norte',
            'web' => '',
            'telefono' => '600111222',
        ]);
        $base = $this->makeBase([
            'nombre' => 'Empresa Base',
            'web' => 'https://base.example.com',
            'telefono' => '600999',
        ]);

        $merged = $sede->toEmpresa($base);

        $this->assertNotSame($base, $merged);
        $this->assertSame('Sede Norte', $merged->nombre, 'a non-empty sede field wins');
        $this->assertSame('https://base.example.com', $merged->web, 'an empty sede field inherits the base');
        $this->assertSame('Empresa Base', $base->nombre, 'toEmpresa() must not mutate the base');
        $this->assertSame('https://base.example.com', $base->web);
        $this->assertSame('600111222', $merged->telefono1, 'telefono1 comes from the sede telefono');
    }

    // =====================================================================
    // Case 17 — withPrintablePhone()
    // =====================================================================

    public function testWithPrintablePhoneSetsTelefono1AndReturnsTheSameInstance(): void
    {
        $base = $this->makeBase(['telefono' => '600999']);

        $out = \empresa_sede::withPrintablePhone($base);

        $this->assertSame($base, $out, 'withPrintablePhone() must preserve the instance identity');
        $this->assertSame('600999', $out->telefono1);
    }

    public function testWithPrintablePhoneDoesNotOverwriteANonEmptyValue(): void
    {
        $base = $this->makeBase(['telefono' => '600999']);
        $base->telefono1 = '700000000';

        $out = \empresa_sede::withPrintablePhone($base);

        $this->assertSame($base, $out);
        $this->assertSame('700000000', $out->telefono1);
    }

    // =====================================================================
    // Cases 18-20 — mapping storage
    // =====================================================================

    public function testSetMappingForUnknownTipoReturnsFalse(): void
    {
        $this->assertFalse(\empresa_sede::setMappingFor('factura_simplificada', 'S1'));
        $this->assertSame([], $GLOBALS['config2']);
    }

    public function testSetMappingForInvalidCodsedeReturnsFalseAndLeavesTheStoreUntouched(): void
    {
        $GLOBALS['config2'] = ['existing' => 'keep'];
        $before = $GLOBALS['config2'];

        $this->assertFalse(\empresa_sede::setMappingFor('factura', 'NOPE', static fn (string $cod) => null));
        $this->assertSame($before, $GLOBALS['config2']);
    }

    public function testSetMappingForNullClearsTheOverride(): void
    {
        $GLOBALS['config2'] = ['empresa_sede_factura' => 'S1'];

        $this->assertTrue(\empresa_sede::setMappingFor('factura', null));

        $this->assertSame('', $GLOBALS['config2']['empresa_sede_factura']);
        $this->assertNull(\empresa_sede::mapping()['factura']);
    }

    public function testMappingRoundTripsAValidCodeAndKeepsTheCanonicalVocabulary(): void
    {
        $existing = $this->makeSede(['codsede' => 'S1', 'nombre' => 'Sede 1']);

        $this->assertTrue(
            \empresa_sede::setMappingFor('factura', 'S1', static fn (string $cod) => $existing)
        );

        $mapping = \empresa_sede::mapping();
        $this->assertSame('S1', $mapping['factura']);
        $this->assertSame(['presupuesto', 'albaran', 'pedido', 'factura'], array_keys($mapping));
    }

    // =====================================================================
    // Case 21 — every null case: null result, no loader on early returns, base
    // never replaced nor mutated
    // =====================================================================

    /**
     * Renamed from `testResolveKeepsTheBaseIdentityUntouchedForEveryNullCase`.
     * That name overstated the coverage: it adopted the base through a local
     * closure (`$adopt = $sede instanceof \empresa ? $sede : $base`) and then
     * compared the result with the very same `$base`, so the `assertSame` could
     * never fail and proved nothing about production code.
     *
     * `resolveForDocumentType()` returns `?empresa` and never hands the base
     * back, so the "same instance" (`assertSame`) identity contract of the print
     * path can only be proven at the WU-2 seam
     * (`RelatedModelsLoader::resolveEmpresa()`, factura_pdf1) -- it is NOT
     * asserted here and must not be faked here.
     *
     * What this test does prove with assertions that can actually fail:
     *  1. every null case returns `null`;
     *  2. the three short-circuit cases (unknown tipo, absent key, empty key)
     *     never reach the injected loader (0 calls);
     *  3. the dangling-code case DOES reach the loader exactly once;
     *  4. the base instance is neither replaced nor mutated and is never
     *     saved/deleted/`exists()`-checked.
     */
    public function testResolveForDocumentTypeReturnsNullForEveryNullCaseWithoutMutatingTheBase(): void
    {
        $base = $this->makeBase(['nombre' => 'Empresa Base', 'codpais' => 'FRA', 'telefono' => '600999']);
        $identity = spl_object_id($base);
        $snapshot = [
            'nombre' => $base->nombre,
            'codpais' => $base->codpais,
            'telefono' => $base->telefono,
            'id' => $base->id,
        ];

        $calls = 0;
        $loader = function (string $cod) use (&$calls) {
            $calls++;

            return null;
        };

        $GLOBALS['config2'] = [];
        $unknownTipo = \empresa_sede::resolveForDocumentType('factura_simplificada', $base, $loader);
        $this->assertNull($unknownTipo);
        $this->assertSame(0, $calls, 'an unknown tipo must never reach the loader');

        $absentKey = \empresa_sede::resolveForDocumentType('factura', $base, $loader);
        $this->assertNull($absentKey);
        $this->assertSame(0, $calls, 'an absent mapping key must never reach the loader');

        $GLOBALS['config2'] = ['empresa_sede_factura' => ''];
        $emptyKey = \empresa_sede::resolveForDocumentType('factura', $base, $loader);
        $this->assertNull($emptyKey);
        $this->assertSame(0, $calls, 'an empty mapping key must never reach the loader');

        $GLOBALS['config2'] = ['empresa_sede_factura' => 'NOPE'];
        $dangling = \empresa_sede::resolveForDocumentType('factura', $base, $loader);
        $this->assertNull($dangling);
        $this->assertSame(1, $calls, 'a dangling code must reach the loader exactly once');

        $this->assertSame($identity, spl_object_id($base), 'the base instance identity must be preserved');
        $this->assertSame($snapshot['nombre'], $base->nombre);
        $this->assertSame($snapshot['codpais'], $base->codpais);
        $this->assertSame($snapshot['telefono'], $base->telefono);
        $this->assertSame($snapshot['id'], $base->id);
        $this->assertSame([], $base->writes);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeBase(array $overrides = []): \empresa
    {
        $row = array_merge([
            'id' => 1,
            'cifnif' => 'B00000000',
            'nombre' => 'Empresa Base',
            'administrador' => 'Admin',
            'direccion' => 'Calle Base 1',
            'codpais' => 'FRA',
            'telefono' => '',
            'web' => 'https://base.example.com',
        ], $overrides);

        return new class($row) extends \empresa {
            /** @var list<string> */
            public array $writes = [];

            public function save()
            {
                $this->writes[] = 'save';

                return true;
            }

            public function delete()
            {
                $this->writes[] = 'delete';

                return true;
            }

            public function exists()
            {
                $this->writes[] = 'exists';

                return false;
            }
        };
    }

    private function makeSede($dbOrData = false, array $row = []): \empresa_sede
    {
        if ($dbOrData instanceof EmpresaSedeResolutionSpyDb) {
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

    private function resetEmpresaRowCache(): void
    {
        $ref = new \ReflectionClass('empresa');
        $prop = $ref->getProperty('empresa_row_cache');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
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
