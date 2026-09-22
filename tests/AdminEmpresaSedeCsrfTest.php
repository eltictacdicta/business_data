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

// Same DB-free loading contract used by AdminEmpresaDispatchActionTest.
if (!defined('FS_LAZY_MODELS')) {
    define('FS_LAZY_MODELS', true);
}

/**
 * The sede mutation handlers in `admin_empresa` must reject an invalid CSRF
 * token explicitly, not only through the page gate.
 *
 * `pre_private_core()` -> `validateCsrf()` only blocks invalid tokens in strict
 * mode; with `FS_CSRF_SOFT=true` it returns TRUE and `private_core()` still
 * runs. The explicit `isCsrfValid()` guard closes that window, mirroring the
 * mapping path in `tpvmod_settings`.
 *
 * `handleDeleteSede()` is exercised behaviourally: `filter_input(INPUT_POST, …)`
 * is null in CLI, so the handler cannot reach the DB and the observed error
 * message discriminates a guarded run (`Token de seguridad inválido.`) from an
 * unguarded one (`Sede no encontrada.`).
 *
 * `handleSaveSede()` cannot be invoked DB-free without the guard: its unguarded
 * `else` branch builds `new empresa_sede()`, whose constructor is DB-backed.
 * Its rejection is therefore pinned by the guard-first source contract below —
 * the guard must precede any repository access or write.
 */
final class AdminEmpresaSedeCsrfTest extends TestCase
{
    private const CONTROLLER = 'plugins/business_data/controller/admin_empresa.php';

    private const BLOCK = 'plugins/business_data/view/block/admin_empresa_sedes.html.twig';

    private const CSRF_ERROR = 'Token de seguridad inválido.';

    private static bool $dependenciesLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadDependencies();

        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        parent::tearDown();
    }

    public function testDeleteSedeRejectsAnInvalidCsrfTokenWithoutTouchingAnyRow(): void
    {
        [$controller, $sedeSpy] = $this->controllerWithInvalidCsrf();

        $this->invokeHandler($controller, 'handleDeleteSede');

        $this->assertSame(
            [self::CSRF_ERROR],
            $controller->capturedErrors,
            'an invalid token must abort before the "sede not found" branch'
        );
        $this->assertSame(0, $sedeSpy->getCalls, 'no lookup may happen on a rejected request');
        $this->assertSame(0, $sedeSpy->deleteCalls, 'no delete may happen on a rejected request');
    }

    public function testSedeHandlersCheckCsrfBeforeTouchingTheSedeRepository(): void
    {
        foreach (['handleSaveSede', 'handleDeleteSede'] as $method) {
            $body = $this->methodSource($method);

            $guard = strpos($body, 'isCsrfValid()');
            $repository = strpos($body, '$this->empresa_sede');

            $this->assertNotFalse($guard, $method . ' must check isCsrfValid() explicitly');
            $this->assertNotFalse($repository, $method . ' must keep using the sede repository');
            $this->assertLessThan(
                $repository,
                $guard,
                $method . ' must reject before any repository access'
            );
            $this->assertStringContainsString(
                self::CSRF_ERROR,
                $body,
                $method . ' must surface the CSRF error to the user'
            );
        }
    }

    public function testDeleteTriggerIsACsrfProtectedPostForm(): void
    {
        $src = (string) file_get_contents(FS_FOLDER . '/' . self::BLOCK);

        // The delete marker is a submit button inside the per-sede POST form,
        // not a bare GET link like the pre-existing `delete_cuenta`. That is
        // what lets the handler genuinely reject an invalid token.
        $this->assertMatchesRegularExpression('/<form[^>]*method="post"/', $src);
        $this->assertStringContainsString('type="submit" name="delete_sede"', $src);

        $formStart = strpos($src, '<form');
        $csrfField = strpos($src, '{{ csrf_field() }}');
        $deleteButton = strpos($src, 'name="delete_sede"');

        $this->assertNotFalse($formStart);
        $this->assertNotFalse($csrfField);
        $this->assertNotFalse($deleteButton);
        $this->assertLessThan($csrfField, $formStart, 'the CSRF field must live inside the form');
        $this->assertLessThan(
            $deleteButton,
            $csrfField,
            'the delete trigger must be covered by the form CSRF field'
        );
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * @return array{0: \admin_empresa, 1: object}
     */
    private function controllerWithInvalidCsrf(): array
    {
        $controller = new class extends \admin_empresa {
            /** @var list<string> */
            public array $capturedErrors = [];

            public function __construct()
            {
                // Skip the DB-backed framework constructor on purpose.
            }

            public function isCsrfValid(): bool
            {
                return false;
            }

            public function new_error_msg($msg, $tipo = 'error', $alerta = FALSE, $guardar = TRUE)
            {
                $this->capturedErrors[] = (string) $msg;
            }

            public function new_message($msg, $save = FALSE, $tipo = 'msg', $alerta = FALSE)
            {
            }
        };

        $sedeSpy = new class {
            public int $getCalls = 0;

            public int $saveCalls = 0;

            public int $deleteCalls = 0;

            public function get($cod = '')
            {
                $this->getCalls++;

                return false;
            }

            /** @return list<object> */
            public function all(): array
            {
                return [];
            }

            public function save(): bool
            {
                $this->saveCalls++;

                return true;
            }

            public function delete(): bool
            {
                $this->deleteCalls++;

                return true;
            }
        };

        $controller->empresa_sede = $sedeSpy;

        return [$controller, $sedeSpy];
    }

    private function invokeHandler(\admin_empresa $controller, string $method): void
    {
        $reflection = new \ReflectionMethod('admin_empresa', $method);
        $reflection->setAccessible(true);
        $reflection->invoke($controller);
    }

    private function methodSource(string $method): string
    {
        $reflection = new \ReflectionMethod('admin_empresa', $method);
        $lines = file(FS_FOLDER . '/' . self::CONTROLLER);
        $this->assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

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
}
