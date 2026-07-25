<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CheckIn;
use App\Models\Facility;
use App\Models\OperationalRule;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Availability;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant 1: Residential (Sunrise Residences) ───────────────────────
        $residentialFacilities = $this->createTenantWithFacilities(
            tenantName: 'Sunrise Residences',
            tenantType: 'residential',
            contactEmail: 'admin@sunrise-residences.test',
            address: '12 Sunrise Boulevard, Petaling Jaya',
            residentEmails: ['resident@test.com', 'resident2@test.com'], 
            tier1Email: 'manager@test.com', // Property Manager
            tier2Email: 'jmb@test.com',    // JMB Member
            facilities: [
                ['name' => 'Tennis Court',       'category' => 'Sports',      'approval_tier' => 0, 'max_capacity' => 4,   'open' => '08:00:00', 'close' => '20:00:00', 'is_shared' => false, 'limit' => 1],
                ['name' => 'Gym',                'category' => 'Fitness',     'approval_tier' => 0, 'max_capacity' => 15,  'open' => '06:00:00', 'close' => '22:00:00', 'is_shared' => true, 'limit' => 15],
                ['name' => 'BBQ Pit',            'category' => 'Recreation',  'approval_tier' => 1, 'max_capacity' => 20,  'open' => '08:00:00', 'close' => '22:00:00', 'is_shared' => false, 'limit' => 1],
                ['name' => 'Multi-Purpose Hall', 'category' => 'Event Space', 'approval_tier' => 2, 'max_capacity' => 100, 'open' => '09:00:00', 'close' => '23:00:00', 'is_shared' => false, 'limit' => 1],
                ['name' => 'Swimming Pool',      'category' => 'Recreation',  'approval_tier' => 0, 'max_capacity' => 30,  'open' => '07:00:00', 'close' => '21:00:00', 'is_shared' => true, 'limit' => 30],
                ['name' => 'Karaoke Room',       'category' => 'Entertainment',  'approval_tier' => 0, 'max_capacity' => 10,  'open' => '07:00:00', 'close' => '21:00:00', 'is_shared' => false, 'limit' => 1],
            ],
        );
        // ── Tenant 1: Residential (Sunrise Residences) ───────────────────────
        $primaryResident = User::where('email', 'resident@test.com')->first();
        if ($primaryResident) {
            $this->seedSampleBookings($primaryResident, $residentialFacilities);
        }

        // ── Tenant 2: School (Greenwood International School) ────────────────
        $this->createTenantWithFacilities(
            tenantName: 'Greenwood International School',
            tenantType: 'school',
            contactEmail: 'admin@greenwood.test',
            address: '88 Greenwood Avenue, Subang Jaya',
            residentEmails: ['student@test.com'],
            tier1Email: 'lecturer@test.com',
            tier2Email: 'dean@test.com',
            facilities: [
                ['name' => 'Library Discussion Room', 'category' => 'Study / Group Work', 'approval_tier' => 0, 'max_capacity' => 8,   'open' => '08:00:00', 'close' => '18:00:00', 'is_shared' => false, 'limit' => 1],
                ['name' => 'Computer Lab',            'category' => 'IT / Coding Class',  'approval_tier' => 0, 'max_capacity' => 30,  'open' => '08:00:00', 'close' => '18:00:00', 'is_shared' => true, 'limit' => 30],
                ['name' => 'Sports Field',            'category' => 'Outdoor Sports',     'approval_tier' => 0, 'max_capacity' => 50,  'open' => '07:00:00', 'close' => '19:00:00', 'is_shared' => true, 'limit' => 20],
                ['name' => 'School Hall',             'category' => 'Assembly / Events',  'approval_tier' => 2, 'max_capacity' => 300, 'open' => '08:00:00', 'close' => '20:00:00', 'is_shared' => false, 'limit' => 1],
                ['name' => 'Science Laboratory',      'category' => 'Practical Class',    'approval_tier' => 1, 'max_capacity' => 25,  'open' => '08:00:00', 'close' => '17:00:00', 'is_shared' => false, 'limit' => 1],
            ],
        );
    }

    private function createTenantWithFacilities(
        string $tenantName,
        string $tenantType,
        string $contactEmail,
        string $address,
        array $residentEmails,
        string $tier1Email,
        string $tier2Email,
        array $facilities,
    ): array {
        $tenant = Tenant::firstOrCreate(
            ['tenant_name' => $tenantName], 
            [
                'contact_email' => $contactEmail,
                'address'       => $address,
                'type'          => $tenantType,
            ]
        );

        $userRole = $tenantType === 'residential' ? 'Resident' : 'Student';
        $tier1Role = $tenantType === 'residential' ? 'Property Manager' : 'Lecturer';
        $tier2Role = $tenantType === 'residential' ? 'JMB Member' : 'Head of Department';

        // Create multiple users for the same tenant
        foreach ($residentEmails as $index => $email) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name'         => $index === 0 ? "Test {$tenantType} User" : "Test {$tenantType} User " . ($index + 1),
                    'password'     => 'password',
                    'tenant_id'    => $tenant->tenant_id,
                    'role'         => $userRole,
                    'phone_number' => '+6012345678' . $index,
                ]
            );
        }

        User::firstOrCreate(
            ['email' => $tier1Email],
            [
                'name'      => "Test {$tier1Role}",
                'password'  => 'password',
                'tenant_id' => $tenant->tenant_id,
                'role'      => $tier1Role,
                'phone_number' => '+60198765432',
            ]
        );

        User::firstOrCreate(
            ['email' => $tier2Email],
            [
                'name'      => "Test {$tier2Role}",
                'password'  => 'password',
                'tenant_id' => $tenant->tenant_id,
                'role'      => $tier2Role,
                'phone_number' => '+60198765431',
            ]
        );

        $createdFacilities = [];

        foreach ($facilities as $def) {
            $facility = new Facility([
                'name'     => $def['name'],
                'category' => $def['category'],
                'status'   => 'active',
            ]);
            $facility->tenant_id = $tenant->tenant_id;
            $facility->save();

            $rule = OperationalRule::create([
                'facility_id'            => $facility->facility_id,
                'max_capacity'           => $def['max_capacity'],
                'approval_tier'          => $def['approval_tier'],
                'opening_time'           => $def['open'],
                'closing_time'           => $def['close'],
                'advance_booking_limit'  => 30,
                'grace_period_minutes'   => 15,
                'is_shared_facility'     => $def['is_shared'],
                'concurrent_booking_limit' => $def['limit']
            ]);

            // Dynamically assign Workflow Tiers based on the facility's requirement
            if ($def['approval_tier'] >= 1) {
                \App\Models\WorkflowTier::create([
                    'rule_id'       => $rule->rule_id,
                    'tier_level'    => 1,
                    'assigned_role' => $tier1Role
                ]);
            }
            if ($def['approval_tier'] >= 2) {
                \App\Models\WorkflowTier::create([
                    'rule_id'       => $rule->rule_id,
                    'tier_level'    => 2,
                    'assigned_role' => $tier2Role
                ]);
            }

            $createdFacilities[$def['name']] = $facility;
        }

        return $createdFacilities;
    }

    private function seedSampleBookings(User $user, array $facilities): void
    {
        // Check if the user already has booking requests to avoid duplicate seeding
        if (BookingRequest::where('user_id', $user->id)->exists()) {
            return;
        }

        $this->makeInstantBooking(
            $user,
            $facilities['Tennis Court'],
            Carbon::now()->addDays(2)->setTime(14, 0),
            Carbon::now()->addDays(2)->setTime(16, 0),
        );

        $this->makeInstantBooking(
            $user,
            $facilities['Gym'],
            Carbon::now()->addDays(4)->setTime(8, 0),
            Carbon::now()->addDays(4)->setTime(9, 0),
        );

        $this->makeInstantBooking(
            $user,
            $facilities['Swimming Pool'],
            Carbon::now()->subDays(5)->setTime(10, 0),
            Carbon::now()->subDays(5)->setTime(12, 0),
            checkedIn: true,
        );

        $this->makeInstantBooking(
            $user,
            $facilities['Tennis Court'],
            Carbon::now()->subDays(6)->setTime(16, 0),
            Carbon::now()->subDays(6)->setTime(18, 0),
            noShow: true,
        );

        // Tier 1 Pending Request
        BookingRequest::create([
            'tenant_id'      => $user->tenant_id,
            'facility_id'    => $facilities['BBQ Pit']->facility_id,
            'user_id'        => $user->id,
            'booking_date'   => Carbon::now()->addDays(3)->toDateString(),
            'start_time'     => Carbon::now()->addDays(3)->setTime(18, 0),
            'end_time'       => Carbon::now()->addDays(3)->setTime(22, 0),
            'status'         => 'Pending',
            'purpose_of_use' => 'Family birthday gathering',
            'guest_count'    => 15,
        ]);

        // Tier 2 Pending Request
        BookingRequest::create([
            'tenant_id'      => $user->tenant_id,
            'facility_id'    => $facilities['Multi-Purpose Hall']->facility_id,
            'user_id'        => $user->id,
            'booking_date'   => Carbon::now()->addDays(5)->toDateString(),
            'start_time'     => Carbon::now()->addDays(5)->setTime(19, 0),
            'end_time'       => Carbon::now()->addDays(5)->setTime(23, 0),
            'status'         => 'Pending',
            'purpose_of_use' => 'Large scale event',
            'guest_count'    => 80,
        ]);

        BookingRequest::create([
            'tenant_id'      => $user->tenant_id,
            'facility_id'    => $facilities['Multi-Purpose Hall']->facility_id,
            'user_id'        => $user->id,
            'booking_date'   => Carbon::now()->subDays(2)->toDateString(),
            'start_time'     => Carbon::now()->subDays(2)->setTime(19, 0),
            'end_time'       => Carbon::now()->subDays(2)->setTime(23, 0),
            'status'         => 'Rejected',
            'purpose_of_use' => 'Private function',
            'guest_count'    => 80,
        ]);
    }

    private function makeInstantBooking(
        User $user,
        Facility $facility,
        Carbon $start,
        Carbon $end,
        bool $checkedIn = false,
        bool $noShow = false,
    ): void {
        $request = new BookingRequest([
            'facility_id'  => $facility->facility_id,
            'user_id'      => $user->id,
            'booking_date' => $start->toDateString(),
            'start_time'   => $start,
            'end_time'     => $end,
            'status'       => 'Approved',
            'guest_count'  => 0,
        ]);
        $request->tenant_id = $user->tenant_id;
        $request->save();

        $bookingStatus = $noShow ? 'Cancelled_No_Show' : ($checkedIn ? 'Checked_In' : 'Confirmed');

        $booking = new Booking([
            'request_id'   => $request->request_id,
            'facility_id'  => $facility->facility_id,
            'user_id'      => $user->id,
            'booking_type' => 'Instant',
            'booking_date' => $start->toDateString(),
            'start_time'   => $start,
            'end_time'     => $end,
            'status'       => $bookingStatus,
        ]);
        $booking->tenant_id = $user->tenant_id;
        $booking->save();

        $request->update(['booking_id' => $booking->booking_id]);

        Schedule::create([
            'facility_id'  => $facility->facility_id,
            'tenant_id'    => $user->tenant_id,
            'booking_id'   => $booking->booking_id,
            'slot_date'    => $start->toDateString(),
            'start_time'   => $start->format('H:i:s'),
            'end_time'     => $end->format('H:i:s'),
            'is_available' => false,
        ]);

        if ($checkedIn) {
            CheckIn::create([
                'booking_id'   => $booking->booking_id,
                'user_id'      => $user->id,
                'checkin_time' => $start->copy()->addMinutes(5),
                'method'       => 'QR',
                'status'       => 'Success',
            ]);
        }
    }
}
