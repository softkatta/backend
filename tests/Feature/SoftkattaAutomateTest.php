<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftkattaAutomateTest extends TestCase
{
    use RefreshDatabase;

    public function test_automate_command_runs_successfully(): void
    {
        $this->artisan('softkatta:automate --task=all')
            ->expectsOutputToContain('Automation finished.')
            ->assertSuccessful();
    }

    public function test_schedule_registers_softkatta_jobs(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('softkatta:automate --task=subscriptions')
            ->expectsOutputToContain('softkatta:automate --task=cleanup')
            ->assertSuccessful();
    }
}
