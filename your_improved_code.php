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

            // Return JSON for AJAX
            echo json_encode(['success' => true, 'message' => 'KYC submitted successfully']);
            exit;
            // Redirect to register page
            // header("Location: remitter_register.php");
        //     exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'API failed: ' . ($result['message'] ?? 'Unknown error')]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . ($curlError ?: 'HTTP ' . $httpCode)]);
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
        .device-status {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .status-success { border-left: 4px solid #28a745; }
        .status-error { border-left: 4px solid #dc3545; }
        .status-warning { border-left: 4px solid #ffc107; }
        .status-info { border-left: 4px solid #17a2b8; }
        
        .diagnostic-panel {
            background: #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2>Remitter E-KYC</h2>
        <form method="POST" id="kycForm">
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
                  <h2>Fingerprint Capture System</h2>
                
                <!-- Diagnostic Panel -->
                <div class="device-status status-info" id="deviceStatusPanel">
                    <h6>📊 Device Status</h6>
                    <div id="diagnosticInfo">Click "Test Connection" to check device status</div>
                </div>
                
                <div id="fingerprintProgress">
                    <span id="thumbProgress">Thumb: ❌</span>
                </div>
                
                <div class="btn-group" role="group">
                    <button type="button" id="testConnection" class="btn btn-info">Test Connection</button>
                    <button type="button" id="initialize" class="btn btn-primary" disabled>Initialize Device</button>
                    <button type="button" id="capture" class="btn btn-success" disabled>Capture Fingerprint</button>
                </div>
                
                <div id="status"></div>
                <div id="capturedImage"></div>
                
                <!-- Advanced Diagnostic Panel -->
                <details class="mt-3">
                    <summary>🔧 Advanced Diagnostics</summary>
                    <div class="diagnostic-panel" id="diagnosticPanel">
                        Diagnostic information will appear here...
                    </div>
                </details>
            </div>
            
            <input type="hidden" name="pidData" id="pidDataHidden">
            <button type="submit" class="btn btn-primary" id="submitBtn">Submit e-KYC</button>
        </form>

        <hr>

</body>
</html>
<script>
        // Enhanced: Multiple URLs with detailed testing
        const deviceUrls = [
            'http://127.0.0.1:11100/rd/',
            'http://localhost:11100/rd/',
            'http://127.0.0.1:11100/',
            'http://localhost:11100/',
            'http://127.0.0.1:11101/rd/',
            'http://127.0.0.1:8005/rd/',
        ];
        
        let workingBaseUrl = '';
        let capturedPidData = '';
        let deviceConnected = false;
        let diagnosticLog = [];
        
        const fingerprintProgressDisplay = document.getElementById('fingerprintProgress');

        function addDiagnostic(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `[${timestamp}] ${message}`;
            diagnosticLog.push(logEntry);
            
            // Update diagnostic panel
            const panel = document.getElementById('diagnosticPanel');
            panel.textContent = diagnosticLog.join('\n');
            panel.scrollTop = panel.scrollHeight;
            
            console.log(logEntry);
        }

        function updateDeviceStatus(message, type = 'info') {
            const panel = document.getElementById('deviceStatusPanel');
            const info = document.getElementById('diagnosticInfo');
            
            panel.className = `device-status status-${type}`;
            info.textContent = message;
            
            addDiagnostic(message, type);
        }

        document.getElementById('testConnection').addEventListener('click', async function (e) {
            e.preventDefault();
            
            updateDeviceStatus('🔍 Testing device connections...', 'info');
            addDiagnostic('Starting comprehensive device test...');
            
            let testResults = [];
            let foundWorking = false;
            
            for (const baseUrl of deviceUrls) {
                try {
                    addDiagnostic(`Testing: ${baseUrl}info`);
                    
                    const startTime = Date.now();
                    const response = await fetch(baseUrl + 'info', {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    const responseTime = Date.now() - startTime;
                    
                    if (response.ok) {
                        const data = await response.text();
                        workingBaseUrl = baseUrl;
                        foundWorking = true;
                        
                        // Check device type
                        let deviceType = 'Unknown';
                        if (data.includes('MSF110')) {
                            deviceType = 'MSF110 Mantra';
                            deviceConnected = true;
                        } else if (data.includes('MANTRA')) {
                            deviceType = 'Mantra Device';
                            deviceConnected = true;
                        } else if (data.includes('RDService')) {
                            deviceType = 'RD Service';
                        }
                        
                        testResults.push(`✅ ${baseUrl} - ${deviceType} (${responseTime}ms)`);
                        addDiagnostic(`✅ SUCCESS: ${baseUrl} - ${deviceType} detected`);
                        addDiagnostic(`Response time: ${responseTime}ms, Data length: ${data.length} bytes`);
                        
                        // Stop on first success
                        break;
                    } else {
                        testResults.push(`❌ ${baseUrl} - HTTP ${response.status}`);
                        addDiagnostic(`❌ FAILED: ${baseUrl} - HTTP ${response.status}`);
                    }
                } catch (error) {
                    testResults.push(`❌ ${baseUrl} - ${error.message}`);
                    addDiagnostic(`❌ ERROR: ${baseUrl} - ${error.message}`);
                }
            }
            
            if (foundWorking) {
                updateDeviceStatus(`✅ Device Connected: ${deviceConnected ? 'MSF110 Ready' : 'Service Ready'}`, 'success');
                document.getElementById('initialize').disabled = false;
                document.getElementById('thumbProgress').textContent = 'Connection: ✅';
                addDiagnostic(`Device ready for initialization on ${workingBaseUrl}`);
            } else {
                updateDeviceStatus('❌ No device found - Check connection & restart RD Service', 'error');
                addDiagnostic('TROUBLESHOOTING NEEDED:');
                addDiagnostic('1. Ensure MSF110 device is connected via USB');
                addDiagnostic('2. Restart MantraRDService.exe as Administrator');
                addDiagnostic('3. Check Windows Device Manager for device');
                addDiagnostic('4. Verify firewall is not blocking port 11100');
            }
        });

        document.getElementById('initialize').addEventListener('click', async function (e) {
            e.preventDefault();

            if (!workingBaseUrl) {
                updateDeviceStatus('❌ Please test connection first', 'error');
                return;
            }
            
            updateDeviceStatus('🔄 Initializing device...', 'info');
            addDiagnostic('Attempting device initialization...');
            
            try {
                const response = await fetch(workingBaseUrl + 'info', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                if (response.ok) {
                    const data = await response.text();
                    
                    addDiagnostic('Device info retrieved successfully');
                    addDiagnostic(`Response preview: ${data.substring(0, 100)}...`);
                    
                    document.getElementById('capture').disabled = false;
                    updateDeviceStatus('✅ Device initialized - Ready for capture', 'success');
                    document.getElementById('thumbProgress').textContent = 'Ready: ✅';
                } else {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                updateDeviceStatus(`❌ Initialization failed: ${error.message}`, 'error');
                addDiagnostic(`Initialization error: ${error.message}`);
            }
        });

        document.getElementById('capture').addEventListener('click', async function (e) {
            e.preventDefault();
            
            if (!workingBaseUrl) {
                updateDeviceStatus('❌ Device not initialized', 'error');
                return;
            }
            
            updateDeviceStatus('👆 Capturing fingerprint... Please place finger on scanner', 'warning');
            addDiagnostic('Starting fingerprint capture...');
            
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

                addDiagnostic('Sending capture request...');
                const response = await fetch(workingBaseUrl + 'capture', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(captureData)
                });

                const result = await response.text();
                addDiagnostic(`Capture response received (${result.length} bytes)`);

                // Parse XML response for MSF110
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(result, "text/xml");
                
                const respElement = xmlDoc.getElementsByTagName("Resp")[0];
                if (respElement) {
                    const errorCode = respElement.getAttribute("errCode");
                    const errorInfo = respElement.getAttribute("errInfo");
                    
                    addDiagnostic(`XML Response - Code: ${errorCode}, Info: ${errorInfo}`);
                    
                    if (errorCode === "0") {
                        // Success - extract data
                        const pidDataElement = xmlDoc.getElementsByTagName("PidData")[0];
                        const dataElement = xmlDoc.getElementsByTagName("Data")[0];
                        
                        if (pidDataElement && dataElement) {
                            capturedPidData = new XMLSerializer().serializeToString(pidDataElement);
                            const bitmapData = dataElement.textContent;
                            
                            displayImage(`data:image/bmp;base64,${bitmapData}`, capturedPidData);
                            document.getElementById('pidDataHidden').value = capturedPidData;
                            updateDeviceStatus('✅ Fingerprint captured successfully!', 'success');
                            document.getElementById('thumbProgress').textContent = 'Captured: ✅';
                            addDiagnostic('Fingerprint capture successful!');
                            addDiagnostic(`PID data length: ${capturedPidData.length} characters`);
                        } else {
                            throw new Error('Invalid XML response format');
                        }
                    } else {
                        throw new Error(errorInfo || 'Capture failed with error code: ' + errorCode);
                    }
                } else {
                    // Try JSON fallback for compatibility
                    try {
                        const jsonResult = JSON.parse(result);
                        if (jsonResult.ErrorCode === "0") {
                            displayImage(`data:image/png;base64,${jsonResult.BitmapData}`, JSON.stringify(jsonResult));
                            document.getElementById('pidDataHidden').value = JSON.stringify(jsonResult);
                            updateDeviceStatus('✅ Fingerprint captured successfully!', 'success');
                            document.getElementById('thumbProgress').textContent = 'Captured: ✅';
                            addDiagnostic('Fingerprint capture successful (JSON format)!');
                        } else {
                            throw new Error(jsonResult.ErrorDescription || 'Capture failed');
                        }
                    } catch (jsonError) {
                        addDiagnostic('Response is neither valid XML nor JSON');
                        addDiagnostic(`Raw response: ${result.substring(0, 200)}...`);
                        throw new Error('Unexpected response format');
                    }
                }
            } catch (error) {
                updateDeviceStatus(`❌ Capture failed: ${error.message}`, 'error');
                addDiagnostic(`Capture error: ${error.message}`);
                console.error('Full capture error:', error);
            }
        }); 

        // Enhanced form submission
        document.getElementById('kycForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!capturedPidData && !document.getElementById('pidDataHidden').value) {
                updateDeviceStatus('❌ Please capture fingerprint first', 'error');
                return;
            }
            
            updateDeviceStatus('📤 Submitting KYC data...', 'info');
            addDiagnostic('Starting KYC submission...');
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    updateDeviceStatus('✅ KYC submitted successfully!', 'success');
                    addDiagnostic('KYC submission successful!');
                    // Uncomment to redirect
                    // setTimeout(() => window.location.href = 'remitter_register.php', 2000);
                } else {
                    updateDeviceStatus(`❌ KYC failed: ${result.message}`, 'error');
                    addDiagnostic(`KYC submission failed: ${result.message}`);
                }
            } catch (error) {
                updateDeviceStatus(`❌ Submission error: ${error.message}`, 'error');
                addDiagnostic(`Submission error: ${error.message}`);
            }
        });

        function displayImage(imageData, pidData) {
            document.getElementById('capturedImage').innerHTML = `
                <h3>Captured Fingerprint:</h3>
                <img src="${imageData}" alt="Fingerprint" style="max-width: 200px; border: 1px solid #ddd;">
                <p><strong>PID Data Length:</strong> ${pidData.length} characters</p>`;
        }

        function setStatus(message, isError = false) {
            const statusElement = document.getElementById('status');
            statusElement.textContent = message;
            statusElement.style.color = isError ? 'red' : 'green';
            statusElement.style.fontWeight = 'bold';
            statusElement.style.padding = '10px';
            statusElement.style.marginTop = '10px';
        }

        // Auto-run connection test on page load
        window.addEventListener('load', () => {
            addDiagnostic('Page loaded - Ready for device testing');
            // Auto-test connection after 2 seconds
            setTimeout(() => {
                document.getElementById('testConnection').click();
            }, 2000);
        });
    </script>
</rewritten_file>