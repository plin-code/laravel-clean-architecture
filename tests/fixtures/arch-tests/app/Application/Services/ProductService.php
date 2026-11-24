<?php

namespace ArchTestsFixture\Application\Services;

use ArchTestsFixture\Domain\Products\Product;
use ArchTestsFixture\Infrastructure\Http\Requests\StoreProductRequest;
use ArchTestsFixture\Infrastructure\Mail\ProductPublishedMail;

class ProductService
{
    public function store(StoreProductRequest $request, ProductPublishedMail $mail): Product
    {
        $build = function (): Product {
            return new Product;
        };

        return $build();
    }
}
