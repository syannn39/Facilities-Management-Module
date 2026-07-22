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
        \App\Models\WorkflowTier::insert([
        ['name' => 'Manager', 'level' => 1], // Adjust column names to match schema
        ]);

        // ── Tenant 1: Residential (Apartment / Condo) ───────────────────────
        $residentialFacilities = $this->createTenantWithFacilities(
            tenantName: 'Sunrise Residences',
            tenantType: 'residential',
            contactEmail: 'admin@sunrise-residences.test',
            address: '12 Sunrise Boulevard, Petaling Jaya',
            residentEmail: 'resident@test.com',
            managerEmail: 'manager@test.com',
            facilities: [
                ['name' => 'Tennis Court',       'category' => 'Sports',      'approval_tier' => 0, 'max_capacity' => 4,   'open' => '08:00:00', 'close' => '20:00:00'],
                ['name' => 'Gym',                'category' => 'Fitness',     'approval_tier' => 0, 'max_capacity' => 15,  'open' => '06:00:00', 'close' => '22:00:00'],
                ['name' => 'BBQ Pit',            'category' => 'Recreation',  'approval_tier' => 1, 'max_capacity' => 20,  'open' => '08:00:00', 'close' => '22:00:00'],
                ['name' => 'Multi-Purpose Hall', 'category' => 'Event Space', 'approval_tier' => 1, 'max_capacity' => 100, 'open' => '09:00:00', 'close' => '23:00:00'],
                ['name' => 'Swimming Pool',      'category' => 'Recreation',  'approval_tier' => 0, 'max_capacity' => 30,  'open' => '07:00:00', 'close' => '21:00:00'],
            ],
        );

        // Sample booking history for the residential test account, covering
        // every status the My Bookings page needs to render:
        // Confirmed (x2, upcoming), Completed (Checked_In, past), Cancelled
        // (no-show), Pending (awaiting manager approval), and Rejected.
        $resident = User::where('email', 'resident@test.com')->first();
        $this->seedSampleBookings($resident, $residentialFacilities);

        // ── Block the Gym for Maintenance ────────────────────────────────────
        Availability::create([
            'facility_id' => $residentialFacilities['Gym']->facility_id,
            'date'        => Carbon::now()->addDays(3)->toDateString(),
            'start_time'  => '08:00:00',
            'end_time'    => '12:00:00',
            'is_blocked'  => true,
        ]);

        // ── Tenant 2: School (Campus) ────────────────────────────────────────
        $this->createTenantWithFacilities(
            tenantName: 'Greenwood International School',
            tenantType: 'school',
            contactEmail: 'admin@greenwood.test',
            address: '88 Greenwood Avenue, Subang Jaya',
            residentEmail: 'student@test.com',
            managerEmail: 'staff@test.com',
            facilities: [
                ['name' => 'Library Discussion Room', 'category' => 'Study / Group Work', 'approval_tier' => 0, 'max_capacity' => 8,   'open' => '08:00:00', 'close' => '18:00:00'],
                ['name' => 'Computer Lab',             'category' => 'IT / Coding Class',  'approval_tier' => 0, 'max_capacity' => 30,  'open' => '08:00:00', 'close' => '18:00:00'],
                ['name' => 'Sports Field',             'category' => 'Outdoor Sports',     'approval_tier' => 0, 'max_capacity' => 50,  'open' => '07:00:00', 'close' => '19:00:00'],
                ['name' => 'School Hall',              'category' => 'Assembly / Events',  'approval_tier' => 1, 'max_capacity' => 300, 'open' => '08:00:00', 'close' => '20:00:00'],
                ['name' => 'Science Laboratory',       'category' => 'Practical Class',    'approval_tier' => 1, 'max_capacity' => 25,  'open' => '08:00:00', 'close' => '17:00:00'],
            ],
        );
    }

    /**
     * Creates one Tenant, its two test accounts (resident-equivalent +
     * manager), and its facility catalog with matching OperationalRule rows.
     *
     * @return array<string, Facility> facilities keyed by name, for seedSampleBookings()
     */
    private function createTenantWithFacilities(
        string $tenantName,
        string $tenantType,
        string $contactEmail,
        string $address,
        string $residentEmail,
        string $managerEmail,
        array $facilities,
    ): array {
        $tenant = Tenant::create([
            'tenant_name'   => $tenantName,
            'contact_email' => $contactEmail,
            'address'       => $address,
            'type'          => $tenantType,
        ]);

        User::create([
            'name'      => "Test {$tenantType} User",
            'email'     => $residentEmail,
            'password'  => 'password',   // auto-hashed by the 'hashed' cast
            'tenant_id' => $tenant->tenant_id,
            'role'      => 'Resident',
            'phone_number' => '+60123456789',
        ]);

        User::create([
            'name'      => "Test {$tenantType} Manager",
            'email'     => $managerEmail,
            'password'  => 'password',
            'tenant_id' => $tenant->tenant_id,
            'role'      => 'Manager',
            'phone_number' => '+60198765432',
        ]);

        $createdFacilities = [];

        foreach ($facilities as $def) {
            $facility = new Facility([
                'name'     => $def['name'],
                'category' => $def['category'],
                'status'   => 'active',
            ]);
            $facility->tenant_id = $tenant->tenant_id;
            $facility->save();

            OperationalRule::create([
                'facility_id'            => $facility->facility_id,
                'max_capacity'           => $def['max_capacity'],
                'approval_tier'          => $def['approval_tier'],
                'opening_time'           => $def['open'],
                'closing_time'           => $def['close'],
                'advance_booking_limit'  => 30,
                'grace_period_minutes'   => 15, // not in ERD — see migration note
            ]);

            $createdFacilities[$def['name']] = $facility;
        }

        return $createdFacilities;
    }

    /**
     * Seeds a handful of booking requests on the residential test account
     * so the "My Bookings" page has something to show on first load,
     * covering every status it needs to render.
     */
    private function seedSampleBookings(User $user, array $facilities): void
    {
        // ── Confirmed, upcoming (instant booking facility) ──────────────────
        $this->makeInstantBooking(
            $user, $facilities['Tennis Court'],
            Carbon::now()->addDays(2)->setTime(14, 0),
            Carbon::now()->addDays(2)->setTime(16, 0),
        );

        $this->makeInstantBooking(
            $user, $facilities['Gym'],
            Carbon::now()->addDays(4)->setTime(8, 0),
            Carbon::now()->addDays(4)->setTime(9, 0),
        );

        // ── Completed (already checked in, in the past) ─────────────────────
        $this->makeInstantBooking(
            $user, $facilities['Swimming Pool'],
            Carbon::now()->subDays(5)->setTime(10, 0),
            Carbon::now()->subDays(5)->setTime(12, 0),
            checkedIn: true,
        );

        // ── Cancelled — auto no-show (past, never checked in) ────────────────
        $this->makeInstantBooking(
            $user, $facilities['Tennis Court'],
            Carbon::now()->subDays(6)->setTime(16, 0),
            Carbon::now()->subDays(6)->setTime(18, 0),
            noShow: true,
        );

        // ── Pending — awaiting manager approval (approval_tier > 0 facility) ─
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

        // ── Rejected — manager declined the request ──────────────────────────
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

    /**
     * Creates a BookingRequest + its Booking + Schedule row in one go,
     * mirroring what SchedulingService::validateAndCreateBooking() /
     * confirmBookingFromRequest() do for a real instant booking — kept as
     * a separate helper here rather than calling the service directly,
     * since the service assumes an authenticated request context
     * (Auth::user() for BelongsToTenant) that doesn't exist while seeding.
     */
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
