import { useEffect, useState } from 'react';
import api from '../api';
import QrScanner from './QrScanner';

export default function MyBookings() {
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [scanningBookingId, setScanningBookingId] = useState(null);

  // State to control the currently selected Request object for the modal popup
  const [selectedRequest, setSelectedRequest] = useState(null);

  const loadRequests = () => {
    setLoading(true);
    api.get('/bookings')
      .then(res => setRequests(res.data.data || []))
      .catch(() => setError('Could not load your bookings.'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { loadRequests(); }, []);

  const handleCancel = async (bookingId) => {
    if (!window.confirm("Are you sure you want to cancel this booking?")) return;
    try {
      const response = await api.post(`/bookings/${bookingId}/cancel`);
      if (response.data.success) {
        alert("Booking cancelled successfully!");
        setSelectedRequest(null); // Close the modal
        loadRequests();
      }
    } catch (error) {
      alert("Failed to cancel: " + (error.response?.data?.message || "Unknown error"));
    }
  };

  if (loading) return <p style={styles.hint}>Loading your bookings…</p>;
  if (error) return <p style={{ ...styles.hint, color: '#c00' }}>{error}</p>;
  if (requests.length === 0) {
    return <p style={styles.hint}>You haven't made any bookings yet.</p>;
  }

  return (
    <div>
      <p style={styles.instructionHint}>✨ Click any booking card to view full details and manage actions.</p>

      <div style={styles.list}>
        {requests.map((req) => {
          const info = describeRequest(req);
          const booking = req.get_booking;
          const facility = req.facility || req.get_facility;

          return (
            <div
              key={req.request_id}
              style={styles.card}
              onClick={() => setSelectedRequest(req)} // Click to open details popup
            >
              <div style={styles.cardLeft}>
                <div style={styles.facilityName}>{facility?.name || 'Facility'}</div>

                {facility?.location ? (
                  <div style={styles.locationRow}>
                    📍 {facility.location}
                  </div>
                ) : null}

                <div style={styles.timeRow}>
                  🕐 {formatDate(req.start_time)} · {formatTime(req.start_time)} - {formatTime(req.end_time)}
                </div>
              </div>

              <div style={styles.cardRight}>
                <span style={{ ...styles.badge, background: info.badgeBg, color: info.badgeColor }}>
                  {info.label}
                </span>
                <span style={styles.detailsPrompt}>View Details →</span>
              </div>
            </div>
          );
        })}
      </div>

      {/* --- Detail Modal Popup on Card Click --- */}
      {selectedRequest && (() => {
        const req = selectedRequest;
        const info = describeRequest(req);
        const booking = req.get_booking;
        const facility = req.facility || req.get_facility;

        // Show purpose or guest count only if purpose exists or guest_count is strictly greater than 0
        const hasPurposeOrGuests = req.purpose_of_use || (req.guest_count !== null && req.guest_count !== undefined && req.guest_count > 0);

        // Determine if action buttons (Scan QR / Cancel) should be available:
        // 1. If it has a Confirmed booking (`booking.status === 'Confirmed'`)
        // 2. OR if it's an approved request awaiting booking creation (`req.status === 'Approved'` without a booking row yet)
        const isConfirmedBooking = booking && booking.status === 'Confirmed';
        const isApprovedRequestWithoutBooking = req.status === 'Approved' && !booking;
        const canTakeActions = isConfirmedBooking || isApprovedRequestWithoutBooking;

        return (
          <div style={styles.modalOverlay} onClick={() => setSelectedRequest(null)}>
            <div style={styles.detailModal} onClick={(e) => e.stopPropagation()}>

              {/* Modal Header */}
              <div style={styles.modalHeader}>
                <div>
                  <h3 style={styles.modalTitle}>{facility?.name || 'Facility Booking'}</h3>
                  {facility?.location && <p style={styles.modalSubtitle}>📍 {facility.location}</p>}
                </div>
                <button style={styles.closeBtn} onClick={() => setSelectedRequest(null)}>✕</button>
              </div>

              {/* Modal Body Content */}
              <div style={styles.modalBody}>

                {/* Status Banner */}
                <div style={{ ...styles.statusBanner, background: info.badgeBg, color: info.badgeColor }}>
                  Status: <strong>{info.label}</strong>
                </div>

                {/* Date, Time, and Conditional Details */}
                <div style={styles.infoSection}>
                  <div style={styles.infoGroup}>
                    <span style={styles.infoLabel}>Date & Time</span>
                    <span style={styles.infoValue}>
                      📅 {formatDate(req.start_time)} <br />
                      ⏰ {formatTime(req.start_time)} - {formatTime(req.end_time)}
                    </span>
                  </div>

                  {/* Render only if valid purpose or guest_count > 0 exists (Tier 1 workflow) */}
                  {hasPurposeOrGuests && (
                    <>
                      {req.purpose_of_use && (
                        <div style={styles.infoGroup}>
                          <span style={styles.infoLabel}>Purpose of Use</span>
                          <span style={styles.infoValue}>{req.purpose_of_use}</span>
                        </div>
                      )}
                      {req.guest_count > 0 && (
                        <div style={styles.infoGroup}>
                          <span style={styles.infoLabel}>Expected Guests</span>
                          <span style={styles.infoValue}>{req.guest_count} People</span>
                        </div>
                      )}
                    </>
                  )}
                </div>

                {/* Show rejection reason if status is Rejected */}
                {req.status === 'Rejected' && (() => {
                  const logs = req.get_approval_logs || req.approval_logs || [];
                  const rejectionLog = logs.find(log => log.action === 'Rejected') || logs[0];
                  const reasonText = rejectionLog?.remarks || req.remarks || req.admin_remarks || 'No remarks provided by manager.';

                  return (
                    <div style={styles.reasonBox}>
                      <span style={styles.reasonTitle}>❌ Rejection Reason</span>
                      <p style={styles.reasonText}>{reasonText}</p>
                    </div>
                  );
                })()}

                {/* Show cancellation note if applicable */}
                {info.note && (
                  <div style={{ ...styles.noteBox, color: info.noteColor }}>
                    {info.note}
                  </div>
                )}
              </div>

              {/* Modal Footer Actions */}
              <div style={styles.modalFooter}>
                {/* Show Scan QR Code button for both Confirmed bookings and Approved requests */}
                {canTakeActions && (
                  <button
                    style={styles.scanBtnModal}
                    onClick={() => {
                      const targetId = booking ? booking.booking_id : req.request_id;
                      setSelectedRequest(null);
                      setScanningBookingId(targetId);
                    }}
                  >
                    📱 Scan QR Code
                  </button>
                )}

                {/* Show Cancel button for both Confirmed bookings and Approved requests */}
                {canTakeActions && (
                  <button
                    onClick={(e) => handleCancel(booking ? booking.booking_id : req.request_id, e)}
                    style={styles.cancelBtnModal}
                  >
                    Cancel Booking
                  </button>
                )}

                <button style={styles.closeActionBtn} onClick={() => setSelectedRequest(null)}>
                  Close
                </button>
              </div>

            </div>
          </div>
        );
      })()}

      {/* --- QR Scanner Modal --- */}
      {scanningBookingId && (
        <div style={styles.modalOverlay} onClick={() => setScanningBookingId(null)}>
          <div style={styles.scannerModal} onClick={(e) => e.stopPropagation()}>
            <button style={styles.closeBtn} onClick={() => setScanningBookingId(null)}>✕</button>
            <QrScanner bookingId={scanningBookingId} />
            <button style={styles.doneBtn} onClick={() => { setScanningBookingId(null); loadRequests(); }}>
              Done
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

// --- Helper Functions ---

function describeRequest(req) {
  if (req.status === 'Pending') return { label: 'Pending Approval', badgeBg: '#fff7e6', badgeColor: '#946200' };
  if (req.status === 'Rejected') return { label: 'Rejected', badgeBg: '#fff0f1', badgeColor: '#bd2130' };

  const booking = req.get_booking;
  if (!booking) return { label: 'Approved', badgeBg: '#eaf2ff', badgeColor: '#0d4cd3', showScanButton: true };

  switch (booking.status) {
    case 'Confirmed':
      return { label: 'Confirmed', badgeBg: '#eaf2ff', badgeColor: '#0d4cd3', showScanButton: true };
    case 'Cancelled_By_User':
      return { label: 'Cancelled', badgeBg: '#fff0f1', badgeColor: '#bd2130', note: 'You cancelled this booking.', noteColor: '#bd2130' };
    case 'Checked_In':
      return { label: 'Completed', badgeBg: '#f1f1f1', badgeColor: '#555' };
    case 'Cancelled_No_Show':
      return { label: 'Cancelled', badgeBg: '#fff0f1', badgeColor: '#bd2130', note: 'Auto-cancelled due to no-show.', noteColor: '#bd2130' };
    default:
      return { label: booking.status, badgeBg: '#f1f1f1', badgeColor: '#555' };
  }
}

function formatDate(dt) { return dt.slice(0, 10); }
function formatTime(dt) { return dt.slice(11, 16); }

// --- Styles ---

const styles = {
  hint: { fontSize: 14, color: '#888', textAlign: 'center', padding: '20px 0' },
  instructionHint: { fontSize: 13, color: '#666', marginBottom: '14px', fontStyle: 'italic' },
  list: { display: 'flex', flexDirection: 'column', gap: 12 },

  card: {
    border: '1px solid #e1e4e8',
    borderRadius: 12,
    padding: '16px 20px',
    background: '#fff',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: 10,
    cursor: 'pointer',
    boxShadow: '0 1px 3px rgba(0,0,0,0.02)',
    transition: 'transform 0.15s ease, box-shadow 0.15s ease',
  },
  cardLeft: { flex: 1 },
  facilityName: { fontWeight: 700, fontSize: 15.5, color: '#1a1a2e' },
  locationRow: { fontSize: 12.5, color: '#555', marginTop: 4 },
  timeRow: { fontSize: 12.5, color: '#666', marginTop: 6 },

  cardRight: { display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 6 },
  badge: { fontSize: 11.5, fontWeight: 700, borderRadius: 12, padding: '4px 12px' },
  detailsPrompt: { fontSize: '11.5px', color: '#64748b', fontWeight: 600 },

  // Modal overlay and container styles
  modalOverlay: {
    position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    zIndex: 1000, backdropFilter: 'blur(3px)'
  },
  detailModal: {
    background: '#fff', borderRadius: 16, width: 480, maxWidth: '92vw',
    boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
    overflow: 'hidden', animation: 'scaleUp 0.2s ease'
  },
  modalHeader: {
    padding: '20px 24px', background: '#f8fafc', borderBottom: '1px solid #e2e8f0',
    display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start'
  },
  modalTitle: { margin: 0, fontSize: 18, fontWeight: 700, color: '#1e293b' },
  modalSubtitle: { margin: '4px 0 0', fontSize: 13, color: '#64748b' },
  closeBtn: { border: 'none', background: 'transparent', fontSize: 18, cursor: 'pointer', color: '#94a3b8', padding: 4 },

  modalBody: { padding: '24px' },
  statusBanner: { padding: '10px 16px', borderRadius: 8, fontSize: '13px', fontWeight: 600, marginBottom: '20px', textAlign: 'center' },

  infoSection: { display: 'flex', flexDirection: 'column', gap: '14px', background: '#f8fafc', padding: '16px', borderRadius: '10px', border: '1px solid #f1f5f9' },
  infoGroup: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' },
  infoLabel: { fontSize: '13px', color: '#64748b', fontWeight: 500 },
  infoValue: { fontSize: '13.5px', color: '#1e293b', fontWeight: 600, textAlign: 'right' },

  reasonBox: { background: '#fef2f2', border: '1px solid #fecaca', borderRadius: 8, padding: '12px 16px', marginTop: '16px' },
  reasonTitle: { fontSize: '13px', fontWeight: 700, color: '#991b1b', display: 'block', marginBottom: '4px' },
  reasonText: { fontSize: '13px', color: '#7f1d1d', margin: 0 },

  noteBox: { fontSize: '13px', marginTop: '16px', padding: '10px 14px', background: '#f1f5f9', borderRadius: 8 },

  modalFooter: {
    padding: '16px 24px', background: '#f8fafc', borderTop: '1px solid #e2e8f0',
    display: 'flex', justifyContent: 'flex-end', gap: '10px', alignItems: 'center'
  },
  scanBtnModal: { padding: '9px 16px', borderRadius: 8, border: 'none', background: '#1a1a2e', color: '#fff', fontSize: '13px', fontWeight: 600, cursor: 'pointer' },
  cancelBtnModal: { backgroundColor: '#dc3545', color: 'white', padding: '9px 16px', border: 'none', borderRadius: 8, fontSize: '13px', fontWeight: 600, cursor: 'pointer' },
  closeActionBtn: { padding: '9px 16px', borderRadius: '8px', border: '1px solid #cbd5e1', background: '#fff', color: '#475569', fontSize: '13px', fontWeight: '600', cursor: 'pointer' },

  scannerModal: { background: '#fff', borderRadius: 12, padding: 16, width: 520, maxWidth: '92vw', position: 'relative' },
  doneBtn: { display: 'block', margin: '10px auto 0', padding: '8px 24px', borderRadius: '8px', border: '1px solid #e1e4e8', background: '#fff', cursor: 'pointer' },
};