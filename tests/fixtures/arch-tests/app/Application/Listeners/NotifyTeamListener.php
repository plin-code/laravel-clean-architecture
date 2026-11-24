<?php

namespace ArchTestsFixture\Application\Listeners;

use ArchTestsFixture\Application\Actions\Products\PublishProductAction;

class NotifyTeamListener
{
    public function __construct(private PublishProductAction $publish) {}

    public function handle(object $event): void
    {
        // A comment naming ->save() must not be reported.
        $this->publish->execute();
    }
}
