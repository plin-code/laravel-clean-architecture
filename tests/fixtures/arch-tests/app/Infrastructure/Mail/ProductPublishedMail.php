<?php

namespace ArchTestsFixture\Infrastructure\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class ProductPublishedMail extends Mailable implements ShouldQueue {}
