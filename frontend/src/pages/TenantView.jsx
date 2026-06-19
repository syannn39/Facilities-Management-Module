import BookingForm from '../components/BookingForm';
import QrScanner from '../components/QrScanner';

/**
 * TenantView — the page a Resident sees after login.
 * This is exactly your original App.jsx content, just moved here so App.jsx
 * can act as the login gate / role router instead.
 *
 * BookingForm and QrScanner are UNCHANGED — they still read the token from
 * localStorage exactly as you wrote them.
 */
export default function TenantView() {
  // facilityId=1 and bookingId=1 match your original hardcoded values.
  // You can make these dynamic later when you add a Browse Facilities page.
  const activeFacilityId = 1;
  const activeBookingId  = 1;

  return (
    <>
      {/* CORE FYP COMPONENT WORKSPACE AREA */}
      <section
        id="next-steps"
        style={{ display: 'flex', flexWrap: 'wrap', gap: '30px', justifyContent: 'center', padding: '20px' }}
      >
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
            <BookingForm facilityId={activeFacilityId} />
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
