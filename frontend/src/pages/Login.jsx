import { useState } from 'react';
import api from '../api';

/**
 * Login page — shown when no valid session exists.
 * On success it calls onLoginSuccess(user) and App.jsx takes over routing.
 */
export default function Login({ onLoginSuccess }) {
  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [error,    setError]    = useState('');
  const [loading,  setLoading]  = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const { data } = await api.post('/login', { email, password });
      localStorage.setItem('token', data.token);
      localStorage.setItem('user',  JSON.stringify(data.user));
      onLoginSuccess(data.user);
    } catch (err) {
      setError(err.response?.data?.message || 'Login failed. Make sure the backend is running.');
    } finally {
      setLoading(false);
    }
  };

  // Quick-fill helpers updated to match the new tiered seeder data (including resident2)
  const TEST_ACCOUNTS = {
    resident:  'resident@test.com',  // Sunrise (Tier 0)
    resident2: 'resident2@test.com', // Sunrise (Second Resident for concurrency testing)
    manager:   'manager@test.com',   // Sunrise (Tier 1)
    jmb:       'jmb@test.com',       // Sunrise (Tier 2)
    student:   'student@test.com',   // Greenwood (Tier 0)
    lecturer:  'lecturer@test.com',  // Greenwood (Tier 1)
    dean:      'dean@test.com',      // Greenwood (Tier 2)
  };
  
  const fill = (accountKey) => {
    setEmail(TEST_ACCOUNTS[accountKey]);
    setPassword('password');
  };

  return (
    <div style={styles.page}>
      <div style={styles.card}>
        {/* Logo / brand */}
        <div style={styles.brand}>
          <div style={styles.brandIcon}>🏢</div>
          <h1 style={styles.brandName}>PropertyHub</h1>
          <p style={styles.brandSub}>Facilities Management System</p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} style={styles.form}>
          <div style={styles.field}>
            <label style={styles.label}>Email Address</label>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="you@example.com"
              style={styles.input}
              required
            />
          </div>

          <div style={styles.field}>
            <label style={styles.label}>Password</label>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="••••••••"
              style={styles.input}
              required
            />
          </div>

          {error && <div style={styles.error}>{error}</div>}

          <button type="submit" disabled={loading} style={styles.btn}>
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
        </form>

        {/* Quick-fill for testing */}
        <div style={styles.quickFill}>
          <p style={styles.quickFillTitle}>Test Accounts (run seeder first)</p>

          <p style={styles.quickFillGroupLabel}>🏢 Sunrise Residences</p>
          <div style={styles.quickFillBtns}>
            <button type="button" style={styles.chipBtn} onClick={() => fill('resident')}>
              👤 Resident 1
            </button>
            <button type="button" style={styles.chipBtn} onClick={() => fill('resident2')}>
              👥 Resident 2
            </button>
            <button type="button" style={styles.chipBtn} onClick={() => fill('manager')}>
              🔑 Tier 1 (Manager)
            </button>
            <button type="button" style={styles.chipBtn} onClick={() => fill('jmb')}>
              👑 Tier 2 (JMB)
            </button>
          </div>

          <p style={styles.quickFillGroupLabel}>🎓 Greenwood International School</p>
          <div style={styles.quickFillBtns}>
            <button type="button" style={styles.chipBtn} onClick={() => fill('student')}>
              👤 Student
            </button>
            <button type="button" style={styles.chipBtn} onClick={() => fill('lecturer')}>
              🔑 Tier 1 (Lecturer)
            </button>
            <button type="button" style={styles.chipBtn} onClick={() => fill('dean')}>
              👑 Tier 2 (Dean)
            </button>
          </div>

          <p style={styles.quickFillHint}>
            Password for all: <code>password</code>
          </p>
        </div>
      </div>
    </div>
  );
}

const styles = {
  page: {
    minHeight: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)',
    fontFamily: 'system-ui, Arial, sans-serif',
    padding: 20,
  },
  card: {
    background: '#fff',
    borderRadius: 16,
    padding: '40px 36px',
    width: '100%',
    maxWidth: 420, // 稍微调宽了一点以容纳新增的按钮
    boxShadow: '0 20px 60px rgba(0,0,0,0.3)',
  },
  brand: {
    textAlign: 'center',
    marginBottom: 28,
  },
  brandIcon: {
    fontSize: 40,
    marginBottom: 8,
  },
  brandName: {
    margin: '0 0 4px',
    fontSize: 26,
    fontWeight: 700,
    color: '#1a1a2e',
    fontFamily: 'system-ui, Arial, sans-serif',
  },
  brandSub: {
    margin: 0,
    fontSize: 13,
    color: '#888',
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: 16,
  },
  field: {
    display: 'flex',
    flexDirection: 'column',
    gap: 6,
  },
  label: {
    fontSize: 13,
    fontWeight: 600,
    color: '#444',
  },
  input: {
    padding: '10px 12px',
    border: '1.5px solid #ddd',
    borderRadius: 8,
    fontSize: 14,
    outline: 'none',
    transition: 'border-color 0.2s',
  },
  error: {
    background: '#fff0f0',
    border: '1px solid #ffcccc',
    borderRadius: 8,
    padding: '10px 12px',
    color: '#c00',
    fontSize: 13,
  },
  btn: {
    marginTop: 4,
    padding: '12px',
    background: '#0f3460',
    color: '#fff',
    border: 'none',
    borderRadius: 8,
    fontSize: 15,
    fontWeight: 600,
    cursor: 'pointer',
    transition: 'background 0.2s',
  },
  quickFill: {
    marginTop: 24,
    paddingTop: 20,
    borderTop: '1px solid #eee',
    textAlign: 'center',
  },
  quickFillTitle: {
    margin: '0 0 10px',
    fontSize: 12,
    color: '#999',
    textTransform: 'uppercase',
    letterSpacing: '0.5px',
  },
  quickFillGroupLabel: {
    margin: '12px 0 6px',
    fontSize: 12,
    color: '#666',
    fontWeight: 600,
  },
  quickFillBtns: {
    display: 'flex',
    gap: 6,
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  chipBtn: {
    padding: '6px 12px',
    border: '1.5px solid #ddd',
    borderRadius: 20,
    background: '#fff',
    fontSize: 12,
    cursor: 'pointer',
    color: '#444',
  },
  quickFillHint: {
    marginTop: 8,
    fontSize: 12,
    color: '#bbb',
  },
};