import React, { useState, useEffect } from 'react';
import api from '../api';

export default function AdminView() {
  const [facilities, setFacilities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchFacilities();
  }, []);

  const fetchFacilities = async () => {
    try {
      const response = await api.get('/facilities');
      setFacilities(response.data.data || response.data);
      setError(null);
    } catch (err) {
      console.error("Failed to fetch facilities:", err);
      setError("Unable to load facilities from the database.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={styles.pageContainer}>
      <div style={styles.content}>
        
        <div style={styles.header}>
          <h2 style={styles.title}>Manage Facilities</h2>
          <button style={styles.addButton}>+ Add New Facility</button>
        </div>

        {error && <div style={styles.errorBanner}>{error}</div>}

        <div style={styles.tableContainer}>
          <table style={styles.table}>
            <thead>
              <tr style={styles.tableHeadRow}>
                <th style={styles.th}>Facility Name</th>
                <th style={styles.th}>Type</th>
                <th style={styles.th}>Capacity</th>
                <th style={styles.th}>Advance Booking Limit</th>
                <th style={styles.th}>Status</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="5" style={styles.emptyState}>Loading database records...</td>
                </tr>
              ) : facilities.length === 0 ? (
                <tr>
                  <td colSpan="5" style={styles.emptyState}>No facilities found.</td>
                </tr>
              ) : (
                facilities.map((facility) => (
                  <tr key={facility.id} style={styles.tableRow}>
                    <td style={{...styles.td, fontWeight: 'bold'}}>{facility.name}</td>
                    <td style={styles.td}>{facility.type || 'Standard'}</td>
                    <td style={styles.td}>{facility.capacity || 'N/A'}</td>
                    <td style={styles.td}>
                      {facility.operational_rule?.advance_booking_days 
                        ? `${facility.operational_rule.advance_booking_days} days` 
                        : 'Not set'}
                    </td>
                    <td style={styles.td}>
                      <span style={facility.status === 'active' ? styles.statusActive : styles.statusWarning}>
                        {facility.status || 'Active'}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

      </div>
    </div>
  );
}

// Native Inline Styles (Matching SY's architecture)
const styles = {
  pageContainer: {
    minHeight: 'calc(100vh - 64px)',
    backgroundColor: '#f4f5f7',
    padding: '40px 20px',
    fontFamily: 'system-ui, Arial, sans-serif',
  },
  content: {
    maxWidth: '1000px',
    margin: '0 auto',
  },
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: '24px',
  },
  title: {
    margin: 0,
    fontSize: '24px',
    color: '#1a1a2e',
  },
  addButton: {
    backgroundColor: '#1a1a2e',
    color: 'white',
    border: 'none',
    padding: '10px 16px',
    borderRadius: '6px',
    cursor: 'pointer',
    fontWeight: 'bold',
  },
  errorBanner: {
    backgroundColor: '#ffebee',
    color: '#c62828',
    padding: '12px',
    borderRadius: '6px',
    marginBottom: '20px',
  },
  tableContainer: {
    backgroundColor: 'white',
    borderRadius: '10px',
    boxShadow: '0 2px 10px rgba(0,0,0,0.05)',
    overflow: 'hidden',
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
    textAlign: 'left',
  },
  tableHeadRow: {
    backgroundColor: '#fafafa',
    borderBottom: '2px solid #eaeaea',
  },
  th: {
    padding: '16px',
    color: '#555',
    fontSize: '14px',
  },
  tableRow: {
    borderBottom: '1px solid #eaeaea',
  },
  td: {
    padding: '16px',
    color: '#333',
    fontSize: '14px',
  },
  statusActive: {
    backgroundColor: '#e8f5e9',
    color: '#2e7d32',
    padding: '6px 12px',
    borderRadius: '20px',
    fontSize: '12px',
    fontWeight: 'bold',
  },
  statusWarning: {
    backgroundColor: '#fff3e0',
    color: '#ef6c00',
    padding: '6px 12px',
    borderRadius: '20px',
    fontSize: '12px',
    fontWeight: 'bold',
  },
  emptyState: {
    textAlign: 'center',
    padding: '40px',
    color: '#888',
  }
};