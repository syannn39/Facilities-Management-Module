import { useState } from 'react'
import reactLogo from './assets/react.svg'
import viteLogo from './assets/vite.svg'
import './App.css'

// Import the functional feature modules we built in Phase 6
import BookingForm from './components/BookingForm'
import QrScanner from './components/QrScanner'

function App() {
  const [count, setCount] = useState(0)
  
  // For immediate local testing, let's target an entity ID instance.
  // In a full application, these values will dynamically change when a resident 
  // clicks on a specific asset from the "Browse Facilities" dashboard grid view.
  const [activeFacilityId, setActiveFacilityId] = useState(1)
  const [activeBookingId, setActiveBookingId] = useState(1)

  return (
    <>
      {/* HEADER HERO AREA */}
      <section id="center" style={{ paddingBottom: '20px' }}>
        <div className="hero">
          <img src={reactLogo} className="framework" alt="React logo" />
          <img src={viteLogo} className="vite" alt="Vite logo" />
        </div>
        <div>
          <h1>Facilities Management Module</h1>
          <p style={{ color: '#666' }}>
            Multi-Tenant Automated Facility Scheduling & Verification Engine
          </p>
        </div>
        
        {/* Debug Utility: Feel free to delete or keep for testing state updates */}
        <button
          type="button"
          className="counter"
          onClick={() => setCount((count) => count + 1)}
          style={{ marginTop: '10px', padding: '8px 16px', cursor: 'pointer' }}
        >
          Session Interactive Operations Check: {count}
        </button>
      </section>

      <div className="ticks"></div>

      {/* CORE FYP COMPONENT WORKSPACE AREA */}
      <section id="next-steps" style={{ display: 'flex', flexWrap: 'wrap', gap: '30px', justifyContent: 'center', padding: '20px' }}>
        
        {/* Workspace Block 1: Real-Time Dynamic Scheduler Submissions (FR1 / FR3) */}
        <div id="docs" style={{ flex: '1', minWidth: '320px', maxWidth: '480px', background: '#fff', padding: '20px', borderRadius: '8px' }}>
          <h2 style={{ borderBottom: '2px solid #0066cc', paddingBottom: '8px', color: '#2c3e50' }}>
            New Reservation Request
          </h2>
          <p style={{ fontSize: '14px', color: '#7f8c8d' }}>
            Algorithm 2 handles cross-tenant conflict detection automatically upon clicking submission.
          </p>
          
          <div style={{ marginTop: '20px' }}>
            <BookingForm facilityId={activeFacilityId} />
          </div>
        </div>

        {/* Workspace Block 2: Physical Location Verification Entry (FR5) */}
        <div id="social" style={{ flex: '1', minWidth: '320px', maxWidth: '480px', background: '#fff', padding: '20px', borderRadius: '8px' }}>
          <h2 style={{ borderBottom: '2px solid #bd2130', paddingBottom: '8px', color: '#2c3e50' }}>
            Physical Gate Verification
          </h2>
          <p style={{ fontSize: '14px', color: '#7f8c8d' }}>
            Algorithm 3 enforces camera access permissions to capture tokens within a 15-minute arrival window.
          </p>

          <div style={{ marginTop: '20px' }}>
            <QrScanner bookingId={activeBookingId} />
          </div>
        </div>

      </section>

      <div className="ticks"></div>
      <section id="spacer" style={{ height: '100px' }}></section>
    </>
  )
}

export default App