import { useEffect, useState } from 'react';
import api from '../api';
import Calendar, { toLocalDateKey } from './Calendar';

/**
 * BookingModal — covers screens 2, 3, 4, 5 from the reference design.
 *
 * Step 1 ("form"): pick a date, then pick from that day's available slots
 *   (GET /api/facilities/{id}/availability — only slots with available:true
 *   are rendered as selectable; taken slots are shown struck-through and
 *   disabled, never selectable). If the facility's approval_tier > 0, also
 *   collect Purpose of Use + Expected Guest Count, and the primary button
 *   reads "Submit Request" instead of "Book Now".
 *
 * Step 2 ("confirm"): a summary screen. Wording and the manager-approval
 *   warning differ depending on requiresApproval, matching screens 3 vs 5.
 *
 * On confirm, calls POST /api/bookings (same endpoint either way — the
 * backend's approval_tier check decides Confirmed vs Pending; this modal
 * never decides that itself, it only mirrors what the facility card already
 * told the user).
 */
export default function BookingModal({ facility, onClose, onBooked }) {
  const requiresApproval = (facility.get_operational_rule?.approval_tier ?? 0) > 0;
  const todayKey = toLocalDateKey(new Date());

  const [step, setStep] = useState('form'); // 'form' | 'confirm'
  const [selectedDate, setSelectedDate] = useState(todayKey);
  const [slots, setSlots] = useState([]);
  const [slotsLoading, setSlotsLoading] = useState(true);
  const [slotsError, setSlotsError] = useState('');
  const [selectedSlot, setSelectedSlot] = useState(null); // { start, end }
  const [purpose, setPurpose] = useState('');
  const [guestCount, setGuestCount] = useState(1);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');

  useEffect(() => {
    setSlotsLoading(true);
    setSlotsError('');
    setSelectedSlot(null);

    api.get(`/facilities/${facility.facility_id}/availability`, { params: { date: selectedDate } })
      .then(res => setSlots(res.data.data || []))
      .catch(() => setSlotsError('Could not load time slots.'))
      .finally(() => setSlotsLoading(false));
  }, [facility.facility_id, selectedDate]);

  const canProceed = selectedSlot && (!requiresApproval || (purpose.trim() && guestCount > 0));

  const handleConfirm = async () => {
    setSubmitting(true);
    setSubmitError('');

    try {
      await api.post('/bookings', {
        facility_id: facility.facility_id,
        start_time: `${selectedDate} ${selectedSlot.start}:00`,
        end_time: `${selectedDate} ${selectedSlot.end}:00`,
        purpose_of_use: requiresApproval ? purpose : null,
        guest_count: requiresApproval ? guestCount : 0,
      });
      onBooked();
    } catch (err) {
      setSubmitError(err.response?.data?.message || 'Something went wrong. Please try again.');
      setSubmitting(false);
    }
  };

  const formattedDate = new Date(`${selectedDate}T00:00:00`).toLocaleDateString('en-US', {
    month: 'numeric', day: 'numeric', year: 'numeric',
  });

  return (
    <div style={styles.overlay} onClick={onClose}>
      <div style={styles.modal} onClick={(e) => e.stopPropagation()}>

        {step === 'form' && (
          <>
            <div style={styles.header}>
              <div>
                <h3 style={styles.title}>Book {facility.name}</h3>
                <p style={styles.subtitle}>Select your preferred date and time to book this facility</p>
              </div>
              <button onClick={onClose} style={styles.closeBtn}>×</button>
            </div>

            <div style={styles.bodyRow}>
              <div>
                <p style={styles.sectionLabel}>Select Date</p>
                <Calendar selectedDate={selectedDate} onSelectDate={setSelectedDate} />
              </div>

              <div style={styles.slotColumn}>
                <p style={styles.sectionLabel}>Available Time Slots</p>
                <div style={styles.slotList}>
                  {slotsLoading && <p style={styles.hint}>Loading…</p>}
                  {slotsError && <p style={{ ...styles.hint, color: '#c00' }}>{slotsError}</p>}
                  {!slotsLoading && !slotsError && slots.length === 0 && (
                    <p style={styles.hint}>No slots configured for this facility.</p>
                  )}
                  {!slotsLoading && slots.map((slot) => {
                    const isSelected = selectedSlot?.start === slot.start;
                    return (
                      <button
                        key={slot.start}
                        type="button"
                        disabled={!slot.available}
                        onClick={() => setSelectedSlot(slot)}
                        style={{
                          ...styles.slotBtn,
                          ...(isSelected ? styles.slotBtnSelected : {}),
                          ...(!slot.available ? styles.slotBtnTaken : {}),
                        }}
                      >
                        {slot.start} - {slot.end}
                        {isSelected && <span style={styles.dot}>●</span>}
                        {!slot.available && <span style={styles.takenLabel}>Booked</span>}
                      </button>
                    );
                  })}
                </div>

                {requiresApproval && (
                  <div style={styles.purposeRow}>
                    <div style={styles.purposeCol}>
                      <p style={styles.sectionLabel}>Purpose of Use</p>
                      <textarea
                        value={purpose}
                        onChange={(e) => setPurpose(e.target.value)}
                        placeholder="Describe the purpose of your booking..."
                        style={styles.textarea}
                      />
                    </div>
                    <div style={styles.guestCol}>
                      <p style={styles.sectionLabel}>Expected Guest Count</p>
                      <input
                        type="number"
                        min={1}
                        value={guestCount}
                        onChange={(e) => setGuestCount(parseInt(e.target.value, 10) || 1)}
                        style={styles.numberInput}
                      />
                    </div>
                  </div>
                )}
              </div>
            </div>

            <div style={styles.footer}>
              <button onClick={onClose} style={styles.secondaryBtn}>Cancel</button>
              <button
                disabled={!canProceed}
                onClick={() => { setStep('confirm'); setSubmitError(''); }}
                style={{ ...styles.primaryBtn, ...(!canProceed ? styles.primaryBtnDisabled : {}) }}
              >
                {requiresApproval ? 'Submit Request' : 'Book Now'}
              </button>
            </div>
          </>
        )}

        {step === 'confirm' && (
          <>
            <div style={styles.header}>
              <h3 style={styles.title}>Confirm Booking</h3>
              <button onClick={onClose} style={styles.closeBtn}>×</button>
            </div>

            <p style={styles.confirmText}>
              {requiresApproval
                ? 'Are you sure you want to submit this booking request? Your request will be sent to the facility manager for approval.'
                : 'Are you sure you want to book this facility? Once confirmed, the facility will be reserved for your selected time slot.'}
            </p>

            <div style={styles.summaryBox}>
              <p style={styles.summaryTitle}>Booking Details:</p>
              <p style={styles.summaryLine}>Facility: {facility.name}</p>
              <p style={styles.summaryLine}>Date: {formattedDate}</p>
              <p style={styles.summaryLine}>Time: {selectedSlot.start} - {selectedSlot.end}</p>
              {requiresApproval && (
                <p style={styles.approvalWarning}>⚠ This booking requires manager approval.</p>
              )}
            </div>

            {submitError && <p style={{ ...styles.hint, color: '#c00' }}>{submitError}</p>}

            <div style={styles.footer}>
              <button onClick={() => { setStep('form'); setSubmitError(''); }} style={styles.secondaryBtn}>Cancel</button>
              <button
                disabled={submitting}
                onClick={handleConfirm}
                style={styles.primaryBtn}
              >
                {submitting ? 'Submitting…' : (requiresApproval ? 'Submit Request' : 'Confirm Booking')}
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

const styles = {
  overlay: {
    position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.45)',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    zIndex: 1000,
  },
  modal: {
    background: '#fff', borderRadius: 12, padding: 24,
    width: 500, maxWidth: '94vw', maxHeight: '88vh', overflowY: 'auto',
    fontFamily: 'system-ui, Arial, sans-serif',
  },
  header: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 16 },
  title: { margin: 0, fontSize: 17, color: '#1a1a2e' },
  subtitle: { margin: '4px 0 0', fontSize: 12.5, color: '#888' },
  closeBtn: { border: 'none', background: 'none', fontSize: 20, cursor: 'pointer', color: '#999', lineHeight: 1 },
  // Widths here are deliberate: 230 (Calendar's fixed width) + 20 (gap) + 200
  // (slotColumn's floor) = 450, comfortably under modal's 620 - 48 padding =
  // 572 usable px. Keep this margin — it's what stops the two columns from
  // flex-wrapping onto separate lines (which is what silently turns the
  // side-by-side layout into a stacked one if the numbers get too tight).
  bodyRow: { display: 'flex', gap: 20, flexWrap: 'wrap' },
  sectionLabel: { fontSize: 12.5, fontWeight: 600, color: '#444', margin: '0 0 8px' },
  slotColumn: { flex: 1, minWidth: 200 },
  purposeCol: { flex: 2, minWidth: 200, marginTop: 20 },
  guestCol: { flex: 3, minWidth: 80, marginTop: 20},
  slotList: {
    display: 'flex', flexDirection: 'column',
    maxHeight: 220, overflowY: 'auto', paddingRight: 4,
  },
  slotBtn: {
    textAlign: 'left', padding: '10px 18px', borderRadius: 6,
    border: 'none', borderBottom: '1px solid #f1f1f4', background: 'transparent', cursor: 'pointer',
    fontSize: 14, color: '#222', fontFamily: 'inherit',
    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
  },
  slotBtnSelected: { background: '#f5f5fa', fontWeight: 700, color: '#1a1a2e' },
  slotBtnTaken: { color: '#bbb', textDecoration: 'line-through', cursor: 'not-allowed', background: 'transparent' },
  dot: { fontSize: 8, marginRight: 6 },
  takenLabel: { fontSize: 10.5, textDecoration: 'none', color: '#c00', fontWeight: 600 },
  textarea: {
    width: '100%', minHeight: 200, borderRadius: 8, border: '1px solid #e1e4e8',
    padding: 10, fontSize: 13, fontFamily: 'inherit', resize: 'vertical', boxSizing: 'border-box',
  },
  numberInput: {
    width: '100%', borderRadius: 8, border: '1px solid #e1e4e8',
    padding: '8px 10px', fontSize: 13, fontFamily: 'inherit', boxSizing: 'border-box',
  },
  hint: { fontSize: 13, color: '#888' },
  footer: { display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 20 },
  secondaryBtn: {
    padding: '9px 18px', borderRadius: 8, border: '1px solid #e1e4e8',
    background: '#fff', cursor: 'pointer', fontSize: 13.5, fontWeight: 600, color: '#333',
  },
  primaryBtn: {
    padding: '9px 18px', borderRadius: 8, border: 'none',
    background: '#1a1a2e', color: '#fff', cursor: 'pointer', fontSize: 13.5, fontWeight: 600,
  },
  primaryBtnDisabled: { opacity: 0.5, cursor: 'not-allowed' },
  confirmText: { fontSize: 13.5, color: '#555', lineHeight: 1.5 },
  summaryBox: { background: '#eef2ff', borderRadius: 10, padding: 14, marginTop: 14 },
  summaryTitle: { margin: '0 0 6px', fontSize: 13, fontWeight: 700, color: '#1a1a2e' },
  summaryLine: { margin: '2px 0', fontSize: 13, color: '#333' },
  approvalWarning: { margin: '8px 0 0', fontSize: 12.5, color: '#946200', fontWeight: 600 },
};