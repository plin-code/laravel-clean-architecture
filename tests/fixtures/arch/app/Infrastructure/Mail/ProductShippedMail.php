<?php

namespace ArchFixture\Infrastructure\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/**
 * Clean: a queued mailable implements ShouldQueue but is not a job.
 */
class ProductShippedMail extends Mailable implements ShouldQueue {}
