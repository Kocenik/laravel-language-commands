<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ResetTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:reset {view        : View path after resources/views directory}
                                               {--d|destroy : Remove backup/s after completion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset views to their backup versions. Doesnt work for lang files yet.';

    public function handle()
    {

        $viewPath = $this->argument('view');

        if (!$viewPath) {
            $this->error('Please provide a view path using --view option');
            return 1;
        }

        $basePath = resource_path('views');
        $fullPath = $basePath . '/' . $viewPath;

        if (!File::exists($fullPath)) {
            $this->error("Path not found: {$viewPath}");
            return 1;
        }

        $backups = $this->findBackups($fullPath);

        if (empty($backups)) {
            $this->info('No backups found.');
            return 0;
        }

        $this->displayBackupsTable($backups);

        if (!$this->confirm('Do you want to proceed with replacing these files?', true)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->replaceWithBackups($backups);

        // Delete backups
        if ($this->option('destroy')) {
            foreach ($backups as $backup) {
                File::delete($backup['backup']);
            }
            $this->info('Backup files deleted.');
        }

        $this->newLine();
        $this->info('Views have been reset successfully!');
        return 0;
    }

    private function findBackups($path)
    {
        $backups = [];

        if (File::isDirectory($path)) {
            $files = File::files($path);
            foreach ($files as $file) {
                $filename = $file->getFilename();
                if (str_ends_with($filename, '.backup')) {
                    $original = str_replace('.backup', '', $filename);
                    $originalPath = $path . '/' . $original;
                    if (File::exists($originalPath)) {
                        $backups[] = [
                            'original' => $originalPath,
                            'backup' => $file->getPathname(),
                        ];
                    }
                }
            }
        } else {
            $backupPath = $path . '.backup';
            if (File::exists($backupPath)) {
                $backups[] = [
                    'original' => $path,
                    'backup' => $backupPath,
                ];
            }
        }

        return $backups;
    }

    private function displayBackupsTable($backups)
    {
        $basePath = resource_path('views') . '/';

        $rows = array_map(function($backup) use ($basePath) {
            return [
                'Original' => str_replace($basePath, '', $backup['original']),
                'Backup' => str_replace($basePath, '', $backup['backup']),
            ];
        }, $backups);

        $this->table(['Original', 'Backup'], $rows);
    }

    private function replaceWithBackups($backups)
    {
        foreach ($backups as $backup) {
            File::copy($backup['backup'], $backup['original']);
            $this->line("Replaced: {$backup['original']}");
        }
    }
}
