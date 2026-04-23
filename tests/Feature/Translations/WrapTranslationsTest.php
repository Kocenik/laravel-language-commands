<?php

use Illuminate\Support\Facades\Artisan;

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Create a real blade file under resources/views and return its path
 * relative to resources/views (i.e. what you pass as the {view} argument).
 */
function makeView(string $relativePath, string $content): string
{
    $full = resource_path('views/' . $relativePath);
    @mkdir(dirname($full), 0755, true);
    file_put_contents($full, $content);
    return $relativePath;
}

function readView(string $relativePath): string
{
    return file_get_contents(resource_path('views/' . $relativePath));
}

function viewExists(string $relativePath): bool
{
    return file_exists(resource_path('views/' . $relativePath));
}

// Clean up after each test
afterEach(function () {
    $dir = resource_path('views/_test_wrap');
    if (is_dir($dir)) {
        // Recursively remove files and subdirectories
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
});

// ─── File-level guard ────────────────────────────────────────────────────────

test('returns error code 1 when file does not exist', function () {
    $code = Artisan::call('translations:wrap', ['view' => '_test_wrap/nonexistent.blade.php']);
    expect($code)->toBe(1);
});

// ─── Backup creation ─────────────────────────────────────────────────────────

test('creates a .backup file alongside the original', function () {
    $view = makeView('_test_wrap/hello.blade.php', '<p>Hello World</p>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(viewExists('_test_wrap/hello.blade.php.backup'))->toBeTrue();
});

test('backup contains the original (pre-wrap) content', function () {
    $original = '<p>Hello World</p>';
    $view = makeView('_test_wrap/backup_check.blade.php', $original);

    Artisan::call('translations:wrap', ['view' => $view]);

    $backup = file_get_contents(resource_path('views/_test_wrap/backup_check.blade.php.backup'));
    expect($backup)->toBe($original);
});

// ─── Core wrapping ───────────────────────────────────────────────────────────

test('wraps a plain text node between HTML tags', function () {
    $view = makeView('_test_wrap/simple.blade.php', '<p>Hello World</p>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/simple.blade.php'))
        ->toContain("__(");
});

test('does not double-wrap already-wrapped strings', function () {
    $content = "<p>{{ __('simple.hello_world') }}</p>";
    $view    = makeView('_test_wrap/already.blade.php', $content);

    Artisan::call('translations:wrap', ['view' => $view]);

    // Should appear exactly once – wrapping again would produce __(__(...
    $result = readView('_test_wrap/already.blade.php');
    expect(substr_count($result, "__('simple.hello_world')"))->toBe(1);
    expect($result)->not->toContain("__(__(");
});

test('does not wrap numeric-only text', function () {
    $view = makeView('_test_wrap/numeric.blade.php', '<span>42</span>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/numeric.blade.php'))->not->toContain("__(");
});

test('does not wrap whitespace-only text nodes', function () {
    $view = makeView('_test_wrap/whitespace.blade.php', "<div>   </div>");

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/whitespace.blade.php'))->not->toContain("__(");
});

test('skips strings already inside Blade comments', function () {
    $view = makeView('_test_wrap/blade_comment.blade.php', '<p>{{-- This is a comment --}}</p>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/blade_comment.blade.php'))->not->toContain("__(");
});

test('skips strings inside HTML comments', function () {
    $view = makeView('_test_wrap/html_comment.blade.php', '<p><!-- This is a comment --></p>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/html_comment.blade.php'))->not->toContain("__(");
});

// ─── Translation-key prefix logic ────────────────────────────────────────────

test('uses filename as prefix for non-crud views', function () {
    $view = makeView('_test_wrap/dashboard.blade.php', '<h1>Welcome</h1>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/dashboard.blade.php'))->toContain("'dashboard.");
});

test('uses parent_action prefix when filename is a crud action', function () {
    @mkdir(resource_path('views/_test_wrap/articles'), 0755, true);
    $view = makeView('_test_wrap/articles/index.blade.php', '<h1>All Articles</h1>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/articles/index.blade.php'))->toContain("'articles_index.");
});

test('prefix strips .blade.php extension', function () {
    $view = makeView('_test_wrap/profile.blade.php', '<h1>Profile</h1>');

    Artisan::call('translations:wrap', ['view' => $view]);

    $result = readView('_test_wrap/profile.blade.php');
    // Should NOT contain the literal extension in the key
    expect($result)->not->toContain('blade_php');
    expect($result)->toContain("'profile.");
});

// ─── Key generation ──────────────────────────────────────────────────────────

test('converts spaces to underscores in generated keys', function () {
    $view = makeView('_test_wrap/keys.blade.php', '<p>Hello World</p>');

    Artisan::call('translations:wrap', ['view' => $view]);

    expect(readView('_test_wrap/keys.blade.php'))->toContain('hello_world');
});

test('removes punctuation from generated keys', function () {
    $view = makeView('_test_wrap/punct.blade.php', '<p>Hello, World!</p>');

    Artisan::call('translations:wrap', ['view' => $view]);

    $result = readView('_test_wrap/punct.blade.php');
    // Punctuation should not appear inside the key portion
    expect($result)->toContain('hello_world');
});

test('lowercases generated keys', function () {
    $view = makeView('_test_wrap/case.blade.php', '<p>UPPERCASE TEXT</p>');

    Artisan::call('translations:wrap', ['view' => $view]);

    $result = readView('_test_wrap/case.blade.php');
    expect($result)->toContain('uppercase_text');
});

// ─── Return codes & output ───────────────────────────────────────────────────

test('returns 0 on success', function () {
    $view = makeView('_test_wrap/exit.blade.php', '<p>Done</p>');

    $code = Artisan::call('translations:wrap', ['view' => $view]);

    expect($code)->toBe(0);
});

test('warns when no strings were wrapped', function () {
    // A file with no translatable text nodes
    $view = makeView('_test_wrap/empty.blade.php', '<div></div>');

    Artisan::call('translations:wrap', ['view' => $view]);
    $output = Artisan::output();

    expect($output)->toContain('No strings were wrapped');
});

test('reports the count of wrapped strings', function () {
    $view = makeView('_test_wrap/count.blade.php', "<p>First</p>\n<p>Second</p>\n<p>Third</p>");

    Artisan::call('translations:wrap', ['view' => $view]);
    $output = Artisan::output();

    expect($output)->toContain('Total strings wrapped: 3');
});

// ─── Edge cases ───────────────────────────────────────────────────────────────

test('multiple text nodes are all wrapped', function () {
    $view = makeView('_test_wrap/multi.blade.php', "<p>First</p><p>Second</p>");

    Artisan::call('translations:wrap', ['view' => $view]);

    $result = readView('_test_wrap/multi.blade.php');
    expect(substr_count($result, "__("))->toBe(2);
});

test('file with only blade directives is not modified in unexpected ways', function () {
    $original = "@if(true)\n    @foreach(\$items as \$item)\n    @endforeach\n@endif";
    $view     = makeView('_test_wrap/directives.blade.php', $original);

    Artisan::call('translations:wrap', ['view' => $view]);

    // Blade directives contain no text nodes – content should be unchanged
    expect(readView('_test_wrap/directives.blade.php'))->toBe($original);
});

test('all crud action filenames produce parent_action prefix', function () {
    $actions = ['index', 'show', 'store', 'create', 'edit', 'destroy', 'patch', 'put'];

    foreach ($actions as $action) {
        @mkdir(resource_path("views/_test_wrap/posts"), 0755, true);
        $view = makeView("_test_wrap/posts/{$action}.blade.php", "<h1>Title</h1>");

        Artisan::call('translations:wrap', ['view' => $view]);

        expect(readView("_test_wrap/posts/{$action}.blade.php"))
            ->toContain("'posts_{$action}.");
    }
});
