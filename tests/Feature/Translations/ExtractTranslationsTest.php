<?php

use Illuminate\Support\Facades\Artisan;

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeExtractView(string $relativePath, string $content): string
{
    $full = resource_path('views/' . $relativePath);
    @mkdir(dirname($full), 0755, true);
    file_put_contents($full, $content);
    return $relativePath;
}

function langFile(string $lang, string $file): string
{
    return base_path("lang/{$lang}/{$file}.php");
}

function readLang(string $lang, string $file): array
{
    $path = langFile($lang, $file);
    return file_exists($path) ? include $path : [];
}

afterEach(function () {
    // Clean views
    $dir = resource_path('views/_test_extract');
    if (is_dir($dir)) {
        array_map('unlink', glob($dir . '/*') ?: []);
        @rmdir($dir);
    }

    // Clean lang files created during tests
    foreach (['en', 'bg', 'fr'] as $lang) {
        $base = base_path("lang/{$lang}");
        if (is_dir($base)) {
            foreach (glob($base . '/_test_*.php') ?: [] as $f) {
                unlink($f);
            }
            // Remove directory only if empty
            @rmdir($base);
        }
    }
    @rmdir(base_path('lang'));
});

// ─── File-not-found guard ────────────────────────────────────────────────────

test('returns 1 when view file does not exist', function () {
    $code = Artisan::call('translations:extract', ['view' => '_test_extract/missing.blade.php']);
    expect($code)->toBe(1);
});

// ─── No-keys early exit ──────────────────────────────────────────────────────

test('returns 0 and reports no keys when view has no __() calls', function () {
    makeExtractView('_test_extract/nokeys.blade.php', '<p>Plain text</p>');

    $code = Artisan::call('translations:extract', ['view' => '_test_extract/nokeys.blade.php']);
    $output = Artisan::output();

    expect($code)->toBe(0);
    expect($output)->toContain('No translation keys found');
});

// ─── Basic extraction ────────────────────────────────────────────────────────

test('extracts a single key and writes it to the lang file', function () {
    makeExtractView('_test_extract/basic.blade.php', "<p>{{ __('_test_basic.hello_world') }}</p>");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/basic.blade.php',
        '--lang' => 'en',
    ]);

    $translations = readLang('en', '_test_basic');
    expect($translations)->toHaveKey('hello_world');
});

test('formats the value as title-cased words from the key', function () {
    makeExtractView('_test_extract/format.blade.php', "<p>{{ __('_test_format.my_key') }}</p>");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/format.blade.php',
        '--lang' => 'en',
    ]);

    $translations = readLang('en', '_test_format');
    expect($translations['my_key'])->toBe('My Key');
});

test('extracts multiple unique keys from a single file', function () {
    $content = "{{ __('_test_multi.first') }} {{ __('_test_multi.second') }} {{ __('_test_multi.third') }}";
    makeExtractView('_test_extract/multi.blade.php', $content);

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/multi.blade.php',
        '--lang' => 'en',
    ]);

    $translations = readLang('en', '_test_multi');
    expect($translations)->toHaveKeys(['first', 'second', 'third']);
});

test('deduplicates repeated keys – writes each only once', function () {
    $content = "{{ __('_test_dedup.hello') }} {{ __('_test_dedup.hello') }}";
    makeExtractView('_test_extract/dedup.blade.php', $content);

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/dedup.blade.php',
        '--lang' => 'en',
    ]);

    $path    = langFile('en', '_test_dedup');
    $raw     = file_get_contents($path);
    $occurrences = substr_count($raw, "'hello'");
    expect($occurrences)->toBe(1);
});

// ─── Lang file management ────────────────────────────────────────────────────

test('creates the lang directory and file if they do not exist', function () {
    makeExtractView('_test_extract/newdir.blade.php', "{{ __('_test_newdir.key') }}");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/newdir.blade.php',
        '--lang' => 'en',
    ]);

    expect(file_exists(langFile('en', '_test_newdir')))->toBeTrue();
});

test('appends to an existing lang file without overwriting existing keys', function () {
    $path = langFile('en', '_test_append');
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, "<?php\n\nreturn [\n    'existing' => 'Existing',\n];\n");

    makeExtractView('_test_extract/append.blade.php', "{{ __('_test_append.new_key') }}");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/append.blade.php',
        '--lang' => 'en',
    ]);

    $translations = readLang('en', '_test_append');
    expect($translations)->toHaveKey('existing');
    expect($translations)->toHaveKey('new_key');
});

test('does not overwrite a key that already exists in the lang file', function () {
    $path = langFile('en', '_test_nooverwrite');
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, "<?php\n\nreturn [\n    'title' => 'My Custom Title',\n];\n");

    makeExtractView('_test_extract/nooverwrite.blade.php', "{{ __('_test_nooverwrite.title') }}");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/nooverwrite.blade.php',
        '--lang' => 'en',
    ]);

    $translations = readLang('en', '_test_nooverwrite');
    expect($translations['title'])->toBe('My Custom Title');
});

// ─── Language option ─────────────────────────────────────────────────────────

test('defaults to the "en" language when --lang is not specified', function () {
    makeExtractView('_test_extract/default_lang.blade.php', "{{ __('_test_deflang.hello') }}");

    Artisan::call('translations:extract', ['view' => '_test_extract/default_lang.blade.php']);

    expect(file_exists(langFile('en', '_test_deflang')))->toBeTrue();
});

test('writes to the correct language directory when --lang is specified', function () {
    makeExtractView('_test_extract/bulgarian.blade.php', "{{ __('_test_bulg.hello') }}");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/bulgarian.blade.php',
        '--lang' => 'bg',
    ]);

    expect(file_exists(langFile('bg', '_test_bulg')))->toBeTrue();
    expect(file_exists(langFile('en', '_test_bulg')))->toBeFalse();
});

// ─── Invalid key formats ─────────────────────────────────────────────────────

test('records an error for keys without exactly one dot', function () {
    // key with NO dot
    makeExtractView('_test_extract/badkey.blade.php', "{{ __('nodotkey') }}");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/badkey.blade.php',
        '--lang' => 'en',
    ]);

    expect(Artisan::output())->toContain('Unresolved Keys');
});

test('records an error for keys with more than one dot', function () {
    makeExtractView('_test_extract/manydots.blade.php', "{{ __('a.b.c') }}");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/manydots.blade.php',
        '--lang' => 'en',
    ]);

    expect(Artisan::output())->toContain('Unresolved Keys');
});

// ─── Comment stripping ───────────────────────────────────────────────────────

test('ignores translation keys inside Blade comments', function () {
    $content = "{{-- {{ __('_test_bcomment.hidden') }} --}}";
    makeExtractView('_test_extract/blade_comment.blade.php', $content);

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/blade_comment.blade.php',
        '--lang' => 'en',
    ]);

    expect(Artisan::output())->toContain('No translation keys found');
});

test('ignores translation keys inside HTML comments', function () {
    $content = "<!-- {{ __('_test_hcomment.hidden') }} -->";
    makeExtractView('_test_extract/html_comment.blade.php', $content);

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/html_comment.blade.php',
        '--lang' => 'en',
    ]);

    expect(Artisan::output())->toContain('No translation keys found');
});

// ─── --wrap flag ─────────────────────────────────────────────────────────────

test('calls translations:wrap first when --wrap flag is set', function () {
    // A file with a plain text node – wrap will convert it to __()
    makeExtractView('_test_extract/with_wrap.blade.php', '<p>Wrap Me</p>');

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/with_wrap.blade.php',
        '--lang' => 'en',
        '--wrap' => true,
    ]);

    $output = Artisan::output();
    // WrapTranslations prints this banner
    expect($output)->toContain('Starting Wrapper');
});

// ─── CRUD file naming (resolveFileName) ──────────────────────────────────────

test('resolveFileName keeps full name for crud-suffixed files like articles_index', function () {
    makeExtractView('_test_extract/crud_resolve.blade.php',
                    "{{ __('articles_index.title') }}");

    Artisan::call('translations:extract', [
        'view'   => '_test_extract/crud_resolve.blade.php',
        '--lang' => 'en',
    ]);

    expect(file_exists(langFile('en', 'articles_index')))->toBeTrue();
});

// ─── Return code & success output ────────────────────────────────────────────

test('returns 0 on a fully successful extraction', function () {
    makeExtractView('_test_extract/success.blade.php', "{{ __('_test_success.done') }}");

    $code = Artisan::call('translations:extract', [
        'view'   => '_test_extract/success.blade.php',
        '--lang' => 'en',
    ]);

    expect($code)->toBe(0);
    expect(Artisan::output())->toContain('Extraction Finished');
});
