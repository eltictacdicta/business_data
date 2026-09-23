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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
 * Regression for the second `fs_settings` loading gap found by the audit of
 * `plugins/business_data`.
 *
 * `admin_empresa::save_traducciones()` guarded its write with
 * `class_exists('fs_settings')` — the autoloading form. `fs_settings` is a
 * legacy global class in `base/fs_settings.php`, not PSR-4, and the modern
 * entry path does not register the legacy class map, so `class_exists()`
 * returned FALSE and the whole block was skipped in silence: document
 * translations were never persisted while the page still reported
 * "Datos guardados correctamente.".
 *
 * The fix routes every `fs_settings` use in the plugin through the single
 * guard `empresa_sede::settings()` (now public), which loads the class on
 * demand exactly like the core precedent in `src/Core/Html.php`.
 *
 * Like EmpresaSedeEntryPointLoadingTest, the runtime probes spawn a fresh PHP
 * process that never preloads `fs_settings`: an in-process test can never prove
 * entry-point independence because an earlier test may already have loaded the
 * class. `fs_settings` is deliberately NEVER required by the test setup.
 */
final class AdminEmpresaTraduccionesSettingsTest extends TestCase
{
    private const TMP_DIR_NAME = 'admin_empresa_traducciones_probe/';

    private const CONTROLLER = 'plugins/business_data/controller/admin_empresa.php';

    /** @var list<string> */
    private array $tempScripts = [];

    private static bool $dependenciesLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempScripts as $script) {
            if (is_file($script)) {
                @unlink($script);
            }
        }
        $this->tempScripts = [];

        $this->removeProbeTmpDir();

        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];

        parent::tearDown();
    }

    // =====================================================================
    // Runtime probe 1 — the shared guard is public and loads on demand
    // =====================================================================

    /**
     * The guard must be reusable from a sibling file (it is the single
     * plugin-wide `fs_settings` accessor) and must resolve the class on demand
     * from an entry point that never preloaded it.
     */
    public function testSharedGuardIsPublicAndLoadsFsSettingsFromACleanEntryPoint(): void
    {
        $snippet = $this->modelBootstrapSnippet() . <<<'PHP'
if (class_exists('fs_settings', false)) {
    fwrite(STDERR, "PRELOADED\n");
    exit(3);
}

$settings = empresa_sede::settings();
if (!$settings instanceof fs_settings) {
    fwrite(STDERR, "NOT_AN_FS_SETTINGS\n");
    exit(4);
}

if (!class_exists('fs_settings', false)) {
    fwrite(STDERR, "NOT_LOADED_AFTER_GUARD\n");
    exit(5);
}

echo "ok\n";
exit(0);
PHP;

        $result = $this->runInCleanProcess($snippet);

        $this->assertSame(
            0,
            $result['code'],
            "empresa_sede::settings() must be public and resolve fs_settings on demand.\n" . $result['output']
        );
        $this->assertStringNotContainsString('Class "fs_settings" not found', $result['output']);
        $this->assertStringNotContainsString('private method', $result['output']);
        $this->assertSame('ok', trim($result['stdout']));
    }

    // =====================================================================
    // Runtime probe 2 — the real save path persists from a clean entry point
    // =====================================================================

    /**
     * The exact production failure: the translations save must reach
     * `fs_settings::save()` and persist from an entry point that never
     * preloaded `fs_settings`.
     */
    public function testPersistTraduccionesWritesConfigFromACleanEntryPoint(): void
    {
        $snippet = $this->controllerBootstrapSnippet() . <<<'PHP'
if (class_exists('fs_settings', false)) {
    fwrite(STDERR, "PRELOADED\n");
    exit(3);
}

$GLOBALS['config2'] = [];
$traducciones = [
    'FACTURA' => 'FACTURA TEST',
    'ALBARAN' => 'ALBARAN TEST',
    'PEDIDO' => 'PEDIDO TEST',
];

if (!admin_empresa::persistTraducciones($traducciones)) {
    fwrite(STDERR, "PERSIST_FAILED\n");
    exit(4);
}

if (!class_exists('fs_settings', false)) {
    fwrite(STDERR, "NOT_LOADED_AFTER_PERSIST\n");
    exit(5);
}

if (($GLOBALS['config2']['FACTURA'] ?? null) !== 'FACTURA TEST') {
    fwrite(STDERR, "CONFIG2_NOT_SET\n");
    exit(6);
}

echo json_encode($GLOBALS['config2']), "\n";
exit(0);
PHP;

        $result = $this->runInCleanProcess($snippet);

        $this->assertSame(
            0,
            $result['code'],
            "admin_empresa::persistTraducciones() must write config2 from a clean entry point.\n" . $result['output']
        );
        $this->assertStringNotContainsString('Class "fs_settings" not found', $result['output']);
        $this->assertStringNotContainsString('undefined method', $result['output']);

        $decoded = json_decode(trim($result['stdout']), true);
        $this->assertIsArray($decoded);
        $this->assertSame('FACTURA TEST', $decoded['FACTURA'] ?? null);
        $this->assertSame('ALBARAN TEST', $decoded['ALBARAN'] ?? null);
        $this->assertSame('PEDIDO TEST', $decoded['PEDIDO'] ?? null);
    }

    // =====================================================================
    // Source contract — the controller routes through the shared guard
    // =====================================================================

    /**
     * `save_traducciones()` reads `filter_input()` (un-stubbable in CLI), so
     * the runtime probe above drives the extracted seam. This contract pins
     * that the real method routes through that seam and never reintroduces the
     * buggy autoload-form `class_exists('fs_settings')` check.
     */
    public function testSaveTraduccionesRoutesThroughTheSharedGuard(): void
    {
        $this->loadDependencies();

        $body = $this->methodSource('admin_empresa', 'save_traducciones');

        $this->assertStringNotContainsString(
            "class_exists('fs_settings'",
            $body,
            'save_traducciones() must not use the autoload-form class_exists() check that silently skipped the write'
        );
        $this->assertStringContainsString(
            'self::persistTraducciones(',
            $body,
            'save_traducciones() must persist through the extracted seam'
        );
        $this->assertStringContainsString(
            'new_error_msg(',
            $body,
            'a failed persistence must be reported instead of silently reporting success'
        );

        $seam = $this->methodSource('admin_empresa', 'persistTraducciones');
        $this->assertStringContainsString(
            'empresa_sede::settings()',
            $seam,
            'the seam must use the single plugin-wide fs_settings guard'
        );
        $this->assertStringNotContainsString(
            "class_exists('fs_settings'",
            $seam,
            'the seam must not carry a second copy of the guard'
        );
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Defines the constants the model needs and requires ONLY the framework
     * model base plus the model under test. `fs_settings` is deliberately NOT
     * required: that is the gap under test.
     */
    private function modelBootstrapSnippet(): string
    {
        $folder = var_export(FS_FOLDER, true);

        return <<<PHP
<?php
define('FS_FOLDER', {$folder});
define('FS_TMP_NAME', '{$this->tmpName()}');
require FS_FOLDER . '/base/fs_model.php';
require FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';

PHP;
    }

    /**
     * Defines the framework constants a clean entry point needs (mirroring
     * `tests/bootstrap.php`) and requires the framework controller base plus
     * the controller under test. `fs_settings` is deliberately NOT required.
     */
    private function controllerBootstrapSnippet(): string
    {
        $folder = var_export(FS_FOLDER, true);
        $tmpName = $this->tmpName();

        return <<<PHP
<?php
define('FS_FOLDER', {$folder});
define('FS_TMP_NAME', '{$tmpName}');
define('FS_DB_TYPE', 'MYSQL');
define('FS_DB_INTEGER', 'INT(11)');
define('FS_DB_HOST', 'db');
define('FS_DB_PORT', '3306');
define('FS_DB_NAME', 'db');
define('FS_DB_USER', 'db');
define('FS_DB_PASS', 'db');
define('FS_IP_WHITELIST', '*');
define('FS_MYDOCS', 'documentos');
define('FS_MAX_DECIMALS', 2);
define('FS_NF0', 2);
define('FS_NF1', ',');
define('FS_NF2', '.');
define('FS_POS_DIVISA', 'right');
define('FS_ITEM_LIMIT', 50);
define('FS_COOKIES_EXPIRE', 31536000);
define('FS_VENTAS_SIN_STOCK', false);
define('FS_DB_HISTORY', false);
define('FS_FOREIGN_KEYS', true);
define('FS_CHECK_DB_TYPES', true);
define('FS_PATH', '');
define('FS_SECRET_KEY', '9f1c2d3e4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d');
define('FS_LAZY_MODELS', true);
\$GLOBALS['plugins'] = [];

chdir(FS_FOLDER);
require FS_FOLDER . '/vendor/autoload.php';
require FS_FOLDER . '/base/fs_controller.php';
require FS_FOLDER . '/plugins/business_data/model/empresa.php';
require FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
require FS_FOLDER . '/plugins/business_data/controller/admin_empresa.php';

\$tmpDir = FS_FOLDER . '/tmp/' . FS_TMP_NAME;
if (!is_dir(\$tmpDir) && !mkdir(\$tmpDir, 0777, true) && !is_dir(\$tmpDir)) {
    fwrite(STDERR, "NO_TMP_DIR\n");
    exit(7);
}

PHP;
    }

    private function tmpName(): string
    {
        return self::TMP_DIR_NAME;
    }

    private function removeProbeTmpDir(): void
    {
        $tmpDir = FS_FOLDER . '/tmp/' . self::TMP_DIR_NAME;
        $configFile = $tmpDir . 'config2.ini';
        if (is_file($configFile)) {
            @unlink($configFile);
        }
        if (is_dir($tmpDir)) {
            @rmdir($tmpDir);
        }
    }

    /**
     * DB-free load order. `fs_controller` MUST be loaded before the controller
     * file, otherwise including `admin_empresa.php` fails with
     * `Class "fs_controller" not found`.
     */
    private function loadDependencies(): void
    {
        if (self::$dependenciesLoaded) {
            return;
        }

        require_once FS_FOLDER . '/base/fs_controller.php';
        require_once FS_FOLDER . '/base/fs_core_log.php';
        // `fs_settings` is deliberately NOT preloaded: `empresa_sede` must
        // resolve it on demand.
        require_once FS_FOLDER . '/plugins/business_data/model/empresa.php';
        require_once FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
        require_once FS_FOLDER . '/' . self::CONTROLLER;

        self::$dependenciesLoaded = true;
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

    /**
     * Runs a snippet in a brand-new PHP process. Skips (never fails) when the
     * PHP binary cannot be resolved, so an environment without a usable CLI
     * binary does not report a spurious regression.
     *
     * @return array{code: int, stdout: string, stderr: string, output: string}
     */
    private function runInCleanProcess(string $snippet): array
    {
        $php = $this->resolvePhpBinary();
        if ($php === null) {
            $this->markTestSkipped('No usable PHP CLI binary could be resolved for the subprocess probe.');
        }

        $script = tempnam(sys_get_temp_dir(), 'admin_empresa_trad_');
        if ($script === false) {
            $this->markTestSkipped('Could not create a temporary script for the subprocess probe.');
        }

        file_put_contents($script, $snippet);
        $this->tempScripts[] = $script;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([$php, $script], $descriptors, $pipes);
        if (!is_resource($process)) {
            $this->markTestSkipped('proc_open() could not start the PHP subprocess probe.');
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [
            'code' => $code,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'output' => $stdout . $stderr,
        ];
    }

    private function resolvePhpBinary(): ?string
    {
        $candidates = [PHP_BINARY, PHP_BINDIR . '/php', 'php'];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if ($candidate === 'php') {
                $resolved = trim((string) @shell_exec('command -v php 2>/dev/null'));

                return $resolved !== '' ? $resolved : null;
            }
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
