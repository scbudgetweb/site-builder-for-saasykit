<?php

namespace ScBudgetWeb\SiteBuilder\Console;

use Illuminate\Console\Command;

/**
 * Publishes the config, runs the migrations (after asking) and prints the lines the host app needs
 * to add: the Filament plugin and the CSS import and sources.
 */
class InstallCommand extends Command
{
    protected $signature = 'site-builder:install {--force : Overwrite a config file that has already been published}';

    protected $description = 'Install the site builder: publish its config and fonts, run its migrations and show the lines to add to your app';

    public function handle(): int
    {
        $this->components->info('Installing the site builder.');

        $this->callSilently('vendor:publish', ['--tag' => 'site-builder-config', '--force' => (bool) $this->option('force')]);
        $this->components->task('Config published to config/site-builder.php');

        // Always refreshed, so a package update brings its fonts' latest files.
        $this->callSilently('vendor:publish', ['--tag' => 'site-builder-assets', '--force' => true]);
        $this->components->task('Fonts published to public/vendor/site-builder/fonts (commit them with your app)');

        if (! $this->input->isInteractive() || $this->confirm('Run the database migrations now?', true)) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->newLine();
        $this->components->info('Add the plugin to your admin panel provider (app/Providers/Filament/AdminPanelProvider.php):');
        $this->line('    ->plugin(\\ScBudgetWeb\\SiteBuilder\\SiteBuilderPlugin::make())');

        $this->newLine();
        $this->components->info('Add these lines to resources/css/app.css, then run npm run build:');
        foreach (self::cssLines() as $line) {
            $this->line('    '.$line);
        }

        $this->newLine();
        $this->line('Tailwind only includes classes it finds in its sources, and some of the package\'s classes are chosen in PHP, so both @source lines are needed.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    public static function cssLines(): array
    {
        $vendorPath = '../../vendor/scbudgetweb/site-builder-for-saasykit';

        return [
            "@import '{$vendorPath}/resources/css/site-builder.css';",
            "@source '{$vendorPath}/src';",
            "@source '{$vendorPath}/resources/views';",
        ];
    }
}
