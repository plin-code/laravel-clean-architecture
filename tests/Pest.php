<?php

use PlinCode\LaravelCleanArchitecture\Tests\TestCase;

uses(
    TestCase::class,
)->in('Feature', 'Unit');

// Global imports for all tests
uses()->beforeEach(function () {
    if (class_exists('Mockery')) {
        Mockery::globalHelpers();
    }
})->in('Unit');
