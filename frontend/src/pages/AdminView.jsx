import React, { useState, useEffect } from 'react';
import { MoreVertical } from 'lucide-react';
import api from '../api';

export default function AdminView() {
  const [facilities, setFacilities] = useState([]);
  const [workflowTiers, setWorkflowTiers] = useState([]); // NEW: State to hold the tiers
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [activeDropdown, setActiveDropdown] = useState(null); 
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  
  const [formData, setFormData] = useState({
    name: '',
    category: 'Standard', 
    capacity: '',
    status: 'active',
    image_url: '',
    workflow_tier_id: '', 
    advance_booking_limit: 30
  });

  useEffect(() => {
    // Fetch BOTH facilities and tiers when the page loads
    fetchFacilities();
    fetchWorkflowTiers();
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

  // NEW: Function to pull the approval tiers for the dropdown
  const fetchWorkflowTiers = async () => {
    try {
      const response = await api.get('/workflow-tiers');
      setWorkflowTiers(response.data.data || response.data);
    } catch (err) {
      console.error("Failed to fetch workflow tiers. Check your API route.", err);
    }
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

 const openModal = (facility = null) => {
    setActiveDropdown(null);
    if (facility) {
      const rowId = facility.id || facility.facility_id;
      setEditingId(rowId);
      setFormData({
        name: facility.name,
        category: facility.category || 'Standard',
        capacity: facility.get_operational_rule?.max_capacity || '',
        status: facility.status || 'active',
        image_url: facility.image_url || '',
        workflow_tier_id: facility.workflow_tier_id || '',
        // NEW: Check for the exact column name 'advance_booking_limit'
        advance_booking_limit: facility.get_operational_rule?.advance_booking_limit || 30
      });
    } else {
      setEditingId(null);
      setFormData({ name: '', category: 'Standard', capacity: '', status: 'active', image_url: '', workflow_tier_id: '', advance_booking_limit: 30 });
    }
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    setEditingId(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      // Ensure empty strings are sent as NULL to Laravel for the foreign key
      const payload = {
        ...formData,
        workflow_tier_id: formData.workflow_tier_id === '' ? null : formData.workflow_tier_id
      };

      if (editingId) {
        await api.put(`/facilities/${editingId}`, payload);
      } else {
        await api.post('/facilities', payload);
      }
      closeModal();
      fetchFacilities();
    } catch (err) {
      console.error("Failed to save facility:", err);
      alert("Error saving facility. Check the console.");
    }
  };

  const handleDelete = async (id) => {
    setActiveDropdown(null);
    if (window.confirm("Are you sure you want to delete this facility?")) {
      try {
        await api.delete(`/facilities/${id}`);
        fetchFacilities();
      } catch (err) {
        console.error("Failed to delete:", err);
        alert("Error deleting facility.");
      }
    }
  };

  return (
    <div style={styles.pageContainer}>
      <div style={styles.content}>
        
        <div style={styles.header}>
          <h2 style={styles.title}>Manage Facilities</h2>
          <button style={styles.addButton} onClick={() => openModal()}>
            + Add New Facility
          </button>
        </div>

        {error && <div style={styles.errorBanner}>{error}</div>}

        <div style={styles.tableContainer}>
          <table style={styles.table}>
            <thead>
              <tr style={styles.tableHeadRow}>
                <th style={styles.th}>Facility Name</th>
                <th style={styles.th}>Image</th>
                <th style={styles.th}>Category</th>
                <th style={styles.th}>Capacity</th>
                <th style={styles.th}>Advance Booking Limit</th>
                <th style={styles.th}>Approval Tier</th>
                <th style={styles.th}>Status</th>
                <th style={{...styles.th, textAlign: 'center'}}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="8" style={styles.emptyState}>Loading database records...</td>
                </tr>
              ) : facilities.length === 0 ? (
                <tr>
                  <td colSpan="8" style={styles.emptyState}>No facilities found.</td>
                </tr>
              ) : (
                facilities.map((facility) => {
                  const rowId = facility.id || facility.facility_id; 

                  return (
                    <tr key={rowId} style={styles.tableRow}>
                      <td style={{...styles.td, fontWeight: 'bold'}}>{facility.name}</td>
                      
                      <td style={styles.td}>
                        {facility.image_url ? (
                          <img src={facility.image_url} alt="Facility" style={{ width: '40px', height: '40px', borderRadius: '6px', objectFit: 'cover' }} />
                        ) : (
                          <span style={{ color: '#aaa', fontSize: '12px' }}>No Image</span>
                        )}
                      </td>
                      
                      <td style={{...styles.td, textTransform: 'capitalize'}}>{facility.category || 'Standard'}</td>
                      <td style={styles.td}>{facility.get_operational_rule?.max_capacity || 'N/A'}</td>

                      <td style={styles.td}>
                        {facility.get_operational_rule?.advance_booking_limit 
                          ? `${facility.get_operational_rule.advance_booking_limit} days` 
                          : 'Not set'}
                      </td>

                      <td style={styles.td}>
                        <span style={styles.tierBadge}>
                          {facility.workflow_tier?.name || 'Auto-Approve (Tier 0)'}
                        </span>
                      </td>

                      <td style={styles.td}>
                        <span style={facility.status === 'active' ? styles.statusActive : styles.statusWarning}>
                          {facility.status || 'Active'}
                        </span>
                      </td>
                      
                      <td style={{...styles.td, position: 'relative', textAlign: 'center'}}>
                        <button 
                          style={styles.iconButton}
                          onClick={() => setActiveDropdown(activeDropdown === rowId ? null : rowId)}
                        >
                          <MoreVertical size={20} />
                        </button>
                        
                        {activeDropdown === rowId && (
                          <div style={styles.dropdownMenu}>
                            <button style={styles.dropdownItem} onClick={() => openModal(facility)}>
                              Edit
                            </button>
                            <button style={{...styles.dropdownItem, color: '#c62828'}} onClick={() => handleDelete(rowId)}>
                              Delete
                            </button>
                          </div>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

      </div>

      {isModalOpen && (
        <div style={styles.modalOverlay}>
          <div style={styles.modalContent}>
            <h3 style={{ marginTop: 0, color: '#1a1a2e' }}>
              {editingId ? 'Edit Facility' : 'Create New Facility'}
            </h3>
            <form onSubmit={handleSubmit} style={styles.form}>
              
              <div style={styles.inputGroup}>
                <label style={styles.label}>Facility Name</label>
                <input required type="text" name="name" value={formData.name} onChange={handleInputChange} style={styles.input} />
              </div>

              <div style={styles.inputGroup}>
                <label style={styles.label}>Category</label>
                <input required type="text" name="category" value={formData.category} onChange={handleInputChange} style={styles.input} />
              </div>

              <div style={styles.inputGroup}>
                <label style={styles.label}>Max Capacity</label>
                <input required type="number" name="capacity" value={formData.capacity} onChange={handleInputChange} style={styles.input} />
              </div>

              <div style={styles.inputGroup}>
                <label style={styles.label}>Advance Booking Limit (Days)</label>
                <input required type="number" name="advance_booking_limit" value={formData.advance_booking_limit} onChange={handleInputChange} style={styles.input} />
              </div>

              <div style={styles.inputGroup}>
                <label style={styles.label}>Image URL</label>
                <input type="text" name="image_url" value={formData.image_url} onChange={handleInputChange} style={styles.input} placeholder="https://example.com/image.jpg" />
              </div>

              {/* NEW: Workflow Tier Dropdown */}
              <div style={styles.inputGroup}>
                <label style={styles.label}>Approval Tier</label>
                <select name="workflow_tier_id" value={formData.workflow_tier_id} onChange={handleInputChange} style={styles.input}>
                  <option value="">Auto-Approve (Tier 0)</option>
                  {workflowTiers.map(tier => (
                    <option key={tier.id} value={tier.id}>
                      {tier.name}
                    </option>
                  ))}
                </select>
              </div>

              <div style={styles.inputGroup}>
                <label style={styles.label}>Status</label>
                <select name="status" value={formData.status} onChange={handleInputChange} style={styles.input}>
                  <option value="active">Active</option>
                  <option value="maintenance">Under Maintenance</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>

              <div style={styles.modalActions}>
                <button type="button" onClick={closeModal} style={styles.cancelButton}>Cancel</button>
                <button type="submit" style={styles.saveButton}>Save Facility</button>
              </div>

            </form>
          </div>
        </div>
      )}
    </div>
  );
}

// Native Inline Styles
const styles = {
  pageContainer: { minHeight: 'calc(100vh - 64px)', backgroundColor: '#f4f5f7', padding: '40px 20px', fontFamily: 'system-ui, Arial, sans-serif' },
  content: { maxWidth: '1100px', margin: '0 auto' },
  header: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' },
  title: { margin: 0, fontSize: '24px', color: '#1a1a2e' },
  addButton: { backgroundColor: '#1a1a2e', color: 'white', border: 'none', padding: '10px 16px', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold' },
  errorBanner: { backgroundColor: '#ffebee', color: '#c62828', padding: '12px', borderRadius: '6px', marginBottom: '20px' },
  
  tableContainer: { backgroundColor: 'white', borderRadius: '10px', boxShadow: '0 2px 10px rgba(0,0,0,0.05)', overflow: 'visible' },
  table: { width: '100%', borderCollapse: 'collapse', textAlign: 'left' },
  tableHeadRow: { backgroundColor: '#fafafa', borderBottom: '2px solid #eaeaea' },
  th: { padding: '16px', color: '#555', fontSize: '14px' },
  tableRow: { borderBottom: '1px solid #eaeaea' },
  td: { padding: '16px', color: '#333', fontSize: '14px' },
  
  statusActive: { backgroundColor: '#e8f5e9', color: '#2e7d32', padding: '6px 12px', borderRadius: '20px', fontSize: '12px', fontWeight: 'bold', textTransform: 'capitalize' },
  statusWarning: { backgroundColor: '#fff3e0', color: '#ef6c00', padding: '6px 12px', borderRadius: '20px', fontSize: '12px', fontWeight: 'bold', textTransform: 'capitalize' },
  tierBadge: { border: '1px solid #eaeaea', backgroundColor: '#f9f9f9', color: '#555', padding: '4px 10px', borderRadius: '6px', fontSize: '12px', fontWeight: 'bold' },
  emptyState: { textAlign: 'center', padding: '40px', color: '#888' },
  
  iconButton: { background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', color: '#555', borderRadius: '4px' },
  dropdownMenu: { position: 'absolute', right: '40px', top: '50%', backgroundColor: 'white', border: '1px solid #eaeaea', borderRadius: '6px', boxShadow: '0 4px 12px rgba(0,0,0,0.1)', zIndex: 10, display: 'flex', flexDirection: 'column', minWidth: '100px', overflow: 'hidden' },
  dropdownItem: { padding: '10px 16px', border: 'none', background: 'transparent', textAlign: 'left', cursor: 'pointer', fontSize: '14px', borderBottom: '1px solid #f5f5f5', fontWeight: '600', color: '#333' },
  
  modalOverlay: { position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 },
  modalContent: { backgroundColor: 'white', padding: '30px', borderRadius: '12px', width: '400px', boxShadow: '0 10px 25px rgba(0,0,0,0.2)', maxHeight: '90vh', overflowY: 'auto' },
  form: { display: 'flex', flexDirection: 'column', gap: '16px' },
  inputGroup: { display: 'flex', flexDirection: 'column', gap: '6px' },
  label: { fontSize: '13px', fontWeight: 'bold', color: '#555' },
  input: { padding: '10px', borderRadius: '6px', border: '1px solid #ccc', fontSize: '14px', backgroundColor: '#fff' },
  modalActions: { display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '10px' },
  cancelButton: { padding: '10px 16px', backgroundColor: '#f5f5f5', border: '1px solid #ddd', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold', color: '#555' },
  saveButton: { padding: '10px 16px', backgroundColor: '#1a73e8', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold' }
};