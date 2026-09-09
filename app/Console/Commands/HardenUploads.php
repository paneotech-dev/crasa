<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Install the rules that stop the uploads directory executing code.
 *
 * WHY THIS IS A COMMAND AND NOT JUST A FILE. The file has to live inside
 * storage/app/public, and that directory is deliberately excluded from the
 * repository because it holds user content. A protection stored only there
 * disappears the first time the platform is deployed to a fresh directory,
 * and nobody notices until something is uploaded that should not have been.
 *
 * So the canonical copy is in the repository, this command installs it, and
 * a test compares the two. Run it after every deployment; it is safe to run
 * repeatedly.
 *
 * The host this platform runs on had two web shells dropped on a sibling
 * account in September 2026. Before this ran, a .php file placed in the
 * uploads directory executed.
 */
class HardenUploads extends Command
{
    protected $signature = 'security:harden-uploads {--check : Report whether the rules are in place without writing}';

    protected $description = 'Install the rules that stop the uploads directory executing code';

    public const SOURCE = 'security/uploads.htaccess';

    public function handle(): int
    {
        $source = resource_path(self::SOURCE);
        $target = storage_path('app/public/.htaccess');

        if (! File::exists($source)) {
            $this->error('The canonical rules are missing from '.$source.'.');

            return self::FAILURE;
        }

        $expected = File::get($source);

        if ($this->option('check')) {
            if (! File::exists($target)) {
                $this->error('The uploads directory has no .htaccess. A .php file placed there would execute.');

                return self::FAILURE;
            }

            if (File::get($target) !== $expected) {
                $this->error('The uploads .htaccess differs from the canonical copy.');
                $this->line('Run `php artisan security:harden-uploads` to reinstall it.');

                return self::FAILURE;
            }

            $this->info('The uploads directory is protected.');

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($target));
        File::put($target, $expected);

        $this->info('Installed. Nothing in the uploads directory will be executed.');
        $this->line('Verify from outside: place a file called canary.php there and request it. It must not run.');

        return self::SUCCESS;
    }
}
