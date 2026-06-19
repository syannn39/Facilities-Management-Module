<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\OperationalRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant (property) ─────────────────────────────────────────────
        $tenant = Tenant::create(['name' => 'Sunrise Residences']);

        // ── Test accounts ─────────────────────────────────────────────────
        // Resident  → sees the Tenant portal (BookingForm + QrScanner)
        User::create([
            'name'      => 'Test Resident',
            'email'     => 'resident@test.com',
            'password'  => 'password',   // auto-hashed by the 'hashed' cast
            'tenant_id' => $tenant->id,
            'role'      => 'Resident',
        ]);

        // Manager → sees the Admin portal (blank for now)
        User::create([
            'name'      => 'Test Manager',
            'email'     => 'manager@test.com',
            'password'  => 'password',
            'tenant_id' => $tenant->id,
            'role'      => 'Manager',
        ]);

        // ── Sample facilities ─────────────────────────────────────────────
        $defs = [
            ['name' => 'Tennis Court',      'description' => 'Sports',      'approval_tier' => 0, 'max_capacity' => 4],
            ['name' => 'Gym',               'description' => 'Fitness',     'approval_tier' => 0, 'max_capacity' => 15],
            ['name' => 'BBQ Pit',           'description' => 'Recreation',  'approval_tier' => 1, 'max_capacity' => 20],
            ['name' => 'Multi-Purpose Hall','description' => 'Event Space', 'approval_tier' => 1, 'max_capacity' => 100],
        ];

        foreach ($defs as $def) {
            $facility = new Facility([
                'name'          => $def['name'],
                'description'   => $def['description'],
                'approval_tier' => $def['approval_tier'],
            ]);
            $facility->tenant_id = $tenant->id;
            $facility->save();

            $rule = new OperationalRule([
                'facility_id'            => $facility->id,
                'max_capacity'           => $def['max_capacity'],
                'approval_tier'          => $def['approval_tier'],
                'checkin_window_minutes' => 15,
            ]);
            $rule->tenant_id = $tenant->id;
            $rule->save();
        }
    }
}
