import { useState } from 'react';
import FacilityList from '../components/FacilityList';
import MyBookings from '../components/MyBookings';

/**
 * TenantView — the page a Resident (or Student, for a School tenant) sees
 * after login. Mirrors the reference design's sidebar layout: a left nav
 * with "Browse Facilities" / "My Bookings", and the corresponding page on
 * the right. Booking itself now happens inside a modal triggered from a
 * facility card's "Book Now" button (see FacilityList + BookingModal),
 * not on this page directly.
 *
 * bookingsRefreshKey: bumped every time a booking is created in
 * FacilityList. Passed to <MyBookings key={bookingsRefreshKey}> so a fresh
 * GET /bookings always runs the next time that tab is shown — this doesn't
 * rely on the tab switch itself happening to unmount/remount MyBookings
 * (which was the previous, more fragile assumption); it explicitly forces
 * a refetch regardless of navigation order.
 */
export default function TenantView() {
  const [activeTab, setActiveTab] = useState('facilities'); // 'facilities' | 'bookings'
  const [bookingsRefreshKey, setBookingsRefreshKey] = useState(0);

  const navItems = [
    { key: 'facilities', label: 'Browse Facilities', icon: '🏠' },
    { key: 'bookings',   label: 'My Bookings',        icon: '📋' },
  ];

  return (
    <div style={styles.layout}>
      <aside style={styles.sidebar}>
        {navItems.map((item) => (
          <button
            key={item.key}
            onClick={() => setActiveTab(item.key)}
            style={{
              ...styles.navItem,
              ...(activeTab === item.key ? styles.navItemActive : {}),
            }}
          >
            <span style={{ marginRight: 8 }}>{item.icon}</span>
            {item.label}
          </button>
        ))}
      </aside>

      <main style={styles.content}>
        <h2 style={styles.pageTitle}>
          {activeTab === 'facilities' ? 'Browse Facilities' : 'My Bookings'}
        </h2>

        {activeTab === 'facilities' ? (
          <FacilityList onBookingCreated={() => setBookingsRefreshKey((k) => k + 1)} />
        ) : (
          <MyBookings key={bookingsRefreshKey} />
        )}
      </main>
    </div>
  );
}

const styles = {
  layout: {
    display: 'flex',
    minHeight: 'calc(100vh - 64px)', // 64px matches App.jsx's navbar height
    fontFamily: 'system-ui, Arial, sans-serif',
    background: '#f7f8fa',
  },
  sidebar: {
    width: 220,
    background: '#fff',
    borderRight: '1px solid #eee',
    padding: '20px 12px',
    display: 'flex',
    flexDirection: 'column',
    gap: 4,
  },
  navItem: {
    display: 'flex',
    alignItems: 'center',
    textAlign: 'left',
    padding: '10px 14px',
    borderRadius: 8,
    border: 'none',
    background: 'none',
    fontSize: 14,
    color: '#444',
    cursor: 'pointer',
    fontFamily: 'inherit',
  },
  navItemActive: {
    background: '#eaf2ff',
    color: '#0d4cd3',
    fontWeight: 600,
  },
  content: {
    flex: 1,
    padding: '28px 32px',
  },
  pageTitle: {
    margin: '0 0 18px',
    fontSize: 22,
    color: '#1a1a2e',
  },
};
