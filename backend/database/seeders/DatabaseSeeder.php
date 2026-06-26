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
        // ── Tenant 1: Residential (Apartment / Condo) ───────────────────────
        $this->createTenantWithFacilities(
            tenantName: 'Sunrise Residences',
            tenantType: 'residential',
            residentEmail: 'resident@test.com',
            managerEmail: 'manager@test.com',
            facilities: [
                ['name' => 'Tennis Court',       'description' => 'Sports',      'approval_tier' => 0, 'max_capacity' => 4],
                ['name' => 'Gym',                'description' => 'Fitness',     'approval_tier' => 0, 'max_capacity' => 15],
                ['name' => 'BBQ Pit',            'description' => 'Recreation',  'approval_tier' => 1, 'max_capacity' => 20],
                ['name' => 'Multi-Purpose Hall', 'description' => 'Event Space', 'approval_tier' => 1, 'max_capacity' => 100],
            ],
        );

        // ── Tenant 2: School (Campus) ────────────────────────────────────────
        $this->createTenantWithFacilities(
            tenantName: 'Greenwood International School',
            tenantType: 'school',
            residentEmail: 'student@test.com',
            managerEmail: 'staff@test.com',
            facilities: [
                ['name' => 'Library Discussion Room', 'description' => 'Study / Group Work', 'approval_tier' => 0, 'max_capacity' => 8],
                ['name' => 'Computer Lab',             'description' => 'IT / Coding Class',  'approval_tier' => 0, 'max_capacity' => 30],
                ['name' => 'Sports Field',             'description' => 'Outdoor Sports',     'approval_tier' => 0, 'max_capacity' => 50],
                ['name' => 'School Hall',              'description' => 'Assembly / Events',  'approval_tier' => 1, 'max_capacity' => 300],
                ['name' => 'Science Laboratory',       'description' => 'Practical Class',    'approval_tier' => 1, 'max_capacity' => 25],
            ],
        );
    }

    /**
     * Creates one Tenant, its two test accounts (resident-equivalent + manager),
     * and its facility catalog with matching OperationalRule rows.
     *
     * Kept as a reusable helper so a third industry (e.g. Corporate Office)
     * can be added later with just another method call.
     */
    private function createTenantWithFacilities(
        string $tenantName,
        string $tenantType,
        string $residentEmail,
        string $managerEmail,
        array $facilities,
    ): void {
        $tenant = Tenant::create([
            'name' => $tenantName,
            'type' => $tenantType,
        ]);

        // Resident-equivalent role → sees the Tenant portal (BookingForm + QrScanner)
        // (e.g. a "Resident" in a condo, or a "Student"/parent in a school —
        // the role column stores 'Resident' either way; tenant.type drives the label/theme).
        User::create([
            'name'      => "Test {$tenantType} User",
            'email'     => $residentEmail,
            'password'  => 'password',   // auto-hashed by the 'hashed' cast
            'tenant_id' => $tenant->id,
            'role'      => 'Resident',
        ]);

        // Manager → sees the Admin portal
        User::create([
            'name'      => "Test {$tenantType} Manager",
            'email'     => $managerEmail,
            'password'  => 'password',
            'tenant_id' => $tenant->id,
            'role'      => 'Manager',
        ]);

        foreach ($facilities as $def) {
            $facility = new Facility([
                'name'          => $def['name'],
                'description'   => $def['description'],
                'approval_tier' => $def['approval_tier'],
            ]);
            $facility->tenant_id = $tenant->id;
            $facility->save();

            $rule = new OperationalRule([
                'facility_id'          => $facility->id,
                'max_capacity'         => $def['max_capacity'],
                'approval_tier'        => $def['approval_tier'],
                'grace_period_minutes' => 15,
            ]);
            $rule->tenant_id = $tenant->id;
            $rule->save();
        }
    }
}
