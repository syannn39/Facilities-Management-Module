import { useState } from 'react';

/**
 * Builds a "YYYY-MM-DD" key from a Date's LOCAL year/month/day —
 * deliberately NOT using `date.toISOString()`. toISOString() first
 * converts the Date to UTC before formatting: a Date built as "29th at
 * local midnight" in a UTC+8 timezone is actually "28th, 16:00 UTC" once
 * converted, so toISOString() would silently hand back "28" for a
 * calendar click on "29" (or for "today" if the current local time is
 * before UTC midnight rolls over). Reading the local
 * getFullYear/getMonth/getDate instead avoids any UTC conversion — the
 * date intended is the date kept.
 *
 * Exported so BookingModal.jsx can use the exact same logic for "today"
 * rather than maintaining a second, easily-out-of-sync copy of this fix.
 */
export function toLocalDateKey(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

/**
 * Minimal month calendar — no external date-picker library, just plain
 * React state, to avoid adding a new npm dependency for one widget.
 * Dates before today are disabled (greyed out, like the reference design).
 */
export default function Calendar({ selectedDate, onSelectDate }) {
  const [viewMonth, setViewMonth] = useState(() => {
    const d = selectedDate ? new Date(selectedDate) : new Date();
    return new Date(d.getFullYear(), d.getMonth(), 1);
  });

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const monthLabel = viewMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const firstDayOfWeek = viewMonth.getDay(); // 0 = Sunday
  const daysInMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 0).getDate();
  const daysInPrevMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 0).getDate();

  const cells = [];
  // Leading days from the previous month (greyed, not clickable)
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    cells.push({ day: daysInPrevMonth - i, inMonth: false, date: null });
  }
  // Days in the current month
  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), d);
    cells.push({ day: d, inMonth: true, date });
  }

  const changeMonth = (delta) => {
    setViewMonth(new Date(viewMonth.getFullYear(), viewMonth.getMonth() + delta, 1));
  };

  return (
    <div style={styles.wrap}>
      <div style={styles.header}>
        <button type="button" onClick={() => changeMonth(-1)} style={styles.navBtn}>‹</button>
        <span style={styles.monthLabel}>{monthLabel}</span>
        <button type="button" onClick={() => changeMonth(1)} style={styles.navBtn}>›</button>
      </div>

      <div style={styles.weekRow}>
        {['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map((d) => (
          <span key={d} style={styles.weekDay}>{d}</span>
        ))}
      </div>

      <div style={styles.grid}>
        {cells.map((cell, i) => {
          if (!cell.inMonth) {
            return <span key={i} style={styles.dayMuted}>{cell.day}</span>;
          }

          const isPast = cell.date < today;
          const isSelected = selectedDate && toLocalDateKey(cell.date) === selectedDate;

          return (
            <button
              key={i}
              type="button"
              disabled={isPast}
              onClick={() => onSelectDate(toLocalDateKey(cell.date))}
              style={{
                ...styles.day,
                ...(isPast ? styles.dayDisabled : {}),
                ...(isSelected ? styles.daySelected : {}),
              }}
            >
              {cell.day}
            </button>
          );
        })}
      </div>
    </div>
  );
}

const styles = {
  wrap: { fontFamily: 'inherit', width: 230 },
  header: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  navBtn: {
    border: 'none',
    background: 'none',
    fontSize: 16,
    cursor: 'pointer',
    color: '#444',
    padding: '2px 8px',
  },
  monthLabel: { fontSize: 13.5, fontWeight: 600, color: '#1a1a2e' },
  weekRow: {
    display: 'grid',
    gridTemplateColumns: 'repeat(7, 1fr)',
    marginBottom: 4,
  },
  weekDay: {
    fontSize: 11,
    color: '#999',
    textAlign: 'center',
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(7, 1fr)',
    rowGap: 2,
  },
  day: {
    border: 'none',
    background: 'none',
    borderRadius: 6,
    padding: '6px 0',
    fontSize: 13,
    cursor: 'pointer',
    color: '#222',
  },
  dayMuted: {
    padding: '6px 0',
    fontSize: 13,
    textAlign: 'center',
    color: '#ddd',
  },
  dayDisabled: {
    color: '#ccc',
    cursor: 'not-allowed',
  },
  daySelected: {
    background: '#1a1a2e',
    color: '#fff',
    fontWeight: 600,
  },
};
