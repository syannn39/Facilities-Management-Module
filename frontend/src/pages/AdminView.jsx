/**
 * AdminView — shown to users with role = 'Manager'.
 * Left blank for now as requested; add your admin features here later.
 */
export default function AdminView() {
  return (
    <div style={styles.page}>
      <div style={styles.card}>
        <div style={styles.icon}>🔑</div>
        <h2 style={styles.title}>Admin Dashboard</h2>
        <p style={styles.sub}>
          You are logged in as a <strong>Manager</strong>.
        </p>
        <p style={styles.note}>
          Admin features (approvals, reports, facility management) will be added here.
        </p>
        <div style={styles.badge}>Coming Soon</div>
      </div>
    </div>
  );
}

const styles = {
  page: {
    minHeight: 'calc(100vh - 64px)',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: '#f4f5f7',
    fontFamily: 'system-ui, Arial, sans-serif',
  },
  card: {
    background: '#fff',
    borderRadius: 16,
    padding: '48px 40px',
    textAlign: 'center',
    maxWidth: 420,
    boxShadow: '0 4px 20px rgba(0,0,0,0.08)',
  },
  icon: {
    fontSize: 48,
    marginBottom: 16,
  },
  title: {
    margin: '0 0 8px',
    fontSize: 24,
    fontWeight: 700,
    color: '#1a1a2e',
  },
  sub: {
    margin: '0 0 12px',
    color: '#555',
    fontSize: 15,
  },
  note: {
    margin: '0 0 20px',
    color: '#888',
    fontSize: 14,
    lineHeight: 1.6,
  },
  badge: {
    display: 'inline-block',
    background: '#e8f0fe',
    color: '#1a73e8',
    padding: '6px 16px',
    borderRadius: 20,
    fontSize: 13,
    fontWeight: 600,
  },
};
