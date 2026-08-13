<?php

namespace Tests\Unit;

use App\Models\Master\Divisions;
use App\Models\User;
use App\Support\GaAccess;
use Tests\TestCase;

class GaAccessTest extends TestCase
{
    public function test_admin_can_access_ga_panel(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertTrue(GaAccess::allowed($user));
    }

    public function test_ga_role_can_access_ga_panel(): void
    {
        $user = new User();
        $user->role = 'ga';

        $this->assertTrue(GaAccess::allowed($user));
    }

    public function test_general_affair_division_can_access_ga_panel(): void
    {
        $user = new User();
        $user->role = 'pelapor';
        $user->setRelation('division', new Divisions(['name' => 'General Affair']));

        $this->assertTrue(GaAccess::allowed($user));
    }

    public function test_regular_user_cannot_access_ga_panel(): void
    {
        $user = new User();
        $user->role = 'pelapor';
        $user->setRelation('division', new Divisions(['name' => 'Information Technology']));

        $this->assertFalse(GaAccess::allowed($user));
    }
}
