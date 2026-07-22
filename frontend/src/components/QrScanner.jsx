import React, { useEffect, useState, useRef } from 'react';
import { Html5QrcodeScanner } from 'html5-qrcode';
import api from '../api';

export default function QrScanner({ bookingId }) {
    const [scanFeedback, setScanFeedback] = useState('');
    const [isSuccess, setIsSuccess] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);
    const scannerRef = useRef(null);

    useEffect(() => {
        const container = document.getElementById("reader-container");
        if (!container) return;

        scannerRef.current = new Html5QrcodeScanner("reader-container", {
            fps: 10,
            qrbox: { width: 300, height: 300 }
        }, false);

        scannerRef.current.render(
            async (decodedText) => {
                if (isProcessing) return;

                setIsProcessing(true);
                setScanFeedback("Processing...");

                try {
                    if (scannerRef.current) await scannerRef.current.clear().catch(() => { });

                    const response = await api.post(`/bookings/${bookingId}/check-in`, {
                        qr_data: decodedText
                    });

                    if (response.data.success) {
                        setIsSuccess(true);
                        setScanFeedback(response.data.message || "Check-in successful!");
                    }
                } catch (error) {
                    setIsSuccess(false);
                    const msg = error.response?.data?.message || "Check-in failed.";
                    setScanFeedback(`Failed: ${msg}`);
                } finally {
                    setIsProcessing(false);
                }
            },
            (error) => {
                if (typeof error === 'string' && error.includes('NotFoundException')) return;
            }
        );

        return () => {
            if (scannerRef.current) scannerRef.current.clear().catch(() => { });
        };
    }, [bookingId, isProcessing]);

    return (
        <div className="scanner-card" style={{ padding: '20px', textAlign: 'center' }}>
            <h3>Scan QR Code</h3>
            <div id="reader-container" style={{ width: '100%' }}></div>
            {scanFeedback && (
                <div style={{
                    padding: '10px', marginTop: '10px', fontWeight: 'bold',
                    color: isSuccess ? '#1e7e34' : '#bd2130',
                    background: isSuccess ? '#e6ffed' : '#fff0f1'
                }}>
                    {scanFeedback}
                </div>
            )}
        </div>
    );
}