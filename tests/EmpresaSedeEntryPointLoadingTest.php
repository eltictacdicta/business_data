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

/**
 * Entry-point independence regression for `empresa_sede`.
 *
 * Production reaches `empresa_sede::mapping()` from the PDF print path
 * (`index.php` -> `FacturaPdf1Controller` -> print view -> `RelatedModelsLoader`)
 * where `base/fs_settings.php` is NOT preloaded: the legacy class map in
 * `base/fs_autoload.php` is not wired into the modern entry path.
 *
 * The whole unit corpus stayed green while production fataled with
 * `Class "fs_settings" not found` because every setup preloaded `fs_settings`
 * itself, masking the model's own loading gap. A same-process test can never
 * prove entry-point independence: once any earlier test loaded the class, the
 * guard is never exercised. This test therefore spawns a fresh PHP process that
 * loads ONLY `base/fs_model.php` and the model, and asserts the model resolves
 * its own dependency.
 */
final class EmpresaSedeEntryPointLoadingTest extends TestCase
{
    private const TMP_DIR_NAME = 'empresa_sede_entrypoint_probe/';

    /** @var list<string> */
    private array $tempScripts = [];

    protected function tearDown(): void
    {
        foreach ($this->tempScripts as $script) {
            if (is_file($script)) {
                @unlink($script);
            }
        }
        $this->tempScripts = [];

        $tmpDir = FS_FOLDER . '/tmp/' . self::TMP_DIR_NAME;
        $configFile = $tmpDir . 'config2.ini';
        if (is_file($configFile)) {
            @unlink($configFile);
        }
        if (is_dir($tmpDir)) {
            @rmdir($tmpDir);
        }

        parent::tearDown();
    }

    /**
     * The exact production failure: `mapping()` must work from an entry point
     * that never preloaded `fs_settings`, and must load it on demand.
     */
    public function testMappingLoadsFsSettingsFromAnEntryPointThatNeverPreloadedIt(): void
    {
        $snippet = $this->bootstrapSnippet() . <<<'PHP'
if (class_exists('fs_settings', false)) {
    fwrite(STDERR, "PRELOADED\n");
    exit(3);
}

$mapping = empresa_sede::mapping();
if (!class_exists('fs_settings', false)) {
    fwrite(STDERR, "NOT_LOADED_AFTER_MAPPING\n");
    exit(4);
}

echo json_encode($mapping), "\n";
exit(0);
PHP;

        $result = $this->runInCleanProcess($snippet);

        $this->assertSame(
            0,
            $result['code'],
            "empresa_sede::mapping() must resolve fs_settings on demand.\n" . $result['output']
        );
        $this->assertStringNotContainsString('Class "fs_settings" not found', $result['output']);
        $this->assertStringNotContainsString('Fatal error', $result['output']);

        $decoded = json_decode(trim($result['stdout']), true);
        $this->assertIsArray($decoded);
        $this->assertSame(['presupuesto', 'albaran', 'pedido', 'factura'], array_keys($decoded));
        foreach ($decoded as $value) {
            $this->assertNull($value, 'An unconfigured mapping must resolve to null.');
        }
    }

    /**
     * The second `fs_settings` call site (`setMappingFor()`) must resolve the
     * dependency the same way, from the same clean entry point.
     */
    public function testSetMappingForLoadsFsSettingsFromAnEntryPointThatNeverPreloadedIt(): void
    {
        $snippet = $this->bootstrapSnippet() . <<<'PHP'
if (class_exists('fs_settings', false)) {
    fwrite(STDERR, "PRELOADED\n");
    exit(3);
}

$GLOBALS['config2'] = [];
$tmpDir = FS_FOLDER . '/tmp/' . FS_TMP_NAME;
if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
    fwrite(STDERR, "NO_TMP_DIR\n");
    exit(5);
}

$loader = static function (string $cod): ?empresa_sede {
    return new class() extends empresa_sede {
        public function __construct()
        {
        }
    };
};

if (!empresa_sede::setMappingFor('presupuesto', 'S1', $loader)) {
    fwrite(STDERR, "SET_MAPPING_FAILED\n");
    exit(6);
}

if (!class_exists('fs_settings', false)) {
    fwrite(STDERR, "NOT_LOADED_AFTER_SET_MAPPING\n");
    exit(7);
}

echo "ok\n";
exit(0);
PHP;

        $result = $this->runInCleanProcess($snippet);

        $this->assertSame(
            0,
            $result['code'],
            "empresa_sede::setMappingFor() must resolve fs_settings on demand.\n" . $result['output']
        );
        $this->assertStringNotContainsString('Class "fs_settings" not found', $result['output']);
        $this->assertStringNotContainsString('Fatal error', $result['output']);
        $this->assertSame('ok', trim($result['stdout']));
    }

    /**
     * Defines the constants the model needs and requires ONLY the framework
     * model base plus the model under test. `fs_settings` is deliberately NOT
     * required: that is the gap under test.
     */
    private function bootstrapSnippet(): string
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

    private function tmpName(): string
    {
        return self::TMP_DIR_NAME;
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

        $script = tempnam(sys_get_temp_dir(), 'sede_entry_');
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
