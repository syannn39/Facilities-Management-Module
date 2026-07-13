import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import React, { useState, useEffect } from 'react';
import { MoreVertical, X, Printer, RefreshCw, Building2, CheckSquare, BarChart3, Check, XCircle, Eye} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react'; // QR package
import api from '../api';

export default function AdminView() {
  // --- LAYOUT & TABS STATE ---
  const [activeTab, setActiveTab] = useState('facilities');

  // --- FACILITIES STATE ---
  const [facilities, setFacilities] = useState([]);
  const [workflowTiers, setWorkflowTiers] = useState([]);
  const [loadingFacilities, setLoadingFacilities] = useState(true);
  const [error, setError] = useState(null);

  // --- APPROVALS STATE ---
  const [bookingRequests, setBookingRequests] = useState([]);
  const [loadingRequests, setLoadingRequests] = useState(false);

  // --- UI MODAL STATE ---
  const [activeDropdown, setActiveDropdown] = useState(null); 
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [viewingFacility, setViewingFacility] = useState(null);

  // --- REVIEW MODAL STATE ---
  const [viewingRequest, setViewingRequest] = useState(null);
  const [requestRemarks, setRequestRemarks] = useState('');
  
  // --- QR CODE STATE ---
  const [qrFacility, setQrFacility] = useState(null);
  const [qrLoading, setQrLoading] = useState(false);

  const [formData, setFormData] = useState({
    name: '', category: 'Standard', status: 'active', image_url: '',
    workflow_tier_id: '', capacity: '', advance_booking_limit: 30,
    grace_period_minutes: 15 
  });

  // --- Report State ---
  const [reportData, setReportData] = useState([]);
  const [dateFilter, setDateFilter] = useState('30days');
  const [facilityFilter, setFacilityFilter] = useState('all');
  const [reportStats, setReportStats] = useState({ total_requests: 0, cancellation_rate: 0 });

  // --- TAB ROUTING EFFECT ---
  useEffect(() => {
    if (activeTab === 'facilities') {
      fetchFacilities();
      fetchWorkflowTiers();
    } else if (activeTab === 'approvals') {
      fetchBookingRequests();
    } else if (activeTab === 'reports') {
      fetchReportData();
    }
  }, [activeTab]);

  // --- API: FACILITIES ---
  const fetchFacilities = async () => {
    setLoadingFacilities(true);
    try {
      const response = await api.get('/facilities');
      setFacilities(response.data.data || response.data);
      setError(null);
    } catch (err) {
      console.error("Failed to fetch facilities:", err);
      setError("Unable to load facilities.");
    } finally {
      setLoadingFacilities(false);
    }
  };

  const fetchWorkflowTiers = async () => {
    try {
      const response = await api.get('/workflow-tiers');
      setWorkflowTiers(response.data.data || response.data);
    } catch (err) {
      console.error("Failed to fetch workflow tiers:", err);
    }
  };

  // --- API: APPROVALS ---
  const fetchBookingRequests = async () => {
    setLoadingRequests(true);
    try {
      const response = await api.get('/approvals/pending');
      setBookingRequests(response.data.data || response.data);
    } catch (err) {
      console.error("Failed to fetch booking requests.", err);
    } finally {
      setLoadingRequests(false);
    }
  };
  
  // --- API: REPORTS ---
  // 1. HELPER: Get the exact dates based on the dropdown choice
  const getFilterDates = () => {
    const end = new Date();
    const start = new Date();
    if (dateFilter === '30days') {
      start.setDate(end.getDate() - 30);
    } else if (dateFilter === 'year') {
      start.setFullYear(end.getFullYear() - 1);
    }
    return {
      date_from: start.toISOString().split('T')[0],
      date_to: end.toISOString().split('T')[0]
    };
  };

  // 2. DASHBOARD FETCH: Only runs on load or when "Generate Report" is clicked
  const fetchReportData = async () => {
    try {
      const { date_from, date_to } = getFilterDates();
      
      const params = new URLSearchParams({
        date_from: date_from,
        date_to: date_to,
        facility_id: facilityFilter
      });

      const response = await api.get(`/reports/dashboard-metrics?${params.toString()}`);
      
      setReportData(response.data.data.chartData);
      setReportStats(response.data.data.stats);
    } catch (err) {
      console.error("Failed to fetch dashboard metrics:", err);
    }
  };

  // 3. EXPORT FILE: Triggered by the PDF/CSV buttons
  const handleExport = async (format) => {
    try {
      // FIX: Added this line so handleExport knows what dates to send!
      const { date_from, date_to } = getFilterDates(); 

      const payload = {
        report_type: 'Dashboard Metrics',
        date_from: date_from, 
        date_to: date_to,
        format: format
      };

      const response = await api.post('/reports/generate', payload);
      const reportId = response.data.data.id || response.data.data.report_id;

      if (!reportId) throw new Error("No Report ID returned");

      const fileResponse = await api.get(`/reports/${reportId}/${format}`, {
        responseType: 'blob'
      });

      const url = window.URL.createObjectURL(new Blob([fileResponse.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `Facility_Report_${reportId}.${format}`);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);
      
    } catch (err) {
      console.error(`Failed to export ${format}:`, err);
      alert(`Error exporting ${format.toUpperCase()}`);
    }
  };

const handleProcessRequest = async (status) => {
    if (!viewingRequest) return;
    const rId = viewingRequest.request_id || viewingRequest.id;

    if (status === 'Rejected' && !requestRemarks.trim()) {
      alert("Please enter a reason for rejection.");
      return;
    }

    if (window.confirm(`Are you sure you want to ${status.toUpperCase()} this request?`)) {
      try {
        const payload = { remarks: requestRemarks };
        
        if (status === 'Approved') {
          await api.post(`/approvals/${rId}/approve`, payload);
        } else {
          await api.post(`/approvals/${rId}/reject`, payload);
        }
        
        setViewingRequest(null);
        setRequestRemarks('');
        fetchBookingRequests();
      } catch (err) {
        console.error("Failed to process request:", err);
        alert(`Error: ${err.response?.data?.message || "Failed to update request."}`);
      }
    }
  };

  // --- CRUD HANDLERS ---
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
        name: facility.name, category: facility.category || 'Standard', status: facility.status || 'active',
        image_url: facility.image_url || '', 
        // THE FIX: Look inside get_operational_rule for the approval_tier!
        workflow_tier_id: facility.get_operational_rule?.approval_tier || '',
        capacity: facility.get_operational_rule?.max_capacity || '',
        advance_booking_limit: facility.get_operational_rule?.advance_booking_limit || 30,
        grace_period_minutes: facility.get_operational_rule?.grace_period_minutes || 15
      });
    } else {
      setEditingId(null);
      setFormData({ name: '', category: 'Standard', status: 'active', image_url: '', workflow_tier_id: '', capacity: '', advance_booking_limit: 30, grace_period_minutes: 15 });
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
      // 1. Define the unifiedPayload FIRST so the API can use it
      const unifiedPayload = {
        name: formData.name,
        category: formData.category,
        status: formData.status,
        image_url: formData.image_url,
      };

      let facilityIdToUse = editingId;

      // 2. CREATE or UPDATE the Facility FIRST to guarantee we have an ID
      if (editingId) {
        await api.put(`/facilities/${editingId}`, unifiedPayload);
      } else {
        const response = await api.post('/facilities', unifiedPayload);
        
        facilityIdToUse = 
          response.data?.data?.facility_id || 
          response.data?.data?.id || 
          response.data?.facility_id || 
          response.data?.id;

        // Safety check
        if (!facilityIdToUse) {
          console.error("Backend response:", response);
          alert("Warning: Facility created, but we couldn't read the new ID from Laravel. Rules were skipped!");
          return; // Stop here so it doesn't crash the next steps
        }
      }

      // 3. - build the rulePayload using the guaranteed facilityIdToUse
      const rulesPayload = {
        facility_id: facilityIdToUse,
        max_capacity: formData.capacity,
        opening_time: '08:00:00', // adjust as needed
        closing_time: '22:00:00',
        advance_booking_limit: formData.advance_booking_limit,
        approval_tier: formData.workflow_tier_id,
        grace_period_minutes: formData.grace_period_minutes,
        ...unifiedPayload // Spread the facility data in case your governance route needs it
      };

      // 4. Save the rules
      try {
        await api.post('/governance/rules', rulesPayload);
      } catch (ruleErr) {
        console.warn("Governance route skipped or failed, but facility saved.", ruleErr);
      }

      // 5. Clean up and refresh
      closeModal();
      if (typeof fetchFacilities === 'function') {
        fetchFacilities(); 
      }
      
    } catch (err) {
      console.error("Failed to save:", err.response?.data || err);
      alert(`Backend Error: ${JSON.stringify(err.response?.data?.errors || err.response?.data?.message || "Unknown error")}`);
    }
  };

  const handleDelete = async (id) => {
    setActiveDropdown(null);
    if (window.confirm("Are you sure you want to delete this facility?")) {
      try {
        await api.delete(`/facilities/${id}`);
        fetchFacilities();
      } catch (err) {
        alert("Error deleting facility.");
      }
    }
  };

  // --- QR LOGIC (SY's Addition) ---
  const requestQrCode = async (facility, confirm = false) => {
    const rowId = facility.id || facility.facility_id;
    setQrLoading(true);
    try {
      const response = await api.post(`/facilities/${rowId}/qr-code`, confirm ? { confirm: true } : {});
      const body = response.data;
      if (body.requires_confirmation) {
        setQrFacility({ ...facility, ...body.data });
        return;
      }
      const updated = { ...facility, ...body.data };
      setQrFacility(updated);
      setFacilities(prev => prev.map(f => (f.id || f.facility_id) === rowId ? { ...f, ...body.data } : f));
    } catch (err) {
      alert("Error generating QR code.");
    } finally {
      setQrLoading(false);
    }
  };

  const openQrModal = (facility) => {
    setActiveDropdown(null);
    if (facility.qr_code_token) {
      setQrFacility(facility);
    } else {
      requestQrCode(facility, false);
    }
  };

  const handleRegenerateQr = (facility) => {
    if (window.confirm("This facility already has a QR code. Regenerating will invalidate the current code. Continue?")) {
      requestQrCode(facility, true);
    }
  };

  const qrValueFor = (token) => token;

  const handlePrintQr = (facility) => {
    const token = facility.qr_code_token;
    if (!token) return;
    const printWindow = window.open('', '_blank', 'width=420,height=560');
    if (!printWindow) return;
    const container = document.getElementById(`qr-print-source-${token}`);
    const svgMarkup = container ? container.innerHTML : '';
    printWindow.document.write(`
      <html>
        <head>
          <title>${facility.name} — Check-in QR Code</title>
          <style>
            body { font-family: system-ui, Arial, sans-serif; text-align: center; padding: 40px 20px; }
            h1 { font-size: 20px; margin-bottom: 4px; }
            p { color: #555; margin-top: 0; }
            .qr-wrap { margin: 24px auto; display: inline-block; }
          </style>
        </head>
        <body>
          <h1>${facility.name}</h1>
          <p>Scan to check in</p>
          <div class="qr-wrap">${svgMarkup}</div>
        </body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
  };

  // --- HELPERS ---
  const formatTime = (timeStr) => {
    if (!timeStr) return 'N/A';
    const [hour, minute] = timeStr.split(':');
    const h = parseInt(hour, 10);
    const ampm = h >= 12 ? 'PM' : 'AM';
    return `${h % 12 || 12}:${minute} ${ampm}`;
  };

  const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  return (
    <div style={styles.appWrapper}>
      
      {/* SIDEBAR NAVIGATION */}
      <div style={styles.sidebar}>
        <div style={styles.sidebarHeader}>
          <h2 style={{ color: '#1a73e8', margin: 0, fontSize: '20px' }}>PropertyHub</h2>
        </div>
        <nav style={styles.navMenu}>
          <button style={activeTab === 'facilities' ? styles.navItemActive : styles.navItem} onClick={() => setActiveTab('facilities')}>
            <Building2 size={18} style={styles.navIcon} /> Facilities
          </button>
          <button style={activeTab === 'approvals' ? styles.navItemActive : styles.navItem} onClick={() => setActiveTab('approvals')}>
            <CheckSquare size={18} style={styles.navIcon} /> Approvals
          </button>
          <button style={activeTab === 'reports' ? styles.navItemActive : styles.navItem} onClick={() => setActiveTab('reports')}>
            <BarChart3 size={18} style={styles.navIcon} /> Reports
          </button>
        </nav>
      </div>

      {/* MAIN CONTENT AREA */}
      <div style={styles.mainContent}>
        
        {/* --- VIEW: FACILITIES --- */}
        {activeTab === 'facilities' && (
          <div style={styles.pageContainer}>
            <div style={styles.header}>
              <h2 style={styles.title}>Manage Facilities</h2>
              <button style={styles.addButton} onClick={() => openModal()}>+ Add New Facility</button>
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
                  {loadingFacilities ? (
                    <tr><td colSpan="8" style={styles.emptyState}>Loading facilities...</td></tr>
                  ) : facilities.length === 0 ? (
                    <tr><td colSpan="8" style={styles.emptyState}>No facilities found.</td></tr>
                  ) : (
                    facilities.map((facility) => {
                      const rowId = facility.id || facility.facility_id; 
                      return (
                        <tr key={rowId} style={styles.tableRow}>
                          <td style={{...styles.td, fontWeight: 'bold', color: '#1a73e8', cursor: 'pointer'}} onClick={() => setViewingFacility(facility)}>
                            {facility.name}
                          </td>
                          <td style={styles.td}>
                            {facility.image_url ? (
                              <img src={facility.image_url} alt="Facility" style={{ width: '40px', height: '40px', borderRadius: '6px', objectFit: 'cover' }} />
                            ) : <span style={{ color: '#aaa', fontSize: '12px' }}>No Image</span>}
                          </td>
                          <td style={{...styles.td, textTransform: 'capitalize'}}>{facility.category || 'Standard'}</td>
                          <td style={styles.td}>{facility.get_operational_rule?.max_capacity || 'N/A'}</td>
                          <td style={styles.td}>{facility.get_operational_rule?.advance_booking_limit ? `${facility.get_operational_rule.advance_booking_limit} days` : 'Not set'}</td>
                          <td style={styles.td}><span style={styles.tierBadge}>{(() => {// Look up the exact tier based on the nested approval_tier ID
                                const tierId = facility.get_operational_rule?.approval_tier;
                                if (!tierId || tierId === 0) return 'Auto-Approve (Tier 0)';
                                
                                const matchedTier = workflowTiers.find(t => (t.id || t.tier_id) === tierId);
                                return matchedTier ? `Tier ${matchedTier.tier_level} (${matchedTier.assigned_role})` : `Tier ${tierId}`;
                              })()}
                            </span>
                          </td>
                          <td style={styles.td}><span style={facility.status === 'active' ? styles.statusActive : styles.statusWarning}>{facility.status || 'Active'}</span></td>
                          
                          <td style={{...styles.td, position: 'relative', textAlign: 'center'}}>
                            <button style={styles.iconButton} onClick={() => setActiveDropdown(activeDropdown === rowId ? null : rowId)}>
                              <MoreVertical size={20} />
                            </button>
                            {activeDropdown === rowId && (
                              <div style={styles.dropdownMenu}>
                                <button style={styles.dropdownItem} onClick={() => openModal(facility)}>Edit</button>
                                {/* SY's QR Button Integration */}
                                <button style={styles.dropdownItem} onClick={() => openQrModal(facility)}>
                                  {facility.qr_code_token ? 'View / Print QR' : 'Generate QR Code'}
                                </button>
                                <button style={{...styles.dropdownItem, color: '#c62828'}} onClick={() => handleDelete(rowId)}>Delete</button>
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
            
            {/* FACILITY DETAIL MODAL */}
            {viewingFacility && (
              <div style={styles.modalOverlay}>
                <div style={styles.detailModalContent}>
                  <div style={styles.detailHeader}>
                    <h2 style={{ margin: 0 }}>{viewingFacility.name}</h2>
                    <button style={styles.iconButton} onClick={() => setViewingFacility(null)}><X size={24} /></button>
                  </div>
                  <div style={styles.detailBody}>
                    <div style={styles.detailCard}>
                      <div style={styles.cardHeader}>
                        <h4 style={{ margin: 0 }}>Facility Information</h4>
                        <span style={viewingFacility.status === 'active' ? styles.statusActive : styles.statusWarning}>{viewingFacility.status}</span>
                      </div>
                      <div style={styles.grid2Col}>
                        <div><div style={styles.detailLabel}>Facility Type</div><div style={styles.detailValue}>{viewingFacility.category || 'Standard'}</div></div>
                        <div><div style={styles.detailLabel}>Maximum Capacity</div><div style={styles.detailValue}>{viewingFacility.get_operational_rule?.max_capacity || 'N/A'} people</div></div>
                        <div><div style={styles.detailLabel}>Advance Booking Limit</div><div style={styles.detailValue}>{viewingFacility.get_operational_rule?.advance_booking_limit || 0} days</div></div>
                        <div>
                          <div style={styles.detailLabel}>Approval Requirement</div>
                          <div style={styles.detailValue}>
                            {(() => {
                              const tierId = viewingFacility.get_operational_rule?.approval_tier;
                              if (!tierId || tierId === 0) return 'Instant Booking';
                              const matchedTier = workflowTiers.find(t => (t.id || t.tier_id) === tierId);
                              return matchedTier ? `Tier ${matchedTier.tier_level} Approval` : `Tier ${tierId} Approval`;
                            })()}
                          </div>
                        </div>
                      </div>
                    </div>
                    <div style={styles.detailCard}>
                      <h4 style={{ margin: '0 0 16px 0' }}>Operating Hours</h4>
                      <div style={styles.flexBetween}>
                        <span style={styles.detailValue}>Standard Hours</span>
                        <span style={{ fontWeight: 'bold', color: '#333' }}>{formatTime(viewingFacility.get_operational_rule?.opening_time)} - {formatTime(viewingFacility.get_operational_rule?.closing_time)}</span>
                      </div>
                    </div>
                    <div style={styles.detailCard}>
                      <h4 style={{ margin: '0 0 16px 0' }}>Usage Statistics</h4>
                      <div style={styles.statsGrid}>
                        <div style={styles.statBox}><div style={{ fontSize: '24px', fontWeight: 'bold', color: '#1a73e8' }}>{viewingFacility.bookings_count || 0}</div><div style={styles.detailLabel}>Total Bookings</div></div>
                        <div style={styles.statBox}><div style={{ fontSize: '24px', fontWeight: 'bold', color: '#2e7d32' }}>{viewingFacility.bookings_count > 0 ? 'Active' : 'N/A'}</div><div style={styles.detailLabel}>Utilization Rate</div></div>
                        <div style={styles.statBox}><div style={{ fontSize: '24px', fontWeight: 'bold', color: '#ef6c00' }}>{viewingFacility.pending_requests_count || 0}</div><div style={styles.detailLabel}>Pending Requests</div></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* QR CODE MODAL */}
            {qrFacility && (
              <div style={styles.modalOverlay}>
                <div style={styles.modalContent}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h3 style={{ margin: 0 }}>{qrFacility.name}</h3>
                    <button style={styles.iconButton} onClick={() => setQrFacility(null)}><X size={20} /></button>
                  </div>
                  <p style={{ color: '#888', fontSize: '13px', margin: '4px 0 20px' }}>Tenants scan this to check in.</p>
                  <div style={{ textAlign: 'center' }}>
                    {qrLoading ? (
                      <div style={{ padding: '60px 0', color: '#888' }}>Generating…</div>
                    ) : qrFacility.qr_code_token ? (
                      <>
                        <div id={`qr-print-source-${qrFacility.qr_code_token}`} style={{ display: 'inline-block', padding: '16px', border: '1px solid #eaeaea', borderRadius: '8px' }}>
                          <QRCodeSVG value={qrValueFor(qrFacility.qr_code_token)} size={220} />
                        </div>
                        {qrFacility.qr_code_generated_at && (
                          <div style={{ fontSize: '12px', color: '#aaa', marginTop: '10px' }}>
                            Generated {new Date(qrFacility.qr_code_generated_at).toLocaleString()}
                          </div>
                        )}
                      </>
                    ) : (
                      <div style={{ padding: '60px 0', color: '#888' }}>No QR code yet.</div>
                    )}
                  </div>
                  <div style={{ ...styles.modalActions, justifyContent: 'space-between', marginTop: '24px' }}>
                    <button style={{ ...styles.cancelButton, display: 'flex', alignItems: 'center', gap: '6px' }} onClick={() => handleRegenerateQr(qrFacility)} disabled={qrLoading}>
                      <RefreshCw size={14} /> Regenerate
                    </button>
                    <button style={{ ...styles.saveButton, display: 'flex', alignItems: 'center', gap: '6px' }} onClick={() => handlePrintQr(qrFacility)} disabled={qrLoading || !qrFacility.qr_code_token}>
                      <Printer size={14} /> Print
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* CREATE/EDIT MODAL */}
            {isModalOpen && (
              <div style={styles.modalOverlay}>
                <div style={styles.modalContent}>
                  <h3 style={{ marginTop: 0 }}>{editingId ? 'Edit Facility' : 'Create New Facility'}</h3>
                  <form onSubmit={handleSubmit} style={styles.form}>
                    <div style={styles.inputGroup}><label style={styles.label}>Facility Name</label><input required type="text" name="name" value={formData.name} onChange={handleInputChange} style={styles.input} /></div>
                    <div style={styles.inputGroup}><label style={styles.label}>Category</label><input required type="text" name="category" value={formData.category} onChange={handleInputChange} style={styles.input} /></div>
                    <div style={styles.inputGroup}><label style={styles.label}>Max Capacity</label><input required type="number" name="capacity" value={formData.capacity} onChange={handleInputChange} style={styles.input} /></div>
                    <div style={styles.inputGroup}><label style={styles.label}>Advance Booking Limit (Days)</label><input required type="number" name="advance_booking_limit" value={formData.advance_booking_limit} onChange={handleInputChange} style={styles.input} /></div>
                    <div style={styles.inputGroup}><label style={styles.label}>Grace Period (Minutes)</label><input required type="number" name="grace_period_minutes" value={formData.grace_period_minutes} onChange={handleInputChange} style={styles.input} /></div>
                    <div style={styles.inputGroup}><label style={styles.label}>Image URL</label><input type="text" name="image_url" value={formData.image_url} onChange={handleInputChange} style={styles.input} /></div>
                    <div style={styles.inputGroup}>
                      <label style={styles.label}>Approval Tier</label>
                      <select name="workflow_tier_id" value={formData.workflow_tier_id} onChange={handleInputChange} style={styles.input}>
                        <option value="">Auto-Approve (Tier 0)</option>
                        {workflowTiers.map(tier => {
                          const tId = tier.tier_id || tier.id;
                          return (
                            <option key={tId} value={tId}>
                              Tier {tier.tier_level} ({tier.assigned_role} Approval)
                            </option>
                          );
                        })}
                      </select>
                    </div>
                    <div style={styles.inputGroup}>
                      <label style={styles.label}>Status</label>
                      <select name="status" value={formData.status} onChange={handleInputChange} style={styles.input}>
                        <option value="active">Active</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="inactive">Inactive</option>
                      </select>
                    </div>
                    <div style={styles.modalActions}>
                      <button type="button" onClick={closeModal} style={styles.cancelButton}>Cancel</button>
                      <button type="submit" style={styles.saveButton}>Save</button>
                    </div>
                  </form>
                </div>
              </div>
            )}
          </div>
        )}

        {/* --- VIEW: APPROVALS --- */}
        {activeTab === 'approvals' && (
          <div style={styles.pageContainer}>
            <div style={styles.header}>
              <h2 style={styles.title}>Pending Approvals</h2>
            </div>
            <div style={styles.tableContainer}>
              <table style={styles.table}>
                <thead>
                  <tr style={styles.tableHeadRow}>
                    <th style={styles.th}>Request ID</th>
                    <th style={styles.th}>Resident</th>
                    <th style={styles.th}>Facility</th>
                    <th style={styles.th}>Purpose</th>
                    <th style={styles.th}>Guests</th>
                    <th style={styles.th}>Requested Slot</th>
                    <th style={styles.th}>Status</th>
                    <th style={{...styles.th, textAlign: 'center'}}>Decision</th>
                  </tr>
                </thead>
                <tbody>
                  {loadingRequests ? (
                    <tr><td colSpan="8" style={styles.emptyState}>Loading requests...</td></tr>
                  ) : bookingRequests.length === 0 ? (
                    <tr><td colSpan="8" style={styles.emptyState}>No pending requests. All caught up! 🎉</td></tr>
                  ) : (
                    bookingRequests.map((request) => {
                      const rId = request.request_id || request.id;
                      const requestUser = request.get_user || request.user;
                      const requestFacility = request.get_facility || request.facility;

                      return (
                        <tr key={rId} style={styles.tableRow}>
                        {/* 1. Request ID */}
                        <td style={{...styles.td, fontWeight: 'bold', color: '#555'}}>#{rId}</td>
                        
                        {/* 2. Resident */}
                        <td style={styles.td}>{requestUser?.name || 'Unknown Resident'}</td>
                        
                        {/* 3. Facility */}
                        <td style={{...styles.td, fontWeight: 'bold'}}>{requestFacility?.name || 'Unknown Facility'}</td>
                        
                        {/* 4. Purpose */}
                        <td style={{...styles.td, fontStyle: 'italic', color: '#666', fontSize: '13px'}}>
                          {request.purpose_of_use || '-'}
                        </td>

                        {/* 5. Guests */}
                        <td style={{...styles.td, textAlign: 'center'}}>
                          {request.guest_count || 0}
                        </td>

                        {/* 6. Requested Slot (This was missing/shifted!) */}
                        <td style={styles.td}>
                          {formatDateTime(request.start_time)} <br/>
                          <span style={{color: '#888', fontSize: '12px'}}>to {formatDateTime(request.end_time)}</span>
                        </td>
                        
                        {/* 7. Status */}
                        <td style={styles.td}>
                          <span style={request.status === 'Pending' ? styles.statusWarning : request.status === 'Approved' ? styles.statusActive : styles.statusRejected}>
                            {request.status}
                          </span>
                        </td>
                        
                        {/* 8. Decision (Button) */}
                        <td style={{...styles.td, textAlign: 'center'}}>
                          {request.status === 'Pending' || request.status === 'pending' ? (
                            <button style={styles.reviewBtn} 
                              onClick={() => {
                                setViewingRequest(request);
                                setRequestRemarks('');
                              }}
                            >
                              <Eye size={16} /> Review
                            </button>
                          ) : (
                            <span style={{ color: '#aaa', fontSize: '13px' }}>Actioned</span>
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
        )}

        {/* --- REVIEW REQUEST MODAL --- */}
        {viewingRequest && (
          <div style={styles.modalOverlay}>
            <div style={styles.modalContent}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h3 style={{ margin: 0 }}>Review Booking Request</h3>
                <button style={styles.iconButton} onClick={() => setViewingRequest(null)}><X size={20} /></button>
              </div>
              
              <div style={{ backgroundColor: '#f8f9fa', padding: '16px', borderRadius: '8px', marginBottom: '20px', border: '1px solid #eaeaea' }}>
                <div style={styles.flexBetween}>
                  <span style={styles.detailLabel}>Resident</span>
                  <span style={styles.detailValue}>{viewingRequest.get_user?.name || viewingRequest.user?.name}</span>
                </div>
                <div style={styles.flexBetween}>
                  <span style={styles.detailLabel}>Facility</span>
                  <span style={{...styles.detailValue, fontWeight: 'bold'}}>{viewingRequest.get_facility?.name || viewingRequest.facility?.name}</span>
                </div>
                <div style={styles.flexBetween}>
                  <span style={styles.detailLabel}>Start Time</span>
                  <span style={styles.detailValue}>{formatDateTime(viewingRequest.start_time)}</span>
                </div>
                <div style={styles.flexBetween}>
                  <span style={styles.detailLabel}>End Time</span>
                  <span style={styles.detailValue}>{formatDateTime(viewingRequest.end_time)}</span>
                </div>
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                <span style={{ color: '#666', fontSize: '14px' }}>Purpose</span>
                <span style={{ fontWeight: '500', fontSize: '14px', textAlign: 'right', maxWidth: '60%' }}>
                  {viewingRequest.purpose_of_use || 'No purpose provided'}
                </span>
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '16px' }}>
                <span style={{ color: '#666', fontSize: '14px' }}>Guest Count</span>
                <span style={{ 
                  fontWeight: '500', 
                  fontSize: '14px',
                  /* Optional: Turn text red if it exceeds capacity */
                  color: viewingRequest.guest_count > (viewingRequest.facility?.max_capacity || 999) ? 'red' : 'inherit'
                }}>
                  {viewingRequest.guest_count || 0} 
                  <span style={{ color: '#999', fontSize: '12px', marginLeft: '4px' }}>
                    (Max: {viewingRequest.facility?.get_operational_rule?.max_capacity || 'N/A'})
                  </span>
                </span>
              </div>

              <div style={styles.inputGroup}>
                <label style={styles.label}>Remarks / Reason <span style={{color: '#ef6c00', fontWeight: 'normal'}}>(Required for rejection)</span></label>
                <textarea 
                  rows="3" 
                  style={{...styles.input, resize: 'vertical'}} 
                  placeholder="Enter any notes for the resident..."
                  value={requestRemarks}
                  onChange={(e) => setRequestRemarks(e.target.value)}
                />
              </div>

              <div style={{ ...styles.modalActions, marginTop: '24px' }}>
                <button 
                  style={styles.cancelActionBtn} 
                  onClick={() => handleProcessRequest('Rejected')}
                >
                  <XCircle size={16} /> Reject Request
                </button>
                <button 
                  style={styles.confirmActionBtn} 
                  onClick={() => handleProcessRequest('Approved')}
                >
                  <Check size={16} /> Approve Request
                </button>
              </div>
            </div>
          </div>
        )}

        {/* --- VIEW: REPORTS --- */}
        {activeTab === 'reports' && (
          <div style={styles.pageContainer}>
            {/* Header & Controls */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
              <h2 style={styles.title}>Facility Usage Reports</h2>
              <div style={{ display: 'flex', gap: '10px' }}></div>
            </div>

            {/* Filter Bar */}
            <div style={{ display: 'flex', gap: '16px', marginBottom: '24px', backgroundColor: '#fff', padding: '16px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
              
              {/* Facility Filter */}
              <select 
                value={facilityFilter} 
                onChange={(e) => setFacilityFilter(e.target.value)} 
                style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ccc', flex: 1 }}
              >
                <option value="all">All Facilities</option>
                
                {/* Fixed: using facility_id to ensure a number is sent to Laravel */}
                {facilities && facilities.map((facility) => (
                  <option 
                    key={facility.facility_id || facility.id} 
                    value={facility.facility_id || facility.id}
                  >
                    {facility.name}
                  </option>
                ))}
              </select>

              {/* Date Filter */}
              <select 
                value={dateFilter} 
                onChange={(e) => setDateFilter(e.target.value)} 
                style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ccc', flex: 1 }}
              >
                <option value="30days">Last 30 Days</option>
                <option value="year">This Year</option>
              </select>

              <button onClick={() => fetchReportData()}
                  style={{...styles.reviewBtn, backgroundColor: '#333', color: 'white'}}>
                  Generate Report
                </button>
                
                {/* Wire up the PDF button */}
                <button 
                  onClick={() => handleExport('pdf')} 
                  style={{...styles.reviewBtn, backgroundColor: '#f3f4f6', color: '#333', border: '1px solid #ddd'}}
                >
                  ⬇ Export PDF
                </button>
                
                {/* Wire up the CSV button */}
                <button 
                  onClick={() => handleExport('csv')} 
                  style={{...styles.reviewBtn, backgroundColor: '#f3f4f6', color: '#333', border: '1px solid #ddd'}}
                >
                  ⬇ Export CSV
                </button>
              
            </div>

            {/* KPI Cards */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '20px', marginBottom: '24px' }}>
              {/* Card 1: Total Bookings */}
              <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                <p style={{ margin: 0, color: '#666', fontSize: '14px' }}>Total Booking Requests</p>
                {/* Dynamically pull total_requests, default to 0 if undefined */}
                <h3 style={{ margin: '8px 0', fontSize: '32px', color: '#111' }}>{reportStats?.total_requests || 0}</h3>
                <p style={{ margin: 0, color: '#9ca3af', fontSize: '12px' }}>Lifetime requests</p>
              </div>
              
              {/* Card 2: Cancellation Rate */}
              <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                <p style={{ margin: 0, color: '#666', fontSize: '14px' }}>Rejection & Cancellation Rate</p>
                <h3 style={{ margin: '8px 0', fontSize: '32px', color: '#111' }}>{reportStats?.cancellation_rate || 0}%</h3>
                <p style={{ margin: 0, color: '#9ca3af', fontSize: '12px' }}>Of total requests</p>
              </div>
              
              {/* Card 3: Rejected / Cancelled */}
              <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                <p style={{ margin: 0, color: '#666', fontSize: '14px' }}>Rejected / Cancelled</p>
                <h3 style={{ margin: '8px 0', fontSize: '32px', color: '#111' }}>{reportStats?.rejected_cancelled || 0}</h3>
                <p style={{ margin: 0, color: '#9ca3af', fontSize: '12px' }}>Total failed requests</p>
              </div>
            </div>

            {/* Chart Section */}
            <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
              <h4 style={{ margin: '0 0 20px 0', color: '#333' }}>Booking Frequency by Facility</h4>
              <div style={{ width: '100%', height: 350 }}>
                {reportData && reportData.length > 0 ? (
                  <ResponsiveContainer>
                    <BarChart data={reportData}> {/* 1. Connect the real data here! */}
                      <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#eee" />
                      <XAxis dataKey="name" tick={{fill: '#666'}} axisLine={{stroke: '#ccc'}} tickLine={false} />
                      <YAxis tick={{fill: '#666'}} axisLine={false} tickLine={false} />
                      <Tooltip 
                        cursor={{fill: '#f9fafb'}}
                        contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 6px rgba(0,0,0,0.1)' }}
                      />
                      {/* 2. Apply the color directly to the Bar so it dynamically scales */}
                      <Bar dataKey="bookings" fill="#3b82f6" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  /*<div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100%', color: '#666' }}>
                    Loading chart data...
                  </div>*/
                  <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
                  No booking data found for the selected filters.
                </div>
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// Native Inline Styles merged
const styles = {
  appWrapper: { display: 'flex', minHeight: 'calc(100vh - 64px)', backgroundColor: '#f4f5f7', fontFamily: 'system-ui, Arial, sans-serif' },
  sidebar: { width: '250px', backgroundColor: 'white', borderRight: '1px solid #eaeaea', display: 'flex', flexDirection: 'column' },
  sidebarHeader: { padding: '24px', borderBottom: '1px solid #eaeaea' },
  navMenu: { display: 'flex', flexDirection: 'column', padding: '16px 12px', gap: '8px' },
  navItem: { display: 'flex', alignItems: 'center', gap: '12px', padding: '12px 16px', border: 'none', background: 'transparent', cursor: 'pointer', borderRadius: '8px', fontSize: '15px', color: '#555', fontWeight: '500', textAlign: 'left', transition: 'all 0.2s' },
  navItemActive: { display: 'flex', alignItems: 'center', gap: '12px', padding: '12px 16px', border: 'none', background: '#e8f0fe', cursor: 'pointer', borderRadius: '8px', fontSize: '15px', color: '#1a73e8', fontWeight: 'bold', textAlign: 'left' },
  navIcon: { opacity: 0.8 },
  mainContent: { flexGrow: 1, padding: '40px', overflowY: 'auto' },
  
  pageContainer: { maxWidth: '1200px', margin: '0 auto' },
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
  statusRejected: { backgroundColor: '#ffebee', color: '#c62828', padding: '6px 12px', borderRadius: '20px', fontSize: '12px', fontWeight: 'bold', textTransform: 'capitalize' },
  tierBadge: { border: '1px solid #eaeaea', backgroundColor: '#f9f9f9', color: '#555', padding: '4px 10px', borderRadius: '6px', fontSize: '12px', fontWeight: 'bold' },
  emptyState: { textAlign: 'center', padding: '40px', color: '#888' },
  
  iconButton: { background: 'transparent', border: 'none', cursor: 'pointer', padding: '4px', color: '#555', borderRadius: '4px' },
  dropdownMenu: { position: 'absolute', right: '40px', top: '50%', backgroundColor: 'white', border: '1px solid #eaeaea', borderRadius: '6px', boxShadow: '0 4px 12px rgba(0,0,0,0.1)', zIndex: 10, display: 'flex', flexDirection: 'column', minWidth: '130px', overflow: 'hidden' },
  dropdownItem: { padding: '10px 16px', border: 'none', background: 'transparent', textAlign: 'left', cursor: 'pointer', fontSize: '14px', borderBottom: '1px solid #f5f5f5', fontWeight: '600', color: '#333' },
  
  approveBtn: { display: 'flex', alignItems: 'center', gap: '4px', backgroundColor: '#e8f5e9', color: '#2e7d32', border: '1px solid #c8e6c9', padding: '6px 12px', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold', fontSize: '13px' },
  rejectBtn: { display: 'flex', alignItems: 'center', gap: '4px', backgroundColor: '#ffebee', color: '#c62828', border: '1px solid #ffcdd2', padding: '6px 12px', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold', fontSize: '13px' },

  modalOverlay: { position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 },
  modalContent: { backgroundColor: 'white', padding: '30px', borderRadius: '12px', width: '400px', boxShadow: '0 10px 25px rgba(0,0,0,0.2)', maxHeight: '90vh', overflowY: 'auto' },
  
  detailModalContent: { backgroundColor: '#f4f5f7', borderRadius: '12px', width: '800px', maxWidth: '90vw', maxHeight: '90vh', overflowY: 'auto', boxShadow: '0 10px 25px rgba(0,0,0,0.2)' },
  detailHeader: { backgroundColor: 'white', padding: '24px 32px', borderBottom: '1px solid #eaeaea', display: 'flex', justifyContent: 'space-between', alignItems: 'center', position: 'sticky', top: 0, zIndex: 10 },
  detailBody: { padding: '24px 32px', display: 'flex', flexDirection: 'column', gap: '20px' },
  detailCard: { backgroundColor: 'white', padding: '24px', borderRadius: '8px', border: '1px solid #eaeaea' },
  cardHeader: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' },
  grid2Col: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' },
  flexBetween: { display: 'flex', justifyContent: 'space-between', marginBottom: '12px' },
  detailLabel: { fontSize: '12px', color: '#888', marginBottom: '4px' },
  detailValue: { fontSize: '14px', color: '#333', fontWeight: '500' },
  statsGrid: { display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '16px', textAlign: 'center' },
  statBox: { padding: '16px', backgroundColor: '#fafafa', borderRadius: '8px', border: '1px solid #eaeaea' },

  form: { display: 'flex', flexDirection: 'column', gap: '16px' },
  inputGroup: { display: 'flex', flexDirection: 'column', gap: '6px' },
  label: { fontSize: '13px', fontWeight: 'bold', color: '#555' },
  input: { padding: '10px', borderRadius: '6px', border: '1px solid #ccc', fontSize: '14px', backgroundColor: '#fff' },
  modalActions: { display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '10px' },
  cancelButton: { padding: '10px 16px', backgroundColor: '#f5f5f5', border: '1px solid #ddd', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold', color: '#555' },
  saveButton: { padding: '10px 16px', backgroundColor: '#1a73e8', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold' },

  // Add these to your styles object
  reviewBtn: { padding: '6px 16px', backgroundColor: 'white', border: '1px solid #d1d5db', borderRadius: '6px', cursor: 'pointer', fontWeight: '600', color: '#374151', display: 'flex', alignItems: 'center', gap: '8px', margin: '0 auto', transition: 'all 0.2s', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' },
  confirmActionBtn: { padding: '10px 16px', backgroundColor: '#1a73e8', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '6px' },
  cancelActionBtn: { padding: '10px 16px', backgroundColor: 'white', color: '#dc2626', border: '1px solid #fca5a5', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '6px' },
};