import React, { useEffect, useState, useRef } from 'react';
import { Html5QrcodeScanner } from 'html5-qrcode';
import api from '../api';

/**
 * Grabs the device's current GPS position, wrapped in a Promise so it can
 * be awaited alongside the QR decode flow. Resolves to { lat, lng } on
 * success, or null on ANY failure (permission denied, no GPS hardware,
 * timeout, non-HTTPS context, etc) — GPS is a best-effort enhancement
 * here, never a hard requirement to check in. The backend
 * (CheckInService::processQrCheckIn) already treats a missing lat/lng as
 * "skip the distance check", so returning null just means this scan
 * falls back to QR token + arrival window only, exactly like before this
 * feature existed.
 */
function getCurrentPosition(timeoutMs = 8000) {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            resolve(null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                });
            },
            () => resolve(null), // denied / unavailable / any error — fall back silently
            { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 0 }
        );
    });
}

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
                setScanFeedback("Getting your location...");

                try {
                    if (scannerRef.current) await scannerRef.current.clear().catch(() => { });

                    // Best-effort location grab — never blocks or fails the
                    // check-in itself, see getCurrentPosition() above.
                    const position = await getCurrentPosition();

                    setScanFeedback("Processing...");

                    const response = await api.post(`/bookings/${bookingId}/check-in`, {
                        qr_data: decodedText,
                        lat: position?.lat ?? null,
                        lng: position?.lng ?? null,
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
