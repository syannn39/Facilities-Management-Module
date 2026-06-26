import React, { useEffect, useState } from 'react';
import { Html5QrcodeScanner } from 'html5-qrcode';
import api from '../api';

export default function QrScanner({ bookingId }) {
    const [scanFeedback, setScanFeedback] = useState('');
    const [isSuccess, setIsSuccess] = useState(false);

    useEffect(() => {
        // Initializes the local camera interface container
        const scanner = new Html5QrcodeScanner("reader-container", { 
            fps: 10, 
            qrbox: { width: 250, height: 250 } 
        }, false);

        scanner.render(
            async (decodedText) => {
                // Clear camera handle immediately upon reading text code to prevent double-firing queries
                scanner.clear();
                setScanFeedback("Processing scanned token data...");

                try {
                    const response = await api.post(`/bookings/${bookingId}/check-in`, {
                        qr_data: decodedText
                    });

                    if (response.data.success) {
                        setIsSuccess(true);
                        setScanFeedback(response.data.message || "Check-in verified successfully!");
                    }
                } catch (error) {
                    setIsSuccess(false);
                    if (error.response && error.response.data) {
                        setScanFeedback(`Verification Failure: ${error.response.data.message}`);
                    } else {
                        setScanFeedback("Check-in communication failed. Check network routing.");
                    }
                }
            },
            (error) => {
                // Suppresses noisy raw frame capture logs from flooding developer console logs
                console.warn(error);
            }
        );

        // Cleanup function to cleanly shut down camera feed if user leaves page view early
        return () => {
            scanner.clear().catch(err => console.error("Scanner failed to stop on unmount.", err));
        };
    }, [bookingId]);

    return (
        <div className="scanner-card" style={{ padding: '20px', maxWidth: '500px', margin: 'auto', textAlign: 'center', fontFamily: 'Arial' }}>
            <h3 style={{ color: '#333' }}>Scan Physical Facility Entrance QR Code</h3>
            <p style={{ color: '#666', fontSize: '14px' }}>Please align the facility badge code inside the window markers below.</p>
            
            <div id="reader-container" style={{ width: '100%', borderRadius: '8px', overflow: 'hidden', margin: '15px auto' }}></div>
            
            {scanFeedback && (
                <div style={{
                    padding: '12px',
                    borderRadius: '4px',
                    fontWeight: 'bold',
                    fontSize: '15px',
                    background: isSuccess ? '#e6ffed' : '#fff0f1',
                    color: isSuccess ? '#1e7e34' : '#bd2130',
                    border: isSuccess ? '1px solid #c3e6cb' : '1px solid #f5c6cb'
                }}>
                    {scanFeedback}
                </div>
            )}
        </div>
    );
}