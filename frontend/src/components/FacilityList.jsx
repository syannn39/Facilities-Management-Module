import { useEffect, useState } from 'react';
import api from '../api';

/**
 * FacilityList — the "Browse Facilities" page (report Figure 4.1.1).
 *
 * No tenant filtering logic lives here on purpose: GET /api/facilities
 * already returns only the logged-in user's tenant facilities (enforced by
 * the backend's TenantScope), so a School account and a Residential account
 * hitting this exact same component will simply receive different lists.
 *
 * Selecting a facility reveals the booking form for that facility
 * (replaces the old hardcoded facilityId=1).
 */
export default function FacilityList({ onSelectFacility, selectedFacilityId }) {
  const [facilities, setFacilities] = useState([]);
  const [loading,    setLoading]    = useState(true);
  const [error,      setError]      = useState('');

  useEffect(() => {
    api.get('/facilities')
      .then(res => setFacilities(res.data.data || []))
      .catch(() => setError('Could not load facilities. Is the backend running?'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return <p style={styles.hint}>Loading facilities…</p>;
  }

  if (error) {
    return <p style={{ ...styles.hint, color: '#c00' }}>{error}</p>;
  }

  if (facilities.length === 0) {
    return <p style={styles.hint}>No facilities have been set up for your property yet.</p>;
  }

  return (
    <div style={styles.grid}>
      {facilities.map((facility) => {
        const isSelected      = facility.id === selectedFacilityId;
        const requiresApproval = facility.approval_tier > 0;

        return (
          <button
            key={facility.id}
            onClick={() => onSelectFacility(facility.id)}
            style={{
              ...styles.card,
              ...(isSelected ? styles.cardSelected : {}),
            }}
          >
            <div style={styles.cardName}>{facility.name}</div>
            {facility.description && (
              <div style={styles.cardDesc}>{facility.description}</div>
            )}
            <div style={styles.cardMeta}>
              {requiresApproval ? (
                <span style={styles.badgePending}>Requires Approval</span>
              ) : (
                <span style={styles.badgeInstant}>Instant Booking</span>
              )}
              {facility.operational_rule?.max_capacity && (
                <span style={styles.capacity}>
                  Up to {facility.operational_rule.max_capacity} pax
                </span>
              )}
            </div>
          </button>
        );
      })}
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
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
    gap: 12,
  },
  card: {
    textAlign: 'left',
    padding: '14px 16px',
    borderRadius: 10,
    border: '1.5px solid #e1e4e8',
    background: '#fafbfc',
    cursor: 'pointer',
    transition: 'border-color 0.15s, box-shadow 0.15s',
    fontFamily: 'inherit',
  },
  cardSelected: {
    border: '1.5px solid #0066cc',
    boxShadow: '0 0 0 3px rgba(0,102,204,0.12)',
    background: '#fff',
  },
  cardName: {
    fontWeight: 700,
    fontSize: 15,
    color: '#1a1a2e',
    marginBottom: 4,
  },
  cardDesc: {
    fontSize: 12.5,
    color: '#888',
    marginBottom: 10,
  },
  cardMeta: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: 6,
  },
  badgeInstant: {
    fontSize: 11,
    fontWeight: 600,
    color: '#1e7e34',
    background: '#e6ffed',
    borderRadius: 12,
    padding: '2px 8px',
  },
  badgePending: {
    fontSize: 11,
    fontWeight: 600,
    color: '#946200',
    background: '#fff7e6',
    borderRadius: 12,
    padding: '2px 8px',
  },
  capacity: {
    fontSize: 11,
    color: '#999',
    padding: '2px 0',
  },
};
