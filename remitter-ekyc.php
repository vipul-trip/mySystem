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

            // Redirect to register page
            // header("Location: remitter_register.php");
        //     exit;
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
        <form method="POST">
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
            <button type="submit" class="btn btn-primary">Submit e-KYC</button>
        </form>

        <hr>

</body>
</html>
<script>
        const   baseUrl = 'http://localhost:11100/rd/';
        const fingerprintProgressDisplay = document.getElementById('fingerprintProgress');

        document.getElementById('initialize').addEventListener('click',async function (e) {
          e.preventDefault(); // prevent form submit

            setStatus('Searching for device...');
            try {
                const response = await fetch(baseUrl + 'info', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await response.json();
                if (data) {
                    document.getElementById('capture').disabled = false;
                    setStatus('Device initialized successfully.');
                }
            } catch (error) {
                setStatus('Device not found. Please check connection.', true);
            }
        });

        document.getElementById('capture').addEventListener('click', async function (e) {
             e.preventDefault(); // prevent form submit
            try {
                const captureData = {
                    "Quality": 60,
                    "TimeOut": 10,
                };

                const response = await fetch(baseUrl + 'capture', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(captureData)
                });

                const result = await response.json();

                if (result.ErrorCode === "0") {
                    displayImage(`data:image/png;base64,${result.BitmapData}`, JSON.stringify(result));

                    const formData = new FormData();
                    formData.append('pidData', JSON.stringify(result));

                    formData.append('imageData', `data:image/png;base64,${result.BitmapData}`);

                    const saveResponse = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const saveResult = await saveResponse.json();
                    if (saveResult.success) {
                        // Update progress display
                            setStatus('Fingerprints captured successfully!');
                    } else {
                        throw new Error(saveResult.message || 'Failed to save PID data');
                    }
                } else {
                    throw new Error(result.ErrorDescription || 'Capture failed');
                }
            } catch (error) {
                setStatus('Error: ' + error.message, true);
                console.error('Full error:', error);
            }
        }); 

        function displayImage(imageData, pidData) {
            document.getElementById('capturedImage').innerHTML = `
                <h3>Captured Fingerprint:</h3>
                <img src="${imageData}" alt="Fingerprint">
                <p><strong>PID Data:</strong> ${pidData}</p>`;
        }

        function setStatus(message, isError = false) {
            const statusElement = document.getElementById('status');
            statusElement.textContent = message;
            statusElement.style.color = isError ? 'red' : 'green';
        }
    </script>

