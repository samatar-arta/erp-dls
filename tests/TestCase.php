<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Garde-fou : les tests (RefreshDatabase) ne doivent jamais viser la base de développement.
        $base = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with($base, '_test')) {
            throw new LogicException("Tests refusés : la base '{$base}' n'est pas une base de test.");
        }
    }
}
