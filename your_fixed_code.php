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

            // Return JSON response for AJAX
            echo json_encode(['success' => true, 'message' => 'KYC submitted successfully']);
            exit;
            // Redirect to register page
            // header("Location: remitter_register.php");
        //     exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'API call failed']);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Remitter E-KYC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
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
                <div id="fingerprintProgress">
                    <span id="thumbProgress">Thumb: ❌</span>
                </div>
                <button type="button" id="initialize" class="btn btn-primary me-2">Initialize Device</button>
                <button type="button" id="capture" class="btn btn-success" disabled>Capture Fingerprint</button>
                <div id="status"></div>
                <div id="capturedImage"></div>
            </div>
            <input type="hidden" name="pidData" id="pidDataHidden">
            <button type="submit" class="btn btn-primary" id="submitBtn">Submit e-KYC</button>
        </form>

        <hr>

</body>
</html>
<script>
        // Fixed: Try multiple URLs for better device compatibility
        const baseUrls = [
            'http://127.0.0.1:11100/rd/',
            'http://localhost:11100/rd/',
            'http://127.0.0.1:11100/',
            'http://localhost:11100/',
        ];
        let workingBaseUrl = '';
        let capturedPidData = '';
        
        const fingerprintProgressDisplay = document.getElementById('fingerprintProgress');

        document.getElementById('initialize').addEventListener('click', async function (e) {
          e.preventDefault(); // prevent form submit

            setStatus('Searching for device...');
            
            // Try each URL until one works
            for (const baseUrl of baseUrls) {
                try {
                    const response = await fetch(baseUrl + 'info', {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    
                    if (response.ok) {
                        const data = await response.text(); // MSF110 returns XML, not JSON
                        workingBaseUrl = baseUrl;
                        
                        // Check if it's MSF110 device
                        if (data.includes('MSF110') || data.includes('MANTRA') || data.includes('RDService')) {
                            document.getElementById('capture').disabled = false;
                            setStatus('MSF110 Mantra device initialized successfully.');
                            document.getElementById('thumbProgress').textContent = 'Device Ready: ✅';
                            return;
                        }
                    }
                } catch (error) {
                    console.log(`Failed to connect to ${baseUrl}:`, error);
                    continue;
                }
            }
            
            setStatus('Device not found. Please check connection and ensure RD Service is running as Administrator.', true);
        });

        document.getElementById('capture').addEventListener('click', async function (e) {
             e.preventDefault(); // prevent form submit
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

                const response = await fetch(workingBaseUrl + 'capture', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(captureData)
                });

                const result = await response.text(); // MSF110 returns XML, not JSON

                // Parse XML response for MSF110
                const parser = new DOMParser();
                const xmlDoc = parser.parseFromString(result, "text/xml");
                
                const respElement = xmlDoc.getElementsByTagName("Resp")[0];
                if (respElement) {
                    const errorCode = respElement.getAttribute("errCode");
                    const errorInfo = respElement.getAttribute("errInfo");
                    
                    if (errorCode === "0") {
                        // Success - extract data
                        const pidDataElement = xmlDoc.getElementsByTagName("PidData")[0];
                        const dataElement = xmlDoc.getElementsByTagName("Data")[0];
                        
                        if (pidDataElement && dataElement) {
                            capturedPidData = new XMLSerializer().serializeToString(pidDataElement);
                            const bitmapData = dataElement.textContent;
                            
                            displayImage(`data:image/bmp;base64,${bitmapData}`, capturedPidData);
                            document.getElementById('pidDataHidden').value = capturedPidData;
                            setStatus('Fingerprint captured successfully!');
                            document.getElementById('thumbProgress').textContent = 'Captured: ✅';
                        } else {
                            throw new Error('Invalid response format');
                        }
                    } else {
                        throw new Error(errorInfo || 'Capture failed with error code: ' + errorCode);
                    }
                } else {
                    // Try to handle as JSON for backward compatibility
                    const jsonResult = JSON.parse(result);
                    if (jsonResult.ErrorCode === "0") {
                        displayImage(`data:image/png;base64,${jsonResult.BitmapData}`, JSON.stringify(jsonResult));
                        document.getElementById('pidDataHidden').value = JSON.stringify(jsonResult);
                        setStatus('Fingerprint captured successfully!');
                        document.getElementById('thumbProgress').textContent = 'Captured: ✅';
                    } else {
                        throw new Error(jsonResult.ErrorDescription || 'Capture failed');
                    }
                }
            } catch (error) {
                setStatus('Error: ' + error.message, true);
                console.error('Full error:', error);
            }
        }); 

        // Handle form submission with AJAX
        document.getElementById('kycForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!capturedPidData && !document.getElementById('pidDataHidden').value) {
                setStatus('Please capture fingerprint first', true);
                return;
            }
            
            setStatus('Submitting KYC data...');
            
            try {
                const formData = new FormData(this);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    setStatus('KYC submitted successfully!');
                    // Uncomment below to redirect after success
                    // setTimeout(() => window.location.href = 'remitter_register.php', 2000);
                } else {
                    setStatus('KYC submission failed: ' + result.message, true);
                }
            } catch (error) {
                setStatus('Error submitting KYC: ' + error.message, true);
            }
        });

        function displayImage(imageData, pidData) {
            document.getElementById('capturedImage').innerHTML = `
                <h3>Captured Fingerprint:</h3>
                <img src="${imageData}" alt="Fingerprint" style="max-width: 200px; border: 1px solid #ddd;">
                <p><strong>PID Data:</strong> ${pidData.substring(0, 100)}...</p>`;
        }

        function setStatus(message, isError = false) {
            const statusElement = document.getElementById('status');
            statusElement.textContent = message;
            statusElement.style.color = isError ? 'red' : 'green';
            statusElement.style.fontWeight = 'bold';
            statusElement.style.padding = '10px';
            statusElement.style.marginTop = '10px';
        }
    </script>
</rewritten_file>