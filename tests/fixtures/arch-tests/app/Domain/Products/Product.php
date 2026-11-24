<?php

namespace ArchTestsFixture\Domain\Products;

class Product
{
    // A layer named in a comment or a string is not a dependency:
    // ArchTestsFixture\Application\Services\ProductService.
    public string $handler = 'ArchTestsFixture\Infrastructure\Jobs\SyncProductsJob';
}
