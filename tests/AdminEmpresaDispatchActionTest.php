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

// Same DB-free loading contract used by tests/Security/FsController*Test.php:
// forcing the lazy model path avoids `require_all_models()` pulling every core
// model into the shared Plugins-suite process mid-run.
if (!defined('FS_LAZY_MODELS')) {
    define('FS_LAZY_MODELS', true);
}

/**
 * Dispatch-routing regression tests for `admin_empresa` (WU-4, design cases
 * 22-26) plus source-level contracts for the sede panel (nav, panel, JS) and
 * the `block/admin_empresa_sedes.html.twig` template.
 *
 * The highest-severity risk of the whole change lives here: a sede form posts a
 * required field named `nombre`, which the legacy `dispatchAction()` used to
 * dispatch straight into `handleEmpresaSave()` — overwriting the base company
 * row. `resolveAction()` is the pure seam that makes the precedence testable
 * without a database, a session or a real SAPI request (`filter_input()` cannot
 * be stubbed in CLI).
 */
final class AdminEmpresaDispatchActionTest extends TestCase
{
    private const TEMPLATE = 'plugins/business_data/view/admin_empresa.html.twig';

    private const BLOCK = 'plugins/business_data/view/block/admin_empresa_sedes.html.twig';

    private const CONTROLLER = 'plugins/business_data/controller/admin_empresa.php';

    private const SEDE_FIELDS = [
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

    /** @var list<string> */
    private const FORBIDDEN_SEDE_FIELDS = [
        'contintegrada',
        'codalmacen',
        'codserie',
        'fax',
        'lema',
        'pie_factura',
        'horario',
        'nombrecorto',
    ];

    private static bool $dependenciesLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadDependencies();

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
    // Case 22 — highest-severity collision regression
    // =====================================================================

    public function testSaveSedeMarkerWinsOverTheNombreBranch(): void
    {
        $action = \admin_empresa::resolveAction(['save_sede' => '1', 'nombre' => 'Sede X'], []);

        $this->assertSame('sede_save', $action);
        $this->assertNotSame(
            'empresa',
            $action,
            'a sede POST carrying `nombre` must never fall through to handleEmpresaSave()'
        );
    }

    // =====================================================================
    // Case 23 — delete marker wins too
    // =====================================================================

    public function testDeleteSedeMarkerWinsOverTheNombreBranch(): void
    {
        $this->assertSame('sede_delete', \admin_empresa::resolveAction(['delete_sede' => '3', 'nombre' => 'X'], []));
    }

    // =====================================================================
    // Case 24 — a full sede POST resolves to the sede handler only
    // =====================================================================

    public function testFullSedePostResolvesToTheSedeHandlerOnly(): void
    {
        $post = ['save_sede' => '1'];
        foreach (self::SEDE_FIELDS as $field) {
            $post[$field] = $field . '-value';
        }

        $action = \admin_empresa::resolveAction($post, []);

        $this->assertSame('sede_save', $action);
        $this->assertNotSame('empresa', $action);
        $this->assertCount(12, \admin_empresa::sedeFieldsFromPost($post));
    }

    // =====================================================================
    // Case 25 — every existing branch keeps resolving exactly as before
    // =====================================================================

    public function testExistingDispatchBranchesAreUnchangedIncludingTheDeleteCuentaAsymmetry(): void
    {
        $this->assertSame('empresa', \admin_empresa::resolveAction(['nombre' => 'Empresa Base'], []));
        $this->assertSame('logo', \admin_empresa::resolveAction(['logo' => 'TRUE'], []));
        $this->assertSame('delete_logo', \admin_empresa::resolveAction([], ['delete_logo' => 'TRUE']));
        $this->assertSame('cuenta_save', \admin_empresa::resolveAction(['iban' => 'ES123'], []));

        // Legacy asymmetry preserved on purpose: the dispatch reads
        // `delete_cuenta` from POST while handleDeleteCuenta() reads it from
        // GET. This assertion pins the dispatch half.
        $this->assertSame('cuenta_delete', \admin_empresa::resolveAction(['delete_cuenta' => '3'], []));
        $this->assertSame(
            'none',
            \admin_empresa::resolveAction([], ['delete_cuenta' => '3']),
            'delete_cuenta must still resolve against POST, not GET'
        );

        // The empresa branch stays POST-only.
        $this->assertSame('none', \admin_empresa::resolveAction([], ['nombre' => 'Empresa Base']));

        // Empty payload keeps the historical default.
        $this->assertSame('none', \admin_empresa::resolveAction([], []));
    }

    // =====================================================================
    // Fidelity — `filter_input()` rejects array values; so must the seam
    // =====================================================================

    public function testArrayValuedPayloadBehavesLikeFilterInput(): void
    {
        $this->assertSame(
            'none',
            \admin_empresa::resolveAction(['nombre' => ['Empresa Base']], []),
            'filter_input() returns false for array input, so the branch must not fire'
        );
        $this->assertSame('none', \admin_empresa::resolveAction(['save_sede' => ['1']], []));
        $this->assertSame('none', \admin_empresa::resolveAction(['nombre' => ''], []));
        $this->assertSame('none', \admin_empresa::resolveAction(['nombre' => '0'], []));
    }

    // =====================================================================
    // Case 26 — the sede field projector never leaks base-company fields
    // =====================================================================

    public function testSedeFieldsFromPostReturnsExactlyTheTwelveEditableFields(): void
    {
        $post = ['save_sede' => '1', 'codsede' => 'S1'];
        foreach (self::SEDE_FIELDS as $field) {
            $post[$field] = $field . '-value';
        }
        foreach (self::FORBIDDEN_SEDE_FIELDS as $field) {
            $post[$field] = 'must-not-leak';
        }

        $fields = \admin_empresa::sedeFieldsFromPost($post);

        $this->assertSame(self::SEDE_FIELDS, array_keys($fields));
        $this->assertSame(
            \empresa_sede::EDITABLE_FIELDS,
            array_keys($fields),
            'the controller projector must mirror the entity editable field set'
        );

        foreach (self::FORBIDDEN_SEDE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $fields, $field . ' must never be written by a sede form');
        }
    }

    public function testSedeFieldsFromPostKeepsExplicitlyEmptyValues(): void
    {
        $fields = \admin_empresa::sedeFieldsFromPost(['save_sede' => '1', 'web' => '', 'nombre' => 'Sede X']);

        $this->assertSame(['nombre' => 'Sede X', 'web' => ''], $fields, 'an empty field must be clearable');
    }

    // =====================================================================
    // Spec scenario "Reserved order under regression" — source order
    // =====================================================================

    public function testResolveActionIsAPureSeamThatTestsTheSedeMarkersFirst(): void
    {
        $ref = new \ReflectionMethod('admin_empresa', 'resolveAction');

        $this->assertTrue($ref->isPublic());
        $this->assertTrue($ref->isStatic());
        $this->assertSame('string', (string) $ref->getReturnType());
        $this->assertSame(2, $ref->getNumberOfParameters());
        $this->assertSame('post', $ref->getParameters()[0]->getName());
        $this->assertSame('get', $ref->getParameters()[1]->getName());
        $this->assertSame('array', (string) $ref->getParameters()[0]->getType());
        $this->assertSame('array', (string) $ref->getParameters()[1]->getType());

        $body = $this->methodSource('admin_empresa', 'resolveAction');

        $this->assertSourceOrder($body, "'save_sede'", "'nombre'", 'resolveAction()');
        $this->assertSourceOrder($body, "'delete_sede'", "'nombre'", 'resolveAction()');

        // dispatchAction() must route through the seam, never on a raw `nombre`
        // presence check.
        $dispatch = $this->methodSource('admin_empresa', 'dispatchAction');
        $this->assertStringContainsString('self::resolveAction(', $dispatch);

        $projector = new \ReflectionMethod('admin_empresa', 'sedeFieldsFromPost');
        $this->assertTrue($projector->isPublic());
        $this->assertTrue($projector->isStatic());
    }

    // =====================================================================
    // Spec scenario "Sede form does not touch the base row" — handler scope
    // =====================================================================

    public function testSedeHandlersNeverWriteTheBaseCompany(): void
    {
        foreach (['handleSaveSede', 'handleDeleteSede'] as $method) {
            $body = $this->methodSource('admin_empresa', $method);

            $this->assertStringNotContainsString(
                '$this->empresa->',
                $body,
                $method . ' must never write the base company'
            );
            $this->assertStringNotContainsString(
                '$this->empresa =',
                $body,
                $method . ' must never reassign the base company'
            );
            $this->assertStringNotContainsString(
                'applyEmpresaFields',
                $body,
                $method . ' must never reuse the base-company field projector'
            );
        }
    }

    // =====================================================================
    // Case 8.7 — the sede panel is wired into the empresa template
    // =====================================================================

    public function testSedesNavAndPanelAreWiredIntoTheEmpresaTemplate(): void
    {
        $src = $this->templateSource();

        $this->assertStringContainsString('<li id="b_sedes">', $src);
        $this->assertSourceOrder($src, '<li id="b_cuentasb">', '<li id="b_sedes">', self::TEMPLATE);
        $this->assertSourceOrder($src, '<li id="b_sedes">', '<li id="b_impresion">', self::TEMPLATE);
        $this->assertSourceOrder($src, '<li id="b_sedes">', '{% for extension in fsc.extensions %}', self::TEMPLATE);
        $this->assertStringContainsString("mostrar_seccion('sedes');", $src);

        // The panel must live outside `<form name="f_empresa">` (its closing tag
        // is the first `</form>` in the file) and after `panel_cuentasb`.
        $this->assertStringContainsString('<div id="panel_sedes">', $src);
        $this->assertSourceOrder($src, '<div id="panel_cuentasb">', '<div id="panel_sedes">', self::TEMPLATE);
        $this->assertSourceOrder($src, '</form>', '<div id="panel_sedes">', self::TEMPLATE);

        $formEnd = $this->position($src, '</form>', self::TEMPLATE);
        $panelStart = $this->position($src, '<div id="panel_sedes">', self::TEMPLATE);
        $between = substr($src, $formEnd, $panelStart - $formEnd);
        $this->assertStringNotContainsString(
            '<form',
            $between,
            'the sede panel must be rendered outside the base-company form'
        );

        $this->assertStringContainsString(
            "{% include 'block/admin_empresa_sedes.html.twig' %}",
            $src,
            'the sedes panel must include its own block'
        );
    }

    public function testSedesBranchesAreWiredIntoComprobarUrlAndMostrarSeccion(): void
    {
        $src = $this->templateSource();

        // Deep-link branch inside comprobar_url(), before mostrar_seccion().
        $this->assertStringContainsString("window.location.hash.substring(1) == 'sedes'", $src);
        $this->assertSourceOrder(
            $src,
            "window.location.hash.substring(1) == 'sedes'",
            'function mostrar_seccion(id)',
            self::TEMPLATE
        );

        // mostrar_seccion() must hide the new panel and deactivate its nav item,
        // next to the existing five ids.
        $this->assertSourceOrder($src, '$("#panel_cuentasb").hide();', '$("#panel_sedes").hide();', self::TEMPLATE);
        $this->assertSourceOrder(
            $src,
            "\$(\"#b_cuentasb\").removeClass('active');",
            "\$(\"#b_sedes\").removeClass('active');",
            self::TEMPLATE
        );

        // The new branch is anchored at the `} else if` closure of the cuentasb
        // branch (design correction 3), never inside its body.
        $this->assertSourceOrder(
            $src,
            "\$(\"#b_cuentasb\").addClass('active');",
            "} else if (id == 'sedes') {",
            self::TEMPLATE
        );
        $this->assertSourceOrder($src, "} else if (id == 'sedes') {", "} else if (id == 'impresion') {", self::TEMPLATE);
        $this->assertStringContainsString('$("#panel_sedes").show();', $src);
        $this->assertStringContainsString("\$(\"#b_sedes\").addClass('active');", $src);

        // The five existing panels and their branches stay untouched.
        foreach (['generales', 'facturacion', 'cuentasb', 'impresion', 'traducciones'] as $id) {
            $this->assertStringContainsString('$("#panel_' . $id . '").hide();', $src);
        }
        foreach (['facturacion', 'cuentasb', 'impresion', 'traducciones'] as $id) {
            $this->assertStringContainsString("id == '" . $id . "'", $src);
        }
        $this->assertStringContainsString('$("#panel_generales").show();', $src);
    }

    // =====================================================================
    // Case 8.8 — the sede block contract
    // =====================================================================

    public function testSedesBlockRendersEscapedMarkersWithoutDisabledSubmits(): void
    {
        $path = FS_FOLDER . '/' . self::BLOCK;
        $this->assertFileExists($path, 'the sedes block template must exist');

        $src = (string) file_get_contents($path);

        // Both dispatch markers are present, and each is carried by a submit
        // button inside a CSRF-protected form.
        $this->assertStringContainsString('name="save_sede"', $src);
        $this->assertStringContainsString('name="delete_sede"', $src);
        $this->assertStringContainsString('{{ csrf_field() }}', $src);
        $this->assertSame(
            substr_count($src, '<form'),
            substr_count($src, '{{ csrf_field() }}'),
            'every sede form must carry exactly one CSRF field'
        );

        // AD-10 trap: disabling the clicked submit drops its marker from the
        // payload and would hand the request to the `nombre` branch.
        $this->assertStringNotContainsString('this.disabled', $src);
        $this->assertStringNotContainsString('onclick=', $src);

        // AD-8 trap: the panel is hidden with .hide(), so a Bootstrap modal
        // nested inside it would never render. The create form stays inline.
        $this->assertStringNotContainsString('class="modal', $src);
        $this->assertStringNotContainsString("modal('show')", $src);

        // Escaped output only; the selector label falls back to nombre.
        $this->assertStringContainsString('{{ sede.descripcion ?: sede.nombre }}', $src);
        $this->assertStringNotContainsString('|raw', $src);

        // Empty state and the create form.
        $this->assertStringContainsString('fsc.sedes', $src);
        $this->assertStringContainsString('Nueva sede', $src);
    }

    // =====================================================================
    // Runtime harness — the block really renders, without the app
    // =====================================================================

    public function testSedesBlockRendersOneCsrfProtectedFormPerSede(): void
    {
        $html = $this->renderSedesBlock([
            $this->sedeEntity('S1', 'Sede <b>Norte</b>', 'Norte', '600111222'),
            $this->sedeEntity('S2', '', 'Sur', ''),
        ]);

        $this->assertSame(3, substr_count($html, '<form'), 'two sede forms plus the inline create form');
        $this->assertSame(
            3,
            substr_count($html, 'name="_token"'),
            'every rendered form must carry its own CSRF field'
        );
        $this->assertSame(3, substr_count($html, 'name="save_sede"'), 'one save marker per form');
        $this->assertSame(2, substr_count($html, 'name="delete_sede"'), 'one delete marker per existing sede');
        $this->assertStringContainsString('name="delete_sede" value="S1"', $html);

        $this->assertStringContainsString(
            'Sede &lt;b&gt;Norte&lt;/b&gt;',
            $html,
            'the selector label must be escaped'
        );
        $this->assertStringNotContainsString('Sede <b>Norte</b>', $html);
        $this->assertStringContainsString('Sur', $html, 'an empty descripcion falls back to nombre');
        $this->assertStringNotContainsString('No hay sedes configuradas', $html);
        $this->assertStringContainsString('Nueva sede', $html);
    }

    public function testSedesBlockShowsTheEmptyStateWithoutSedes(): void
    {
        $html = $this->renderSedesBlock([]);

        $this->assertSame(1, substr_count($html, '<form'), 'only the inline create form remains');
        $this->assertStringContainsString('No hay sedes configuradas', $html);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * DB-free render of the sede block. `csrf_field()` is stubbed exactly as the
     * framework registers it (`src/Core/Html.php:287`, html-safe), so the real
     * auto-escaping of the template is exercised.
     *
     * @param list<empresa_sede> $sedes
     */
    private function renderSedesBlock(array $sedes): string
    {
        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader([FS_FOLDER . '/plugins/business_data/view']),
            ['cache' => false]
        );
        $twig->addFunction(new \Twig\TwigFunction(
            'csrf_field',
            static fn (): string => '<input type="hidden" name="_token" value="stub"/>',
            ['is_safe' => ['html']]
        ));

        $fsc = new class {
            /** @var list<empresa_sede> */
            public array $sedes = [];

            public function url(): string
            {
                return 'index.php?page=admin_empresa';
            }
        };
        $fsc->sedes = $sedes;

        return $twig->render('block/admin_empresa_sedes.html.twig', ['fsc' => $fsc]);
    }

    /**
     * Real `empresa_sede` entity, hydrated without the DB-backed constructor so
     * the render asserts the entity's actual public property names.
     */
    private function sedeEntity(string $codsede, string $descripcion, string $nombre, string $telefono): \empresa_sede
    {
        $row = [
            'codsede' => $codsede,
            'descripcion' => $descripcion,
            'nombre' => $nombre,
            'cifnif' => 'B00000000',
            'direccion' => 'Calle 1',
            'apartado' => '',
            'codpostal' => '28001',
            'ciudad' => 'Madrid',
            'provincia' => 'Madrid',
            'codpais' => 'ESP',
            'email' => 'sede@example.com',
            'web' => '',
            'telefono' => $telefono,
        ];

        return new class($row) extends \empresa_sede {
            /**
             * @param array<string, mixed> $row
             */
            public function __construct(array $row)
            {
                $this->table_name = 'empresa_sedes';
                $this->hydrate($row);
            }
        };
    }

    /**
     * DB-free load order. `fs_controller` MUST be loaded before the controller
     * file (design correction 6), otherwise including `admin_empresa.php`
     * fails with `Class "fs_controller" not found`.
     */
    private function loadDependencies(): void
    {
        if (self::$dependenciesLoaded) {
            return;
        }

        require_once FS_FOLDER . '/base/fs_controller.php';
        require_once FS_FOLDER . '/base/fs_core_log.php';
        require_once FS_FOLDER . '/base/fs_settings.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
        require_once FS_FOLDER . '/' . self::CONTROLLER;

        self::$dependenciesLoaded = true;
    }

    private function templateSource(): string
    {
        return (string) file_get_contents(FS_FOLDER . '/' . self::TEMPLATE);
    }

    private function position(string $src, string $needle, string $file): int
    {
        $pos = strpos($src, $needle);
        $this->assertNotFalse($pos, sprintf('"%s" not found in %s', $needle, $file));

        return (int) $pos;
    }

    private function assertSourceOrder(string $src, string $first, string $second, string $file): void
    {
        $this->assertLessThan(
            $this->position($src, $second, $file),
            $this->position($src, $first, $file),
            sprintf('"%s" must appear before "%s" in %s', $first, $second, $file)
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new \ReflectionMethod($class, $method);
        $lines = file(FS_FOLDER . '/' . self::CONTROLLER);
        $this->assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));
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
