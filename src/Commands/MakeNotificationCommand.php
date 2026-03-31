<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeNotificationCommand extends Command
{
    protected $signature = 'clean-arch:make-notification {name : The name of the notification}
                          {--force : Overwrite existing files}';

    protected $description = 'Create a new notification in the Infrastructure layer';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $this->info("Creating notification: {$name}");
        $this->createNotification($name);
        $this->info("Notification {$name} created successfully!");

        return self::SUCCESS;
    }

    protected function createNotification(string $name): void
    {
        $stub    = $this->getStub('notification');
        $content = $this->replacePlaceholders($stub, $name);

        $notificationPath = app_path('Infrastructure/Notifications');

        if (! $this->files->isDirectory($notificationPath)) {
            $this->files->makeDirectory($notificationPath, 0755, true);
        }

        $this->files->put("{$notificationPath}/{$name}Notification.php", $content);
        $this->info("Created: Infrastructure/Notifications/{$name}Notification.php");
    }

    protected function replacePlaceholders(string $content, string $name): string
    {
        $pluralName     = Str::plural($name);
        $domainVariable = Str::camel($name);

        return str_replace(
            ['{{DomainName}}', '{{PluralDomainName}}', '{{domainVariable}}'],
            [$name, $pluralName, $domainVariable],
            $content
        );
    }

    protected function getStub(string $stub): string
    {
        $stubPath = __DIR__ . "/../../stubs/{$stub}.stub";

        if (! $this->files->exists($stubPath)) {
            throw new \Exception("Stub file not found: {$stubPath}");
        }

        return $this->files->get($stubPath);
    }
}
