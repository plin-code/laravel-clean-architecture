<?php

namespace ArchTestsFixture\Application\Actions\Products;

final readonly class ProductData
{
    public function __construct(public string $name) {}

    public function name(): string
    {
        return $this->name;
    }
}
