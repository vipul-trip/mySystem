<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Default values
$mobile = $_POST['mobile'] ?? '';
$lat = $_POST['lat'] ?? '26.912434';
$long = $_POST['long'] ?? '75.787270';
$aadhaar_number = $_POST['aadhaar_number'] ?? '';
// $piddata = $_POST['piddata'] ?? '';

$response = '';
$curlError = '';
$httpCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $piddata = $_POST['pidData'] ?? '';

    if (!$piddata) {
        echo json_encode(['success' => false, 'message' => 'Missing pidData']);
        exit;
    }

    // --- ENCRYPTION ---
    $key = "30a0b6b2349b8b69"; // Replace with actual 16-byte key
    $iv = "e45e8a3c50ad745a"; // Replace with actual 16-byte IV

    $ciphertext_raw = openssl_encrypt($piddata, "AES-128-CBC", $key, OPENSSL_RAW_DATA, $iv);
    $encrypted_piddata = base64_encode($ciphertext_raw);

    // --- API Call ---
    $url = "https://uat.paysprint.in/service-api/api/v1/service/dmt/kyc/remitter/queryremitter/kyc";

    $headers = [
        "Content-Type: application/json",
        "AuthorisedKey: Y2Q4NmE4NDBiZGEwMTczYmNmZDIxNGRhZThjZGNlODk=", // replace with real
        "Token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0aW1lc3RhbXAiOjE2NDk2Nzk1MTksInBhcnRuZXJJZCI6InBzMDAxOTg4IiwicmVxaWQiOiI0NXl1eWpoZ2Z2Y2QifQ.vbWDPTfq2_NTmjLPbD1RG-n57YqurzgCwq1oUfbwmg8"
    ];

    $postData = json_encode([
        "mobile" => $mobile,
        "lat" => $lat,
        "long" => $long,
        "aadhaar_number" => $aadhaar_number,
        "data" => $encrypted_piddata,
        //"accessmode" => "USB", // "Android" or "USB"
        "is_iris" => 2
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // If API called successfully
    if ($httpCode === 200 && !$curlError) {
        $result = json_decode($response, true);

        if (isset($result['status']) && $result['status'] === true) {
            // Extract STATERESP & OTP and store in session
            $_SESSION['STATERESP'] = $result['data']['stateresp'] ?? '';
            $_SESSION['OTP'] = $result['data']['otp'] ?? '';
            $_SESSION['mobile'] = $mobile;

            // Return success response
            echo json_encode(['success' => true, 'message' => 'KYC submitted successfully', 'data' => $result]);
            exit;
            // Redirect to register page
            // header("Location: remitter_register.php");
        //     exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'API Error: ' . ($result['message'] ?? 'Unknown error')]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'cURL Error: ' . $curlError ?: 'HTTP ' . $httpCode]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Remitter E-KYC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .status-message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .fingerprint-container {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            margin: 10px 0;
        }
        .captured-image img {
            max-width: 200px;
            height: auto;
            border: 1px solid #ddd;
            padding: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2>Remitter E-KYC</h2>
        <form id="kycForm" method="POST">
            <div class="mb-3">
                <label class="form-label">Mobile Number</label>
                <input type="text" class="form-control" name="mobile" value="<?= htmlspecialchars($mobile) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Latitude</label>
                <input type="text" class="form-control" name="lat" value="<?= htmlspecialchars($lat) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Longitude</label>
                <input type="text" class="form-control" name="long" value="<?= htmlspecialchars($long) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Aadhaar Number</label>
                <input type="text" class="form-control" name="aadhaar_number" value="<?= htmlspecialchars($aadhaar_number) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Fingerprint</label>
                <div class="fingerprint-container">
                    <h4>Fingerprint Capture System</h4>
                    <div id="deviceStatus" class="status-message" style="display: none;"></div>
                    <div id="fingerprintProgress">
                        <span id="thumbProgress">Status: Not initialized ❌</span>
                    </div>
                    <div class="mt-3">
                        <button type="button" id="testDevice" class="btn btn-info me-2">Test Device</button>
                        <button type="button" id="initialize" class="btn btn-primary me-2">Initialize Device</button>
                        <button type="button" id="capture" class="btn btn-success" disabled>Capture Fingerprint</button>
                    </div>
                </div>
                <div id="capturedImage" class="captured-image"></div>
            </div>
            <input type="hidden" name="pidData" id="pidDataInput">
            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Submit e-KYC</button>
        </form>
    </div>

    <script>
        // Updated URLs for better compatibility
        const rdServiceUrls = [
            'http://127.0.0.1:11100',
            'http://localhost:11100'
        ];
        
        let currentBaseUrl = '';
        let capturedPidData = null;

        // Test device connectivity
        document.getElementById('testDevice').addEventListener('click', async function(e) {
            e.preventDefault();
            setStatus('Testing device connectivity...', 'info');
            
            try {
                const formData = new FormData();
                formData.append('action', 'test_device');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    setStatus('Device test successful! MSF110 Mantra device detected.', 'success');
                    document.getElementById('initialize').disabled = false;
                } else {
                    setStatus('Device test failed: ' + result.error, 'error');
                    showTroubleshootingTips();
                }
            } catch (error) {
                setStatus('Error testing device: ' + error.message, 'error');
                showTroubleshootingTips();
            }
        });

        // Initialize device
        document.getElementById('initialize').addEventListener('click', async function(e) {
            e.preventDefault();
            setStatus('Initializing device...', 'info');
            
            for (const baseUrl of rdServiceUrls) {
                try {
                    const response = await fetch(baseUrl + '/rd/info', {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    
                    if (response.ok) {
                        const data = await response.text();
                        currentBaseUrl = baseUrl;
                        
                        // Parse the response (usually XML for RD services)
                        if (data.includes('MSF110') || data.includes('MANTRA')) {
                            setStatus('MSF110 Mantra device initialized successfully!', 'success');
                            document.getElementById('capture').disabled = false;
                            updateProgress('Device Ready ✅');
                            return;
                        }
                    }
                } catch (error) {
                    console.log(`Failed to connect to ${baseUrl}:`, error);
                }
            }
            
            setStatus('Failed to initialize device. Please check if MSF110 Mantra RD service is running.', 'error');
            showTroubleshootingTips();
        });

        // Capture fingerprint
        document.getElementById('capture').addEventListener('click', async function(e) {
            e.preventDefault();
            setStatus('Capturing fingerprint...', 'info');
            
            try {
                const captureData = {
                    "Quality": 60,
                    "TimeOut": 10,
                    "fCount": 1,
                    "fType": 0,
                    "iCount": 0,
                    "iType": 0,
                    "pCount": 0,
                    "pType": 0,
                    "format": 0,
                    "pidVer": "2.0",
                    "timeout": 10,
                    "otp": "",
                    "env": "P"
                };

                const response = await fetch(currentBaseUrl + '/rd/capture', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(captureData)
                });

                const result = await response.text();
                
                // Parse XML response
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(result, "text/xml");
                
                const errCode = xmlDoc.getElementsByTagName("Resp")[0]?.getAttribute("errCode");
                const errInfo = xmlDoc.getElementsByTagName("Resp")[0]?.getAttribute("errInfo");
                
                if (errCode === "0") {
                    const pidData = xmlDoc.getElementsByTagName("PidData")[0];
                    const bitmapData = xmlDoc.getElementsByTagName("Data")[0]?.textContent;
                    
                    if (pidData && bitmapData) {
                        capturedPidData = new XMLSerializer().serializeToString(pidData);
                        
                        displayImage(`data:image/bmp;base64,${bitmapData}`, capturedPidData);
                        setStatus('Fingerprint captured successfully!', 'success');
                        updateProgress('Fingerprint Captured ✅');
                        
                        // Enable submit button
                        document.getElementById('pidDataInput').value = capturedPidData;
                        document.getElementById('submitBtn').disabled = false;
                    } else {
                        throw new Error('Invalid response format');
                    }
                } else {
                    throw new Error(errInfo || 'Capture failed');
                }
            } catch (error) {
                setStatus('Error capturing fingerprint: ' + error.message, 'error');
                console.error('Full error:', error);
            }
        });

        // Form submission
        document.getElementById('kycForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!capturedPidData) {
                setStatus('Please capture fingerprint first', 'error');
                return;
            }
            
            setStatus('Submitting KYC data...', 'info');
            
            try {
                const formData = new FormData(this);
                formData.append('pidData', capturedPidData);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    setStatus('KYC submitted successfully!', 'success');
                    // You can redirect here if needed
                    // window.location.href = 'remitter_register.php';
                } else {
                    setStatus('KYC submission failed: ' + result.message, 'error');
                }
            } catch (error) {
                setStatus('Error submitting KYC: ' + error.message, 'error');
            }
        });

        function displayImage(imageData, pidData) {
            document.getElementById('capturedImage').innerHTML = `
                <h5>Captured Fingerprint:</h5>
                <img src="${imageData}" alt="Fingerprint" class="img-thumbnail">
                <details>
                    <summary>PID Data (Click to expand)</summary>
                    <pre style="font-size: 12px; max-height: 200px; overflow-y: auto;">${pidData}</pre>
                </details>
            `;
        }

        function setStatus(message, type = 'info') {
            const statusElement = document.getElementById('deviceStatus');
            statusElement.style.display = 'block';
            statusElement.textContent = message;
            statusElement.className = 'status-message status-' + type;
        }

        function updateProgress(message) {
            document.getElementById('thumbProgress').textContent = message;
        }

        function showTroubleshootingTips() {
            const tips = `
                <div class="alert alert-warning mt-3">
                    <h6>Troubleshooting Tips:</h6>
                    <ol>
                        <li>Ensure MSF110 Mantra RD Service is running</li>
                        <li>Check if device is properly connected</li>
                        <li>Try running as administrator</li>
                        <li>Restart the RD service</li>
                        <li>Check Windows firewall settings</li>
                    </ol>
                </div>
            `;
            document.getElementById('deviceStatus').innerHTML += tips;
        }
    </script>
</body>
</html>