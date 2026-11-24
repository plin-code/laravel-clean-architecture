<?php

namespace ArchTestsFixture\Application\Actions\Products;

class PublishProductAction
{
    public function __construct(private string $channel = 'web') {}

    public function execute(): string
    {
        return $this->channel;
    }
}
