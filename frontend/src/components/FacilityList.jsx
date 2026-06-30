import { useEffect, useState } from 'react';
import api from '../api';
import BookingModal from './BookingModal';

/**
 * FacilityList — the "Browse Facilities" page (reference screens 2 & 4).
 *
 * No tenant filtering logic lives here on purpose: GET /api/facilities
 * already returns only the logged-in user's tenant facilities (enforced by
 * the backend's TenantScope), so a School account and a Residential account
 * hitting this exact same component will simply receive different lists.
 *
 * Clicking "Book Now" opens BookingModal for that facility. On success,
 * facilities are re-fetched (capacity/labels could change), a toast-style
 * confirmation message is shown, and onBookingCreated() fires so the parent
 * (TenantView) can tell My Bookings to refetch the next time it's shown.
 */
export default function FacilityList({ onBookingCreated }) {
  const [facilities,      setFacilities]      = useState([]);
  const [loading,         setLoading]         = useState(true);
  const [error,           setError]           = useState('');
  const [activeFacility,  setActiveFacility]  = useState(null); // facility being booked, or null
  const [successMessage,  setSuccessMessage]  = useState('');

  const loadFacilities = () => {
    setLoading(true);
    api.get('/facilities')
      .then(res => setFacilities(res.data.data || []))
      .catch(() => setError('Could not load facilities. Is the backend running?'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { loadFacilities(); }, []);

  const handleBooked = () => {
    setActiveFacility(null);
    setSuccessMessage('Booking confirmed! Check "My Bookings" for details.');
    setTimeout(() => setSuccessMessage(''), 4000);
    onBookingCreated?.();
  };

  if (loading) return <p style={styles.hint}>Loading facilities…</p>;
  if (error) return <p style={{ ...styles.hint, color: '#c00' }}>{error}</p>;
  if (facilities.length === 0) {
    return <p style={styles.hint}>No facilities have been set up for your property yet.</p>;
  }

  return (
    <div>
      {successMessage && <div style={styles.toast}>✓ {successMessage}</div>}

      <div style={styles.grid}>
        {facilities.map((facility) => {
          const requiresApproval = (facility.get_operational_rule?.approval_tier ?? 0) > 0;

          return (
            <div key={facility.facility_id} style={styles.card}>
              <div style={styles.cardTopRow}>
                <div>
                  <div style={styles.cardName}>{facility.name}</div>
                  {facility.category && <div style={styles.cardDesc}>{facility.category}</div>}
                </div>
                <span style={styles.availableBadge}>Available</span>
              </div>

              <div style={styles.cardMeta}>
                {facility.get_operational_rule?.max_capacity && (
                  <span style={styles.metaLine}>
                    👥 Capacity: {facility.get_operational_rule.max_capacity} people
                  </span>
                )}
                {facility.get_operational_rule?.opening_time && facility.get_operational_rule?.closing_time && (
                  <span style={styles.metaLine}>
                    🕐 {facility.get_operational_rule.opening_time.slice(0, 5)} - {facility.get_operational_rule.closing_time.slice(0, 5)}
                  </span>
                )}
                {requiresApproval && <span style={styles.requiresApproval}>Requires Approval</span>}
              </div>

              <button style={styles.bookBtn} onClick={() => setActiveFacility(facility)}>
                Book Now <span style={{ marginLeft: 4 }}>→</span>
              </button>
            </div>
          );
        })}
      </div>

      {activeFacility && (
        <BookingModal
          facility={activeFacility}
          onClose={() => setActiveFacility(null)}
          onBooked={() => { handleBooked(); loadFacilities(); }}
        />
      )}
    </div>
  );
}

const styles = {
  hint: {
    fontSize: 14,
    color: '#888',
    textAlign: 'center',
    padding: '20px 0',
  },
  toast: {
    background: '#e6ffed',
    color: '#1e7e34',
    borderRadius: 8,
    padding: '10px 14px',
    fontSize: 13.5,
    fontWeight: 600,
    marginBottom: 16,
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))',
    gap: 16,
  },
  card: {
    border: '1px solid #e1e4e8',
    borderRadius: 12,
    padding: 18,
    background: '#fff',
    display: 'flex',
    flexDirection: 'column',
  },
  cardTopRow: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 10,
  },
  cardName: {
    fontWeight: 700,
    fontSize: 15.5,
    color: '#1a1a2e',
  },
  cardDesc: {
    fontSize: 12.5,
    color: '#888',
    marginTop: 2,
  },
  availableBadge: {
    fontSize: 11,
    fontWeight: 700,
    color: '#1e7e34',
    background: '#e6ffed',
    borderRadius: 12,
    padding: '3px 10px',
    whiteSpace: 'nowrap',
  },
  cardMeta: {
    display: 'flex',
    flexDirection: 'column',
    gap: 5,
    margin: '14px 0 16px',
  },
  metaLine: {
    fontSize: 12.5,
    color: '#666',
  },
  requiresApproval: {
    fontSize: 11.5,
    fontWeight: 600,
    color: '#946200',
  },
  bookBtn: {
    marginTop: 'auto',
    padding: '10px 0',
    borderRadius: 8,
    border: 'none',
    background: '#1a1a2e',
    color: '#fff',
    fontWeight: 600,
    fontSize: 13.5,
    cursor: 'pointer',
  },
};
