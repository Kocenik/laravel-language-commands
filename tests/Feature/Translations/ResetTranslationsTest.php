<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeResetView(string $relativePath, string $content): string
{
    $full = resource_path('views/' . $relativePath);
    @mkdir(dirname($full), 0755, true);
    file_put_contents($full, $content);
    return $relativePath;
}

function makeBackup(string $relativePath, string $content): void
{
    $full = resource_path('views/' . $relativePath . '.backup');
    @mkdir(dirname($full), 0755, true);
    file_put_contents($full, $content);
}

function readResetView(string $relativePath): string
{
    return file_get_contents(resource_path('views/' . $relativePath));
}

afterEach(function () {
    $dir = resource_path('views/_test_reset');
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.blade.php') ?: [] as $f) unlink($f);
        foreach (glob($dir . '/*.backup') ?: [] as $f) unlink($f);
        @rmdir($dir);
    }
});

// ─── Guard: path not found ───────────────────────────────────────────────────

test('returns 1 when the given view path does not exist', function () {
    $code = Artisan::call('translations:reset', ['view' => '_test_reset/nonexistent.blade.php']);
    expect($code)->toBe(1);
});

// ─── No backups found ────────────────────────────────────────────────────────

test('returns 0 and reports no backups when none exist for a file', function () {
    makeResetView('_test_reset/no_backup.blade.php', '<p>Current</p>');

    $code = Artisan::call('translations:reset', ['view' => '_test_reset/no_backup.blade.php']);
    $output = Artisan::output();

    expect($code)->toBe(0);
    expect($output)->toContain('No backups found');
});

test('returns 0 and reports no backups when a directory has no .backup files', function () {
    makeResetView('_test_reset/dir_no_backup.blade.php', '<p>Content</p>');

    $code = Artisan::call('translations:reset', ['view' => '_test_reset']);
    $output = Artisan::output();

    expect($code)->toBe(0);
    expect($output)->toContain('No backups found');
});

// ─── Single-file reset ───────────────────────────────────────────────────────

test('replaces the original file with the backup content for a single file', function () {
    makeResetView('_test_reset/single.blade.php', '<p>Modified</p>');
    makeBackup('_test_reset/single.blade.php', '<p>Original</p>');

    $this->artisan('translations:reset', ['view' => '_test_reset/single.blade.php'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes');

    expect(readResetView('_test_reset/single.blade.php'))->toBe('<p>Original</p>');
});

test('returns 0 after a successful single-file reset', function () {
    makeResetView('_test_reset/exit_ok.blade.php', '<p>New</p>');
    makeBackup('_test_reset/exit_ok.blade.php', '<p>Old</p>');

    $this->artisan('translations:reset', ['view' => '_test_reset/exit_ok.blade.php'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes')
         ->assertExitCode(0);
});

// ─── Directory reset ─────────────────────────────────────────────────────────

test('resets all files that have a matching backup inside a directory', function () {
    makeResetView('_test_reset/file_a.blade.php', 'Modified A');
    makeBackup('_test_reset/file_a.blade.php', 'Original A');
    makeResetView('_test_reset/file_b.blade.php', 'Modified B');
    makeBackup('_test_reset/file_b.blade.php', 'Original B');

    $this->artisan('translations:reset', ['view' => '_test_reset'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes');

    expect(readResetView('_test_reset/file_a.blade.php'))->toBe('Original A');
    expect(readResetView('_test_reset/file_b.blade.php'))->toBe('Original B');
});

test('ignores files inside a directory that have no backup counterpart', function () {
    makeResetView('_test_reset/has_backup.blade.php', 'Modified');
    makeBackup('_test_reset/has_backup.blade.php', 'Original');
    makeResetView('_test_reset/no_backup.blade.php', 'Stays as-is');

    $this->artisan('translations:reset', ['view' => '_test_reset'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes');

    expect(readResetView('_test_reset/no_backup.blade.php'))->toBe('Stays as-is');
});

// ─── User cancellation ───────────────────────────────────────────────────────

test('does not replace files when the user declines the confirmation', function () {
    makeResetView('_test_reset/cancel.blade.php', '<p>Modified</p>');
    makeBackup('_test_reset/cancel.blade.php', '<p>Original</p>');

    $this->artisan('translations:reset', ['view' => '_test_reset/cancel.blade.php'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'no');

    expect(readResetView('_test_reset/cancel.blade.php'))->toBe('<p>Modified</p>');
});

test('returns 0 when the user declines (operation cancelled gracefully)', function () {
    makeResetView('_test_reset/cancel_exit.blade.php', 'New');
    makeBackup('_test_reset/cancel_exit.blade.php', 'Old');

    $this->artisan('translations:reset', ['view' => '_test_reset/cancel_exit.blade.php'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'no')
         ->assertExitCode(0);
});

// ─── --destroy flag ──────────────────────────────────────────────────────────

test('removes backup files after reset when --destroy is passed', function () {
    makeResetView('_test_reset/destroy.blade.php', '<p>Modified</p>');
    makeBackup('_test_reset/destroy.blade.php', '<p>Original</p>');

    $this->artisan('translations:reset', [
        'view'      => '_test_reset/destroy.blade.php',
        '--destroy' => true,
    ])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes');

    $backupPath = resource_path('views/_test_reset/destroy.blade.php.backup');
    expect(file_exists($backupPath))->toBeFalse();
});

test('keeps backup files after reset when --destroy is NOT passed', function () {
    makeResetView('_test_reset/keep_backup.blade.php', '<p>Modified</p>');
    makeBackup('_test_reset/keep_backup.blade.php', '<p>Original</p>');

    $this->artisan('translations:reset', ['view' => '_test_reset/keep_backup.blade.php'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes');

    $backupPath = resource_path('views/_test_reset/keep_backup.blade.php.backup');
    expect(file_exists($backupPath))->toBeTrue();
});

test('reports backup deletion in output when --destroy is used', function () {
    makeResetView('_test_reset/destroy_msg.blade.php', '<p>X</p>');
    makeBackup('_test_reset/destroy_msg.blade.php', '<p>Y</p>');

    $this->artisan('translations:reset', [
        'view'      => '_test_reset/destroy_msg.blade.php',
        '--destroy' => true,
    ])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes')
         ->expectsOutputToContain('Backup files deleted');
});

// ─── Success output ──────────────────────────────────────────────────────────

test('prints a success message after resetting', function () {
    makeResetView('_test_reset/success_msg.blade.php', 'New');
    makeBackup('_test_reset/success_msg.blade.php', 'Old');

    $this->artisan('translations:reset', ['view' => '_test_reset/success_msg.blade.php'])
         ->expectsConfirmation('Do you want to proceed with replacing these files?', 'yes')
         ->expectsOutputToContain('reset successfully');
});

// ─── Backup without corresponding original ───────────────────────────────────

test('does not list a backup in the table when its original file is missing', function () {
    // Only the backup exists, no original
    makeBackup('_test_reset/orphan.blade.php', '<p>Orphan</p>');

    $code = Artisan::call('translations:reset', ['view' => '_test_reset']);
    $output = Artisan::output();

    expect($output)->toContain('No backups found');
    expect($code)->toBe(0);
});
