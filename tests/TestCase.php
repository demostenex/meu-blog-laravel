<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // O .env local tem APP_ENV=local, o que impede o Livewire de registrar
        // os macros de teste (assertSeeLivewire/assertSeeVolt).
        $this->app['env'] = 'testing';

        if (! \Illuminate\Testing\TestResponse::hasMacro('assertSeeLivewire')) {
            \Livewire\Features\SupportTesting\SupportTesting::provide();
        }

        // Aplica apenas migrações pendentes (nunca derruba tabelas).
        // Cada teste é envolvido em transação via DatabaseTransactions
        // e revertido ao final — dados de desenvolvimento são preservados.
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate');
            RefreshDatabaseState::$migrated = true;
        }
    }
}


