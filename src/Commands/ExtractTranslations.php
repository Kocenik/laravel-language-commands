<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ExtractTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:extract {view      : View path after resources/views directory}
                                                 {--lang=en : Language code (en, bg, etc.)}
                                                 {--w|wrap  : Wrap strings in __() before extraction}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract translation keys from a view and generate language file';

    /**
     * Everything that fails during the process of extraction
     *
     * @var array
     */
    protected array $errors = [];

    protected $added = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //Base directory in which to search
        $viewPath = resource_path('views/' . $this->argument('view'));

        // Exit if file is not found
        if (!file_exists($viewPath)) {
            $this->error("File not found: {$viewPath}");
            return 1;
        }

        // Run wrap if requested
        if ($this->option('wrap')) {
            $this->newLine();
            $this->call('translations:wrap', [
                'view' => $this->argument('view')
            ]);
        }



        $this->comment('Starting Extractor...');

        // Get view contents
        $content = file_get_contents($viewPath);

        // Skip/remove Blade and HTML comments
        $content = preg_replace('/{{--.*?--}}|<!--.*?-->/s', '', $content);

        // Extract all __('...') patterns
        preg_match_all("/__\('([^']+)'\)/", $content, $matches);

        // Return if no formatted keys found
        if (empty($matches[1])) {
            $this->info('No translation keys found.');

            return 0;
        }

        $keys = array_unique($matches[1]);

        $lang = $this->option('lang');
        $langPath = base_path("lang/{$lang}/{file}.php");

        $this->info("Processing {$viewPath}");
        $this->info("Target language: {$lang}");
        $this->info("Target Dir: $langPath");
        $this->newLine();

        foreach ($keys as $key) {
            $this->processKey($key);
        }

        // Display successfully added translations
        if (!empty($this->added)) {
            $this->newLine();
            $this->info('Successfully Added:');
            $this->table(
                ['File', 'Key', 'Value'],
                array_map(fn($a) => [$a['file'], $a['key'], $a['value']], $this->added)
            );
        }

        // Display failed translations
        if (!empty($this->errors)) {
            $this->newLine();
            $this->error('Unresolved Keys:');
            $this->table(['Error'], array_map(fn($e) => [$e], $this->errors));
        }

        // Return
        $this->comment('Extraction Finished!');
        return 0;
    }

    protected function processKey($key)
    {
        // Split file.key (only allow one dot)
        $parts = explode('.', $key);

        if (count($parts) !== 2) {
            $this->errors[] = "Invalid key format (must be file.key): {$key}";
            return;
        }

        [$file, $transKey] = $parts;

        $lang = $this->option('lang');

        // Determine actual file name
        $actualFile = $this->resolveFileName($file);

        $langPath = base_path("lang/{$lang}/{$actualFile}.php");

        // Convert key to formatted English and escape quotes
        $formatted = Str::of($transKey)
                        ->replace('_', ' ')
                        ->title()
                        ->toString();

        $formatted = addslashes($formatted);

        // Ensure directory exists
        if (!file_exists(dirname($langPath))) {
            if (!mkdir(dirname($langPath), 0755, true)) {
                $this->errors[] = "Failed to create directory: " . dirname($langPath);
                return;
            }
        }

        // Create file if doesn't exist
        if (!file_exists($langPath)) {
            if (file_put_contents($langPath, "<?php\n\nreturn [\n];\n") === false) {
                $this->errors[] = "Failed to create file: {$langPath}";
                return;
            }
        }

        // Open file with exclusive lock
        $fp = @fopen($langPath, 'r+');
        if (!$fp) {
            $this->errors[] = "Failed to open file: {$langPath}";
            return;
        }

        if (!flock($fp, LOCK_EX)) {
            $this->errors[] = "Could not lock file: {$langPath}";
            fclose($fp);
            return;
        }

        // Read existing content
        $existing = fread($fp, filesize($langPath));

        // Check if key already exists
        if (str_contains($existing, "'$transKey'")) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }

        // Insert before closing bracket
        $newEntry = "    '$transKey' => '$formatted',\n";
        $updated = str_replace("];\n", $newEntry . "];\n", $existing);

        // Write with lock still held
        ftruncate($fp, 0);
        rewind($fp);
        if (fwrite($fp, $updated) === false) {
            $this->errors[] = "Failed to write to file: {$langPath}";
        } else {
            $this->added[] = [
                'file' => "{$actualFile}.php",
                'key' => $transKey,
                'value' => $formatted
            ];
        }

        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    protected function resolveFileName($file)
    {
        $crudActions = ['index', 'show', 'store', 'create', 'edit', 'destroy', 'patch', 'put'];

        // Check if file ends with _action pattern (e.g., articles_index)
        foreach ($crudActions as $action) {
            if (str_ends_with($file, '_' . $action)) {
                // Keep the full name as the file name
                return $file;
            }
        }

        return $file;
    }
}
