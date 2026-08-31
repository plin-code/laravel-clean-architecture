<?php

namespace ArchFixture\Infrastructure\Console;

/**
 * Violates no_commands_in_infrastructure through the chain, two levels up.
 * The removed clean-arch:validate could not see this one.
 */
class SyncProductsCommand extends BaseCommand {}
