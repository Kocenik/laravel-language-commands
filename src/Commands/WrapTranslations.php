<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WrapTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:wrap {view : View path after resources/views directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wrap visible strings in __() for translation';

    protected $crudActions = ['index', 'show', 'store', 'create', 'edit', 'destroy', 'patch', 'put'];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->comment("Starting Wrapper...");

        //Base path in which to search
        $viewPath = resource_path('views/' . $this->argument('view'));

        if (!file_exists($viewPath)) {
            $this->error("File not found: {$viewPath}");
            return 1;
        }



        // Create backup
        $backupPath = $viewPath . '.backup';
        copy($viewPath, $backupPath);
        $this->info("Backup created: {$backupPath}");



        $content = file_get_contents($viewPath);
        $prefix = $this->getTranslationPrefix();
        $result = $this->wrapStrings($content, $prefix);
        $wrapped = $result['content'];
        $changes = $result['changes'];

        file_put_contents($viewPath, $wrapped);

        // Display table with original and changed strings
        if (!empty($changes)) {
            $this->newLine();
            $this->table(
                ['Original String', 'Translation Key'],
                array_map(fn($c) => [$c['original'], $c['key']], $changes)
            );
            $this->info("Total strings wrapped: " . count($changes));
        } else {
            $this->newLine();
            $this->warn('No strings were wrapped.');
        }

        $this->newLine();
        $this->comment('Wrapping done!');
        $this->newLine();
        return 0;
    }

    protected function getTranslationPrefix()
    {
        $viewArg = $this->argument('view');

        // Remove .blade.php extension
        $viewArg = preg_replace('/\.blade\.php$/', '', $viewArg);

        $parts = explode('/', $viewArg);
        $filename = array_pop($parts);

        // Check if filename is a CRUD action
        if (in_array($filename, $this->crudActions) && !empty($parts)) {
            // Use parent_folder_action format
            $parentFolder = array_pop($parts);
            return $parentFolder . '_' . $filename;
        }

        // Use just the filename
        return $filename;
    }

    protected function wrapStrings($content, $prefix)
    {
        $changes = [];

        // Skip Blade and HTML comments
        $content = preg_replace_callback(
            '/(?<=>)([^<>{}\s][^<>{}]*[^<>{}\s])(?=<)/',
            function($matches) use ($prefix, &$changes) {
                $text = $matches[1];

                // Skip comments
                if (preg_match('/{{--.*?--}}|<!--.*?-->/s', $text)) {
                    return $text;
                }

                // Skip if already wrapped
                if (preg_match('/__\(|{{\s*__\(/', $text)) {
                    return $text;
                }

                $trimmed = trim($text);
                if (empty($trimmed) || is_numeric($trimmed)) {
                    return $text;
                }

                $key = $this->generateKey($trimmed);
                $wrapped = "{{ __('{$prefix}.{$key}') }}";

                $changes[] = [
                    'original' => $trimmed,
                    'key' => "{$prefix}.{$key}",
                ];

                return $wrapped;
            },
            $content
        );

        // Same for attributes...

        return ['content' => $content, 'changes' => $changes];
    }

    protected function generateKey($text)
    {
        // Preserve the original text as-is for the value
        // Only simplify for the key
        return Str::of($text)
                  ->lower()
                  ->replaceMatches('/[^\w\s]/', '') // Remove punctuation but keep words
                  ->replace(' ', '_')
                  ->replaceMatches('/_+/', '_')
                  ->trim('_')
                  ->toString();
    }
}
