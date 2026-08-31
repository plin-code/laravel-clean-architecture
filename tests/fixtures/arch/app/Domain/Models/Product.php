<?php

namespace ArchFixture\Domain\Models;

use ArchFixture\Application\Services\ProductService;
use ArchFixture\Domain\Shared\BaseModel;

/**
 * Violates domain_no_application_imports.
 */
class Product extends BaseModel
{
    public function __construct(private ProductService $service) {}
}
