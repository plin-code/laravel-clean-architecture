<?php

namespace ArchFixture\Application\Actions;

use ArchFixture\Infrastructure\Mail\ProductMail;

/**
 * Violates application_no_infrastructure_imports, unless Mail is allowed.
 */
class SendProductMail
{
    public function __construct(private ProductMail $mail) {}
}
