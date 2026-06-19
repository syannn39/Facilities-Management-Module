import { useEffect, useState } from 'react'
import './App.css'
import api from './api'
import Login from './pages/Login'
import TenantView from './pages/TenantView'
import AdminView from './pages/AdminView'

/**
 * App.jsx — login gate + role router.
 *
 * Flow:
 *  1. On mount, check localStorage for an existing token → call /me to
 *     re-validate it (handles page refresh without re-login).
 *  2. If no valid session → show <Login />.
 *  3. After login success → show <TenantView> (Resident) or <AdminView> (Manager).
 */
function App() {
  const [user,            setUser]            = useState(null)
  const [checkingSession, setCheckingSession] = useState(true)

  useEffect(() => {
    const token      = localStorage.getItem('token')
    const cachedUser = localStorage.getItem('user')

    if (token && cachedUser) {
      setUser(JSON.parse(cachedUser))          // show cached state immediately
      api.get('/me')
        .then(res => setUser(res.data))        // then confirm with server
        .catch(() => handleLogout())           // token expired / invalid → logout
    }
    setCheckingSession(false)
  }, [])

  const handleLogout = async () => {
    try { await api.post('/logout') } catch { /* token already invalid is fine */ }
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    setUser(null)
  }

  // Blank while we check localStorage to avoid a flash of the login page
  if (checkingSession) return null

  // ── Not logged in ──────────────────────────────────────────────────────────
  if (!user) return <Login onLoginSuccess={setUser} />

  // ── Logged in ──────────────────────────────────────────────────────────────
  const isManager = user.role === 'Manager'

  return (
    <>
      {/* Top navigation bar */}
      <nav style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '0 24px',
        height: 64,
        background: isManager ? '#1a1a2e' : '#0f3460',
        color: '#fff',
        fontFamily: 'system-ui, Arial, sans-serif',
        boxSizing: 'border-box',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <span style={{ fontSize: 22 }}>🏢</span>
          <span style={{ fontWeight: 700, fontSize: 17, letterSpacing: '-0.3px' }}>PropertyHub</span>
          <span style={{
            marginLeft: 8,
            background: 'rgba(255,255,255,0.15)',
            borderRadius: 20,
            padding: '2px 10px',
            fontSize: 12,
          }}>
            {isManager ? 'Admin Dashboard' : 'Resident Portal'}
          </span>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <span style={{ fontSize: 13, opacity: 0.85 }}>
            👤 {user.name}
          </span>
          <span style={{
            background: isManager ? '#e74c3c' : '#27ae60',
            borderRadius: 20,
            padding: '2px 10px',
            fontSize: 11,
            fontWeight: 600,
          }}>
            {user.role}
          </span>
          <button
            onClick={handleLogout}
            style={{
              padding: '6px 14px',
              background: 'rgba(255,255,255,0.15)',
              border: '1px solid rgba(255,255,255,0.3)',
              borderRadius: 6,
              color: '#fff',
              fontSize: 13,
              cursor: 'pointer',
            }}
          >
            Log Out
          </button>
        </div>
      </nav>

      {/* Role-specific view */}
      {isManager ? <AdminView /> : <TenantView />}
    </>
  )
}

export default App
