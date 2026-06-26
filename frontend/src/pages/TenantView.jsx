import { useState } from 'react';
import BookingForm from '../components/BookingForm';
import QrScanner from '../components/QrScanner';
import FacilityList from '../components/FacilityList';

/**
 * TenantView — the page a Resident (or Student, for a School tenant) sees
 * after login.
 *
 * Browse Facilities (FacilityList) now drives which facility BookingForm
 * targets — previously this was hardcoded to facilityId=1. Because
 * GET /api/facilities is automatically scoped to the logged-in user's
 * tenant on the backend, a School account and a Residential account land
 * on this exact same component but see two different facility catalogs.
 */
export default function TenantView() {
  const [activeFacilityId, setActiveFacilityId] = useState(null);
  // bookingId=1 matches the original hardcoded value — wiring this up to
  // the booking actually created by BookingForm is a separate follow-up
  // (would need BookingForm to report back the new booking's id).
  const activeBookingId = 1;

  return (
    <>
      {/* CORE FYP COMPONENT WORKSPACE AREA */}
      <section
        id="next-steps"
        style={{ display: 'flex', flexWrap: 'wrap', gap: '30px', justifyContent: 'center', padding: '20px' }}
      >
        {/* Block 0: Browse Facilities — tenant-scoped catalog */}
        <div
          id="browse-facilities"
          style={{ flex: '1 1 100%', maxWidth: '1000px', background: '#fff', padding: '20px', borderRadius: '8px' }}
        >
          <h2 style={{ borderBottom: '2px solid #6f42c1', paddingBottom: '8px', color: '#2c3e50' }}>
            Browse Facilities
          </h2>
          <p style={{ fontSize: '14px', color: '#7f8c8d', marginBottom: '16px' }}>
            Showing only the facilities available at your property. Select one to start a booking.
          </p>
          <FacilityList
            onSelectFacility={setActiveFacilityId}
            selectedFacilityId={activeFacilityId}
          />
        </div>

        {/* Block 1: Real-Time Dynamic Scheduler Submissions (FR1 / FR3) */}
        <div
          id="docs"
          style={{ flex: '1', minWidth: '320px', maxWidth: '480px', background: '#fff', padding: '20px', borderRadius: '8px' }}
        >
          <h2 style={{ borderBottom: '2px solid #0066cc', paddingBottom: '8px', color: '#2c3e50' }}>
            New Reservation Request
          </h2>
          <p style={{ fontSize: '14px', color: '#7f8c8d' }}>
            Algorithm 2 handles cross-tenant conflict detection automatically upon clicking submission.
          </p>
          <div style={{ marginTop: '20px' }}>
            {activeFacilityId ? (
              <BookingForm facilityId={activeFacilityId} />
            ) : (
              <p style={{ fontSize: '13px', color: '#aaa' }}>
                ↑ Pick a facility above first.
              </p>
            )}
          </div>
        </div>

        {/* Block 2: Physical Location Verification Entry (FR5) */}
        <div
          id="social"
          style={{ flex: '1', minWidth: '320px', maxWidth: '480px', background: '#fff', padding: '20px', borderRadius: '8px' }}
        >
          <h2 style={{ borderBottom: '2px solid #bd2130', paddingBottom: '8px', color: '#2c3e50' }}>
            Physical Gate Verification
          </h2>
          <p style={{ fontSize: '14px', color: '#7f8c8d' }}>
            Algorithm 3 enforces camera access permissions to capture tokens within a 15-minute arrival window.
          </p>
          <div style={{ marginTop: '20px' }}>
            <QrScanner bookingId={activeBookingId} />
          </div>
        </div>
      </section>

      <div className="ticks"></div>
      <section id="spacer" style={{ height: '100px' }}></section>
    </>
  );
}
