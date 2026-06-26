import React, { useEffect, useState } from 'react';
import api from '../api';

export default function BookingForm({ facilityId }) {
    const [formData, setFormData] = useState({
        startTime: '',
        endTime: '',
        purpose: '',
        guests: 0
    });
    const [statusMessage, setStatusMessage] = useState('');
    const [isError, setIsError] = useState(false);

    // Reset the form whenever the selected facility changes (e.g. user picks
    // a different facility from Browse Facilities) so stale values from a
    // previous facility don't carry over into a new request.
    useEffect(() => {
        setFormData({ startTime: '', endTime: '', purpose: '', guests: 0 });
        setStatusMessage('');
        setIsError(false);
    }, [facilityId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setStatusMessage('');
        setIsError(false);

        try {
            const response = await api.post('/bookings', {
                facility_id: facilityId,
                start_time: formData.startTime,
                end_time: formData.endTime,
                purpose_of_use: formData.purpose,
                guest_count: parseInt(formData.guests, 10)
            });

            if (response.data.success) {
                setStatusMessage(response.data.message || "Booking processed successfully!");
                // Clear form inputs on success
                setFormData({ startTime: '', endTime: '', purpose: '', guests: 0 });
            }
        } catch (error) {
            setIsError(true);
            if (error.response && error.response.data) {
                // Displays specific validation or scheduling conflict errors directly from the service layer
                setStatusMessage(error.response.data.message);
            } else {
                setStatusMessage("An unexpected network error occurred. Please try again.");
            }
        }
    };

    return (
        <div className="booking-container" style={{ padding: '20px', maxWidth: '450px', margin: 'auto', fontFamily: 'Arial, sans-serif' }}>
            <h3 style={{ color: '#333' }}>Reserve Facility Asset</h3>
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                
                <label style={{ fontWeight: 'bold', fontSize: '14px' }}>Start Date & Time</label>
                <input 
                    type="datetime-local" 
                    value={formData.startTime}
                    onChange={e => setFormData({...formData, startTime: e.target.value})} 
                    style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ccc' }} 
                    required 
                />

                <label style={{ fontWeight: 'bold', fontSize: '14px' }}>End Date & Time</label>
                <input 
                    type="datetime-local" 
                    value={formData.endTime}
                    onChange={e => setFormData({...formData, endTime: e.target.value})} 
                    style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ccc' }} 
                    required 
                />

                <label style={{ fontWeight: 'bold', fontSize: '14px' }}>Purpose of Use</label>
                <textarea 
                    placeholder="Describe event/purpose..." 
                    value={formData.purpose}
                    onChange={e => setFormData({...formData, purpose: e.target.value})} 
                    style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ccc', minHeight: '60px' }} 
                />

                <label style={{ fontWeight: 'bold', fontSize: '14px' }}>Guest Count</label>
                <input 
                    type="number" 
                    min="0"
                    value={formData.guests}
                    onChange={e => setFormData({...formData, guests: e.target.value})} 
                    style={{ padding: '8px', borderRadius: '4px', border: '1px solid #ccc' }} 
                />

                <button 
                    type="submit" 
                    style={{ padding: '10px', background: '#0066cc', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer', fontWeight: 'bold' }}
                >
                    Confirm Booking Request
                </button>
            </form>

            {statusMessage && (
                <div style={{ 
                    marginTop: '15px', 
                    padding: '10px', 
                    borderRadius: '4px', 
                    background: isError ? '#ffe6e6' : '#e6f7ff', 
                    color: isError ? '#cc0000' : '#0066cc',
                    fontWeight: '500',
                    fontSize: '14px'
                }}>
                    {statusMessage}
                </div>
            )}
        </div>
    );
}