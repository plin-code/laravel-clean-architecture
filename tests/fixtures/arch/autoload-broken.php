<?php

/**
 * Same as autoload.php without the fixture classes, which is what a project
 * with a stale or missing composer dump-autoload looks like. The reflection
 * based rules report nothing here, so the guard in the generated config is the
 * only thing standing between that and a green pipeline.
 */

require __DIR__ . '/../../../vendor/autoload.php';
