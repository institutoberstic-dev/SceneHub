<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_react_pages_are_served_by_laravel(): void
    {
        foreach ([
            '/login',
            '/dashboard',
            '/escenarios',
            '/resultados',
            '/versiones',
            '/sincronizacion',
            '/cache-local',
            '/users-list',
            '/roles-list',
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertViewIs('app');
        }
    }

    public function test_csrf_token_can_be_refreshed_for_react_requests(): void
    {
        $this->getJson('/csrf/refresh')
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_read_endpoints_return_json_collections(): void
    {
        foreach ([
            '/api/escenarios',
            '/api/features',
            '/api/simu-solars',
            '/api/users',
            '/api/roles',
        ] as $path) {
            $this->getJson($path)
                ->assertOk()
                ->assertExactJson([]);
        }
    }
}
