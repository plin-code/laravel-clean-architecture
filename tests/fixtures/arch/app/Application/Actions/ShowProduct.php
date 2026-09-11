<?php

namespace ArchFixture\Application\Actions;

use ArchFixture\Infrastructure\Http\ProductController;

/**
 * Violates application_no_infrastructure_imports.
 */
class ShowProduct
{
    public function __construct(private ProductController $controller) {}
}
