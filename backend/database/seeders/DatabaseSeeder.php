<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\OperationalRule;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant 1: Residential (Apartment / Condo) ───────────────────────
        $residentialFacilities = $this->createTenantWithFacilities(
            tenantName: 'Sunrise Residences',
            tenantType: 'residential',
            residentEmail: 'resident@test.com',
            managerEmail: 'manager@test.com',
            facilities: [
                ['name' => 'Tennis Court',       'description' => 'Sports',      'approval_tier' => 0, 'max_capacity' => 4,   'open' => '08:00:00', 'close' => '20:00:00'],
                ['name' => 'Gym',                'description' => 'Fitness',     'approval_tier' => 0, 'max_capacity' => 15,  'open' => '06:00:00', 'close' => '22:00:00'],
                ['name' => 'BBQ Pit',            'description' => 'Recreation',  'approval_tier' => 1, 'max_capacity' => 20,  'open' => '08:00:00', 'close' => '22:00:00'],
                ['name' => 'Multi-Purpose Hall', 'description' => 'Event Space', 'approval_tier' => 1, 'max_capacity' => 100, 'open' => '09:00:00', 'close' => '23:00:00'],
                ['name' => 'Swimming Pool',      'description' => 'Recreation',  'approval_tier' => 0, 'max_capacity' => 30,  'open' => '07:00:00', 'close' => '21:00:00'],
            ],
        );

        // Sample booking history for the residential test account, matching
        // the four states shown on the My Bookings page: Confirmed (x2,
        // upcoming, ready to check in), Completed, and Cancelled (no-show).
        $resident = User::where('email', 'resident@test.com')->first();
        $this->seedSampleBookings($resident, $residentialFacilities);

        // ── Tenant 2: School (Campus) ────────────────────────────────────────
        $this->createTenantWithFacilities(
            tenantName: 'Greenwood International School',
            tenantType: 'school',
            residentEmail: 'student@test.com',
            managerEmail: 'staff@test.com',
            facilities: [
                ['name' => 'Library Discussion Room', 'description' => 'Study / Group Work', 'approval_tier' => 0, 'max_capacity' => 8,   'open' => '08:00:00', 'close' => '18:00:00'],
                ['name' => 'Computer Lab',             'description' => 'IT / Coding Class',  'approval_tier' => 0, 'max_capacity' => 30,  'open' => '08:00:00', 'close' => '18:00:00'],
                ['name' => 'Sports Field',             'description' => 'Outdoor Sports',     'approval_tier' => 0, 'max_capacity' => 50,  'open' => '07:00:00', 'close' => '19:00:00'],
                ['name' => 'School Hall',              'description' => 'Assembly / Events',  'approval_tier' => 1, 'max_capacity' => 300, 'open' => '08:00:00', 'close' => '20:00:00'],
                ['name' => 'Science Laboratory',       'description' => 'Practical Class',    'approval_tier' => 1, 'max_capacity' => 25,  'open' => '08:00:00', 'close' => '17:00:00'],
            ],
        );
    }

    /**
     * Creates one Tenant, its two test accounts (resident-equivalent + manager),
     * and its facility catalog with matching OperationalRule rows (including
     * per-facility operating hours used to build the booking modal's slot list).
     *
     * @return array<string, Facility> facilities keyed by name, for seedSampleBookings()
     */
    private function createTenantWithFacilities(
        string $tenantName,
        string $tenantType,
        string $residentEmail,
        string $managerEmail,
        array $facilities,
    ): array {
        $tenant = Tenant::create([
            'name' => $tenantName,
            'type' => $tenantType,
        ]);

        User::create([
            'name'      => "Test {$tenantType} User",
            'email'     => $residentEmail,
            'password'  => 'password',   // auto-hashed by the 'hashed' cast
            'tenant_id' => $tenant->id,
            'role'      => 'Resident',
        ]);

        User::create([
            'name'      => "Test {$tenantType} Manager",
            'email'     => $managerEmail,
            'password'  => 'password',
            'tenant_id' => $tenant->id,
            'role'      => 'Manager',
        ]);

        $createdFacilities = [];

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
                'open_time'            => $def['open'],
                'close_time'           => $def['close'],
            ]);
            $rule->tenant_id = $tenant->id;
            $rule->save();

            $createdFacilities[$def['name']] = $facility;
        }

        return $createdFacilities;
    }

    /**
     * Seeds a handful of bookings on the residential test account so the
     * "My Bookings" page has something to show on first load — one upcoming
     * confirmed instant booking, one upcoming confirmed booking close to its
     * check-in window, one completed booking, and one auto-cancelled no-show.
     */
    private function seedSampleBookings(User $user, array $facilities): void
    {
        $bookingsData = [
            [
                'facility' => $facilities['Tennis Court'],
                'start'    => Carbon::now()->addDays(2)->setTime(14, 0),
                'end'      => Carbon::now()->addDays(2)->setTime(16, 0),
                'status'   => 'Confirmed',
            ],
            [
                'facility' => $facilities['Gym'],
                'start'    => Carbon::now()->addDays(4)->setTime(8, 0),
                'end'      => Carbon::now()->addDays(4)->setTime(9, 0),
                'status'   => 'Confirmed',
            ],
            [
                'facility' => $facilities['BBQ Pit'],
                'start'    => Carbon::now()->subDays(3)->setTime(18, 0),
                'end'      => Carbon::now()->subDays(3)->setTime(22, 0),
                'status'   => 'Checked_In', // shown as "Completed" once start_time has passed
            ],
            [
                'facility' => $facilities['Swimming Pool'],
                'start'    => Carbon::now()->subDays(6)->setTime(10, 0),
                'end'      => Carbon::now()->subDays(6)->setTime(12, 0),
                'status'   => 'Cancelled_No_Show',
            ],
        ];

        foreach ($bookingsData as $data) {
            $booking = new Booking([
                'user_id'        => $user->id,
                'facility_id'    => $data['facility']->id,
                'start_time'     => $data['start'],
                'end_time'       => $data['end'],
                'status'         => $data['status'],
                'purpose_of_use' => $data['status'] === 'Cancelled_No_Show' ? 'Family swim session' : null,
                'guest_count'    => 0,
            ]);
            $booking->tenant_id = $user->tenant_id;
            $booking->save();
        }
    }
}
