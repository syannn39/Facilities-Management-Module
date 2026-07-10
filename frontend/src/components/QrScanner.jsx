import React, { useEffect, useState, useRef } from 'react';
import { Html5QrcodeScanner } from 'html5-qrcode';
import api from '../api';

export default function QrScanner({ bookingId }) {
    const [scanFeedback, setScanFeedback] = useState('');
    const [isSuccess, setIsSuccess] = useState(false);
    const scannerRef = useRef(null);

    useEffect(() => {
        // Safety check: ensure container exists
        const container = document.getElementById("reader-container");
        if (!container) return;

        try {
            scannerRef.current = new Html5QrcodeScanner("reader-container", { 
                fps: 10, 
                qrbox: { width: 250, height: 250 } 
            }, false);

            scannerRef.current.render(
                async (decodedText) => {
                    if (scannerRef.current) {
                        await scannerRef.current.clear().catch(console.error);
                    }
                    
                    setScanFeedback("Processing...");
                    try {
                        const response = await api.post(`/bookings/${bookingId}/check-in`, {
                            qr_data: decodedText
                        });
                        if (response.data.success) {
                            setIsSuccess(true);
                            setScanFeedback(response.data.message || "Success!");
                        }
                    } catch (error) {
                        setIsSuccess(false);
                        setScanFeedback(error.response?.data?.message || "Check-in failed.");
                    }
                },
                (error) => { console.warn(error); }
            );
        } catch (err) {
            console.error("Scanner init failed:", err);
            setScanFeedback("Camera failed to initialize.");
        }

        return () => {
            if (scannerRef.current) {
                scannerRef.current.clear().catch(() => {});
            }
        };
    }, [bookingId]);

    return (
        <div className="scanner-card" style={{ padding: '20px' }}>
            <h3>Scan QR Code</h3>
            <div id="reader-container"></div>
            {scanFeedback && <div style={{ color: isSuccess ? 'green' : 'red' }}>{scanFeedback}</div>}
        </div>
    );
}