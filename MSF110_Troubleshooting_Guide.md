# MSF110 Mantra Fingerprint Device Troubleshooting Guide

## Common Issues and Solutions

### 1. **RD Service Not Responding**
Your `netstat` shows the service is listening, but it's not responding to HTTP requests.

**Solutions:**
- Restart the MSF110 RD Service as Administrator
- Check if the correct version of RD Service is installed
- Verify device drivers are properly installed

### 2. **Port 11100 Access Issues**

**Check Service Status:**
```cmd
netstat -ano | findstr :11100
tasklist | findstr "MantraRDService"
```

**Test Service Response:**
```cmd
curl -v http://127.0.0.1:11100/rd/info
# or
curl -v http://127.0.0.1:11100/info
```

### 3. **Browser CORS/Security Issues**

Modern browsers block HTTP requests from HTTPS pages to localhost. Solutions:

**For Chrome:**
- Start Chrome with: `chrome.exe --disable-web-security --allow-running-insecure-content --user-data-dir="C:\temp\chrome_dev"`

**For Firefox:**
- Go to `about:config`
- Set `security.tls.insecure_fallback_hosts` to `localhost,127.0.0.1`

### 4. **MSF110 Specific Configuration**

**Required Files:**
- `MantraRDService.exe` (should be running)
- Device drivers for MSF110
- Proper device configuration

**Registry Check:**
Verify these registry entries exist:
```
HKEY_LOCAL_MACHINE\SOFTWARE\MANTRA\RDService
```

### 5. **Alternative Port Testing**

Some MSF110 devices use different ports:
- Port 11100 (default)
- Port 11101
- Port 8005

**Test all ports:**
```cmd
netstat -ano | findstr :11101
netstat -ano | findstr :8005
```

### 6. **Service Installation Steps**

1. Install MSF110 device drivers
2. Install Mantra RD Service
3. Connect the device
4. Start service as Administrator
5. Test with provided test application

### 7. **XML Response Format**

MSF110 typically returns XML in this format:
```xml
<PidData>
  <Resp errCode="0" errInfo="Success" fCount="1" fType="0" iCount="0" pCount="0"/>
  <DeviceInfo dpId="MANTRA" rdsId="MANTRA" rdsVer="1.0.4" mi="MSF110" mc="MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC+dCYUZKe0vQs..."/>
  <Skey ci="20201007">encrypted_session_key</Skey>
  <Hmac>hmac_value</Hmac>
  <Data type="X">base64_encoded_fingerprint_data</Data>
</PidData>
```

### 8. **Device Testing Commands**

**Test Device Info:**
```javascript
fetch('http://127.0.0.1:11100/rd/info')
  .then(response => response.text())
  .then(data => console.log(data))
```

**Test Capture:**
```javascript
fetch('http://127.0.0.1:11100/rd/capture', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    "Quality": 60,
    "TimeOut": 10,
    "fCount": 1,
    "fType": 0,
    "pidVer": "2.0",
    "format": 0
  })
})
```

### 9. **Firewall Configuration**

Add firewall exception for port 11100:
```cmd
netsh advfirewall firewall add rule name="MSF110 RD Service" dir=in action=allow protocol=TCP localport=11100
```

### 10. **Service Restart Script**

Create `restart_rd_service.bat`:
```batch
@echo off
echo Stopping MSF110 RD Service...
taskkill /f /im MantraRDService.exe
timeout /t 2
echo Starting MSF110 RD Service as Administrator...
runas /user:Administrator "C:\Program Files\Mantra\RDService\MantraRDService.exe"
echo Service restarted!
pause
```

## Quick Diagnostic Steps

1. **Verify Service Running:**
   ```cmd
   tasklist | findstr Mantra
   ```

2. **Check Port Binding:**
   ```cmd
   netstat -ano | findstr :11100
   ```

3. **Test HTTP Response:**
   ```cmd
   curl http://127.0.0.1:11100/rd/info
   ```

4. **Check Device Connection:**
   - Device should be recognized in Device Manager
   - LED should be on/blinking

5. **Browser Console Test:**
   ```javascript
   fetch('http://127.0.0.1:11100/rd/info').then(r => r.text()).then(console.log)
   ```

## Contact Support

If issues persist:
- Check Mantra Softech official documentation
- Contact technical support with device serial number
- Verify software version compatibility

---

**Important:** Always run RD Service as Administrator for proper device access.