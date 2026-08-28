<?php

namespace PlinCode\LaravelCleanArchitecture\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use PlinCode\LaravelCleanArchitecture\Concerns\RendersStubs;

class MakeNotificationCommand extends Command
{
    use RendersStubs;

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
        $content = $this->replaceDomainPlaceholders($stub, $name);

        $notificationPath = app_path('Infrastructure/Notifications');

        if (! $this->files->isDirectory($notificationPath)) {
            $this->files->makeDirectory($notificationPath, 0755, true);
        }

        $this->files->put("{$notificationPath}/{$name}Notification.php", $content);
        $this->info("Created: Infrastructure/Notifications/{$name}Notification.php");
    }
}
