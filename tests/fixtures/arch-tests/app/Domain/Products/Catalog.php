<?php

namespace ArchTestsFixture\Domain\Products;

use ArchTestsFixture\Application\Actions\Products\PublishProductAction as Publish;
use ArchTestsFixture\Infrastructure\Mail\ProductPublishedMail;

class Catalog
{
    public function publish(Publish $publish): string
    {
        $label = "{$publish->execute()}";

        return $label . ProductPublishedMail::class;
    }
}
