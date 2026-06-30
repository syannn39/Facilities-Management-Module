import { useEffect, useState } from 'react';
import api from '../api';
import QrScanner from './QrScanner';

/**
 * MyBookings — the "My Bookings" page (reference screen 6).
 *
 * GET /bookings now returns BookingRequest rows (not Booking rows) — under
 * the ERD's two-table design, a Pending or Rejected request never gets a
 * Booking row at all, so querying from BookingRequest is the only way to
 * show those states here. Each request optionally carries a nested
 * `get_booking` object (BookingRequest::getBooking() per the UML naming —
 * present once approved/instant-confirmed) which itself optionally
 * carries a nested `get_check_in` (present once scanned —
 * Booking::getCheckIn() per the UML Class Diagram naming).
 *
 * Status comes from combining two levels:
 *   request.status === 'Pending'   → "Pending Approval" (amber)
 *   request.status === 'Rejected'  → "Rejected" (red)
 *   request.status === 'Approved' and...
 *     request.get_booking.status === 'Confirmed'         → "Confirmed" (blue) + Scan QR button
 *     request.get_booking.status === 'Checked_In'        → "Completed" (grey)
 *     request.get_booking.status === 'Cancelled_No_Show' → "Cancelled" (red) + auto-cancellation note
 *
 * Only one QrScanner is ever mounted at a time (inside a small modal,
 * opened per-booking) since the scanner library binds to a single
 * fixed-id DOM container — mounting several at once on the list itself
 * would collide.
 */
export default function MyBookings() {
  const [requests, setRequests] = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [error,    setError]    = useState('');
  const [scanningBookingId, setScanningBookingId] = useState(null);

  const loadRequests = () => {
    setLoading(true);
    api.get('/bookings')
      .then(res => setRequests(res.data.data || []))
      .catch(() => setError('Could not load your bookings.'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { loadRequests(); }, []);

  if (loading) return <p style={styles.hint}>Loading your bookings…</p>;
  if (error) return <p style={{ ...styles.hint, color: '#c00' }}>{error}</p>;
  if (requests.length === 0) {
    return <p style={styles.hint}>You haven't made any bookings yet. Browse Facilities to get started.</p>;
  }

  return (
    <div>
      <div style={styles.list}>
        {requests.map((req) => {
          const info = describeRequest(req);

          return (
            <div key={req.request_id} style={styles.card}>
              <div style={styles.cardLeft}>
                <div style={styles.facilityName}>{req.facility?.name || 'Facility'}</div>
                <div style={styles.timeRow}>
                  🕐 {formatDate(req.start_time)} · {formatTime(req.start_time)} - {formatTime(req.end_time)}
                </div>
                {info.note && (
                  <div style={{ ...styles.note, color: info.noteColor }}>{info.note}</div>
                )}
              </div>

              <div style={styles.cardRight}>
                <span style={{ ...styles.badge, background: info.badgeBg, color: info.badgeColor }}>
                  {info.label}
                </span>
                {info.showScanButton && (
                  <button style={styles.scanBtn} onClick={() => setScanningBookingId(req.get_booking.booking_id)}>
                    📱 Scan QR to Check In
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {scanningBookingId && (
        <div style={styles.overlay} onClick={() => setScanningBookingId(null)}>
          <div style={styles.scannerModal} onClick={(e) => e.stopPropagation()}>
            <button style={styles.closeBtn} onClick={() => setScanningBookingId(null)}>×</button>
            <QrScanner bookingId={scanningBookingId} />
            <button
              style={styles.doneBtn}
              onClick={() => { setScanningBookingId(null); loadRequests(); }}
            >
              Done
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

/** Maps a raw BookingRequest (with nested booking) to its display label, colors, and note text. */
function describeRequest(req) {
  if (req.status === 'Pending') {
    return { label: 'Pending Approval', badgeBg: '#fff7e6', badgeColor: '#946200' };
  }

  if (req.status === 'Rejected') {
    return {
      label: 'Rejected',
      badgeBg: '#fff0f1',
      badgeColor: '#bd2130',
      note: 'Your booking request was declined by the facility manager.',
      noteColor: '#bd2130',
    };
  }

  // status === 'Approved' from here on — look at the nested booking to
  // tell Confirmed / Completed / Cancelled apart.
  const booking = req.get_booking;
  if (!booking) {
    // Defensive fallback: shouldn't happen (Approved should always carry a
    // booking), but avoids a blank/crashed card if it ever does.
    return { label: 'Approved', badgeBg: '#eaf2ff', badgeColor: '#0d4cd3' };
  }

  switch (booking.status) {
    case 'Confirmed':
      return {
        label: 'Confirmed',
        badgeBg: '#eaf2ff',
        badgeColor: '#0d4cd3',
        showScanButton: true,
        note: 'Check in within 15 minutes of start time to avoid auto-cancellation',
        noteColor: '#888',
      };

    case 'Checked_In':
      return { label: 'Completed', badgeBg: '#f1f1f1', badgeColor: '#555' };

    case 'Cancelled_No_Show':
      return {
        label: 'Cancelled',
        badgeBg: '#fff0f1',
        badgeColor: '#bd2130',
        note: 'Auto-cancelled due to no-show. Booking expired 15 minutes after start time.',
        noteColor: '#bd2130',
      };

    default:
      return { label: booking.status, badgeBg: '#f1f1f1', badgeColor: '#555' };
  }
}

/**
 * Formats a backend datetime string for display WITHOUT going through any
 * timezone conversion. See CHANGES_TIMEZONE_FIX.md for the full
 * explanation — short version: the backend serializes as UTC ("Z"
 * suffix), and `new Date()` would silently shift displayed times by the
 * browser's timezone offset. Reading the digits straight out of the
 * string avoids that entirely.
 */
function formatDate(dt) {
  return dt.slice(0, 10); // "2026-05-07T10:00:00.000000Z" → "2026-05-07"
}
function formatTime(dt) {
  return dt.slice(11, 16); // "2026-05-07T10:00:00.000000Z" → "10:00"
}

const styles = {
  hint: { fontSize: 14, color: '#888', textAlign: 'center', padding: '20px 0' },
  list: { display: 'flex', flexDirection: 'column', gap: 12 },
  card: {
    border: '1px solid #e1e4e8',
    borderRadius: 12,
    padding: '16px 20px',
    background: '#fff',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 10,
  },
  cardLeft: { flex: 1, minWidth: 220 },
  facilityName: { fontWeight: 700, fontSize: 15, color: '#1a1a2e' },
  timeRow: { fontSize: 12.5, color: '#666', marginTop: 4 },
  note: { fontSize: 12, marginTop: 4 },
  cardRight: { display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 8 },
  badge: { fontSize: 11.5, fontWeight: 700, borderRadius: 12, padding: '3px 12px' },
  scanBtn: {
    padding: '7px 14px', borderRadius: 8, border: 'none',
    background: '#1a1a2e', color: '#fff', fontSize: 12.5, fontWeight: 600, cursor: 'pointer',
    whiteSpace: 'nowrap',
  },
  overlay: {
    position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.45)',
    display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000,
  },
  scannerModal: {
    background: '#fff', borderRadius: 12, padding: 16, width: 520, maxWidth: '92vw',
    position: 'relative',
  },
  closeBtn: {
    position: 'absolute', top: 10, right: 14, border: 'none', background: 'none',
    fontSize: 20, cursor: 'pointer', color: '#999',
  },
  doneBtn: {
    display: 'block', margin: '0 auto', padding: '8px 24px', borderRadius: 8,
    border: '1px solid #e1e4e8', background: '#fff', cursor: 'pointer', fontSize: 13, fontWeight: 600,
  },
};
