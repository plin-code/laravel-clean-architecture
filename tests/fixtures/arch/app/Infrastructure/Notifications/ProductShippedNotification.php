<?php

namespace ArchFixture\Infrastructure\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Clean: a queued notification implements ShouldQueue but is not a job.
 */
class ProductShippedNotification extends Notification implements ShouldQueue {}
