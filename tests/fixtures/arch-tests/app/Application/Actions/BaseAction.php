<?php

namespace ArchTestsFixture\Application\Actions;

abstract class BaseAction
{
    public function describe(): string
    {
        return static::class;
    }
}
