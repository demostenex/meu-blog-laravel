<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O .env local tem APP_ENV=local, o que impede o Livewire de registrar
        // os macros de teste (assertSeeLivewire/assertSeeVolt).
        // Forçamos o env para 'testing' no container e registramos os macros.
        $this->app['env'] = 'testing';

        if (! \Illuminate\Testing\TestResponse::hasMacro('assertSeeLivewire')) {
            \Livewire\Features\SupportTesting\SupportTesting::provide();
        }
    }
}
