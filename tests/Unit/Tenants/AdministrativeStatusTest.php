<?php

namespace Tests\Unit\Tenants;

use App\Models\AdministrativeStatus;
use App\Models\Province;
use App\Models\User;
use Config;
use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class AdministrativeStatusTest.
 *
 * @package Tests\Unit
 */
class AdministrativeStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp()
    {
        parent::setUp();
        Config::set('auth.providers.users.model',User::class);
    }

    /**
     * Refresh the in-memory database.
     *
     * @return void
     */
    protected function refreshInMemoryDatabase()
    {
        $this->artisan('migrate',[
            '--path' => 'database/migrations/tenant'
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /** @test */
    public function find_by_name()
    {
        $this->assertNull(AdministrativeStatus::findByName('Funcionari/a amb plaça definitiva'));

        $status = AdministrativeStatus::create([
            'name' => 'Funcionari/a amb plaça definitiva',
            'code' => 'FUNCIONARI DEF'
        ]);

        $this->assertTrue($status->is(AdministrativeStatus::findByName('Funcionari/a amb plaça definitiva')));
    }

}
