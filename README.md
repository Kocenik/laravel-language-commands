# Laravel Language Commands
A collection of Artisan commands for managing translations in Laravel.

![Version](https://img.shields.io/badge/version-0.1.0-orange)
![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013*-red)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)
[![GitHub](https://img.shields.io/badge/GitHub-Kocenik-181717?logo=github)](https://github.com/Kocenik)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## A bit about the project...
This package is for projects that need to add translation support — whether starting fresh
or retrofitting an existing project. Instead of manually wrapping every string and creating
lang files by hand, these commands automate the tedious parts: wrapping strings in `__()`,
extracting translation keys, and generating organized lang files following Laravel conventions.

If you don't yet know how Laravel Translatables work, please first read the
official documentation here:
[Laravel Localization](https://laravel.com/docs/12.x/localization)

Example of current structure:
```bash
    lang/
    ├── bg/
    │   ├── settings.php
    │   └── nav.php
    └── es/
        ├── settings.php
        └── nav.php
```

Future updates will cover even more basics of language conversion like 
extracting into json instead of php and a few others.

Currently, not all methods of translatables are recognised.
```bash
__('key')              // most common, supported
// Unsuported
trans('key')           // alias, older style
@lang('key')           // Blade directive
trans_choice('key', 2) // pluralization
```

I am currently working on a config file for better workflow among different
projects, where a "semantic" (current) or "mirrored" structure of lang files may
be chosen, as well as some other neat options.

## Installation

```bash
composer require kocenik/laravel-language-commands
```

The package will auto-register via Laravel's package discovery.

## Commands

### 1. AutoTranslations
```bash
php artisan translations:auto {view : View path after resources/views directory}
```

The all-in-one command. Accepts a single file or a directory and handles the 
full extraction flow automatically. For Laravel Translatables, we need a base 
language for the project to operate on. As it stands, this is hardcoded to 
English with no immediate plans for adjustment.

After the English Translatables are completed, you will be asked what other language do you want to
extract as translation file/s. It accepts an array, so you can define multiple 
different languages at once. It will then do the same for each one. Note that
these files will still be translated to English or whatever the original text is.
You are just given a prepared directory with file/s which is easy to manage and
translate later on.

After everything completes you will be left with formatted strings in your view. The new
translatables in the view following convention will look something like this:
'Example String' => 'nameOfFile.example_string' 

The Command creates backups of each view before changing it. That is to prevent
loses of data due to errors. If something unexpected happens, go
ahead and use translations:revert to convert back to the old blade views.

### 2. Reset Translations
```bash
php artisan translations:reset {view        : View path after resources/views directory}
                               {--d|destroy : Remove backup/s after completion}
```

Reset views back to their original form using their backups. Note that it looks
for all available backups and converts only those it finds.

> Use `--destroy` to delete the backups after reverting. This is recommended if 
> you plan to run the extraction again, as stale backups can cause conflicts.

### 3. Extract Translations
```bash
php artisan translations:translations:extract {view      : View path after resources/views directory}
                                              {--lang=en : Language code (en, bg, etc.)}
                                              {--w|wrap  : Wrap strings in __() before extraction}
```

Extract formatted strings from the given view/s. By default it will extract them
in lang/en/... but you can change that using --lang. If the given view is not 
prepared the extraction will not find anything, in that case for ease of use you
can use --w which will first convert the views and then the extraction will begin.

> Currently only the `__('key')` syntax is supported. Support for `trans()`, `@lang()`, and `trans_choice()` is planned.

### 4. Wrap Translations
```bash
php artisan translations:wrap {view : View path after resources/views directory}
```
Scans a view for plain text strings and wraps them in `__()` using the `fileName.key` convention. The file name comes from the view's file name, and the key is a snake_case version of the string.

Example — `Save Changes` in `settings.blade.php` becomes `__('settings.save_changes')`.

A backup is created before modifying any file.


## Limitations & Planned Features

- Only `__('key')` is currently detected during extraction. `trans()`, `@lang()`, and `trans_choice()` are not yet supported.
- A config file is in development, which will allow choosing between the current structure and a mirrored structure that matches your view paths.
- JSON translation file output is planned.

## Requirements

- PHP ^8.1
- Laravel ^12

## License

MIT

## Contributing

Pull requests are welcome. For major changes, please open an issue first.

## Author

[Kocenik](https://github.com/Kocenik)
