<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class AutoTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:auto {view : View path after resources/views directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically resolves translation of views';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // A clock for debug time data
        $startClock = Carbon::now();

        //Base directory in which to search
        $dir = resource_path('views/' . $this->argument('view'));
        $files = File::allFiles($dir);



        // Table to display found files
        $tableRows = collect();

        foreach ($files as $file) {
            // Prepare each file for table
            $tableRows->push([$file->getRealPath(), $startClock->diffInMilliseconds()]);
        }

        // Debug info from search
        $this->table(['Found Directories:', 'Miliseconds:'], $tableRows);
        $this->info('Total files found: ' . $tableRows->count());
        $this->info('Time Elapsed: ' . $startClock->diffInMilliseconds() . ' Miliseconds');
        $this->newLine();



        // Execute wrap and extraction of translations for English
        $this->info('Starting extraction in English...');
        foreach ($files as $file) {
            $this->call('translations:extract', ['view' => str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getRealPath()),
                                                 '--wrap' => true]);
        }

        // In case of success
        $this->comment('Extracted translations in English.');



        // Extract files for all other user defined languages
        $languages = $this->ask('What other languages do you want to extract? Example: en, bg, etc.');

        foreach ($languages as $language) {
            $this->call('translations:extract', ['view' => str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getRealPath()),
                                                 '--lang' => $language]);

            $this->newLine();
            $this->info('Prepared files for ' . $language);
        }


        // Runtime
        $this->newLine();
        $this->info('Total files found and converted: ' . $count);
        $this->info('Time Elapsed: ' . $startClock->diffInMilliseconds() . ' Miliseconds');
        $this->warn('Done. In case of errors revert back to backups using translations:revert');
    }
}
