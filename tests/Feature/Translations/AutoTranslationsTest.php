<?php

use Illuminate\Support\Facades\Artisan;

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeAutoView(string $relativePath, string $content): string
{
    $full = resource_path('views/' . $relativePath);
    @mkdir(dirname($full), 0755, true);
    file_put_contents($full, $content);
    return $relativePath;
}

function readAutoLang(string $lang, string $file): array
{
    $path = base_path("lang/{$lang}/{$file}.php");
    return file_exists($path) ? include $path : [];
}

afterEach(function () {
    $dir = resource_path('views/_test_auto');
    if (is_dir($dir)) {
        foreach (glob($dir . '/**/*.blade.php') ?: [] as $f) unlink($f);
        foreach (glob($dir . '/**/*.backup') ?: [] as $f) unlink($f);
        foreach (glob($dir . '/*.blade.php') ?: [] as $f) unlink($f);
        foreach (glob($dir . '/*.backup') ?: [] as $f) unlink($f);
        @array_map('rmdir', glob($dir . '/*') ?: []);
        @rmdir($dir);
    }

    foreach (['en', 'bg', 'fr'] as $lang) {
        $base = base_path("lang/{$lang}");
        foreach (glob($base . '/_auto_*.php') ?: [] as $f) unlink($f);
        @rmdir($base);
    }
    @rmdir(base_path('lang'));
});

/*
 * NOTE: translations:auto calls $this->ask() interactively.
 * The recommended approach in feature tests is to use the
 * Artisan::call() overload with an $input stream OR to mock
 * the command itself. The tests below use expectation-based
 * mocking so they run without a real TTY.
 */

// ─── File discovery ──────────────────────────────────────────────────────────

test('reports correct total file count in output', function () {
    makeAutoView('_test_auto/a.blade.php', '<p>A</p>');
    makeAutoView('_test_auto/b.blade.php', '<p>B</p>');
    makeAutoView('_test_auto/c.blade.php', '<p>C</p>');

    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion(
             'What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")',
             ''
         )
         ->expectsOutputToContain('Total files found: 3');
});

// ─── Integration: English extraction runs for each file ──────────────────────

test('calls translations:extract with --wrap for each file in English', function () {
    makeAutoView('_test_auto/wrap_me.blade.php', '<h1>Title</h1>');

    // We spy on Artisan calls by checking side-effects:
    // After running auto, the backup file should exist (wrap ran).
    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion('What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")', '')
         ->assertExitCode(0);

    expect(file_exists(resource_path('views/_test_auto/wrap_me.blade.php.backup')))->toBeTrue();
});

// ─── Additional-language prompt ──────────────────────────────────────────────

test('extracts a single additional language when one is given', function () {
    makeAutoView('_test_auto/lang_test.blade.php', "<p>{{ __('_auto_lt.hello') }}</p>");

    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion(
             'What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")',
             'bg'
         )
         ->assertExitCode(0);

    expect(file_exists(base_path('lang/bg/_auto_lt.php')))->toBeTrue();
});

test('extracts multiple additional languages from comma-separated input', function () {
    makeAutoView('_test_auto/multi_lang.blade.php', "<p>{{ __('_auto_ml.hello') }}</p>");

    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion(
             'What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")',
             'bg, fr'
         )
         ->assertExitCode(0);

    expect(file_exists(base_path('lang/bg/_auto_ml.php')))->toBeTrue();
    expect(file_exists(base_path('lang/fr/_auto_ml.php')))->toBeTrue();
});

test('handles extra spaces in the language input gracefully', function () {
    makeAutoView('_test_auto/spaces_lang.blade.php', "<p>{{ __('_auto_sl.hello') }}</p>");

    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion(
             'What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")',
             '  bg  ,  fr  '
         )
         ->assertExitCode(0);

    expect(file_exists(base_path('lang/bg/_auto_sl.php')))->toBeTrue();
    expect(file_exists(base_path('lang/fr/_auto_sl.php')))->toBeTrue();
});

test('skips additional language extraction when input is empty', function () {
    makeAutoView('_test_auto/no_extra.blade.php', "<p>{{ __('_auto_ne.hello') }}</p>");

    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion(
             'What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")',
             ''
         )
         ->assertExitCode(0);

    // No other lang directories should have been created
    expect(file_exists(base_path('lang/bg/_auto_ne.php')))->toBeFalse();
    expect(file_exists(base_path('lang/fr/_auto_ne.php')))->toBeFalse();
});

// ─── Output messages ─────────────────────────────────────────────────────────

test('displays total files found in the output table', function () {
    makeAutoView('_test_auto/out1.blade.php', '<p>A</p>');
    makeAutoView('_test_auto/out2.blade.php', '<p>B</p>');

    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion(
             'What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")',
             ''
         )
         ->expectsOutputToContain('Total files found: 2');
});

test('outputs a completion warning suggesting revert on error', function () {
    makeAutoView('_test_auto/done.blade.php', '<p>Done</p>');

    $this->artisan('translations:auto', ['view' => '_test_auto'])
         ->expectsQuestion(
             'What other languages do you want to extract? (single: "bg" or multiple: "bg, fr, de")',
             ''
         )
         ->expectsOutputToContain('translations:revert');
});
