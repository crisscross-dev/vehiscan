// Node.js middleware for RFID hardware integration
// Requires: npm install serialport axios

const { SerialPort } = require('serialport');
const axios = require('axios');

const portName = 'COM3'; // Change to your RFID scanner's port
const baudRate = 9600;   // Change if your scanner uses a different rate
const backendUrl = process.env.APP_URL ? process.env.APP_URL + '/api/rfid/scan.php' : 'http://localhost/api/rfid/scan.php';
const apiKey = process.env.RFID_API_KEY || '';
const readerId = process.env.RFID_READER_ID || 'middleware-reader';

let port;
try {
  // serialport v10+
  port = new SerialPort({ path: portName, baudRate });
} catch (err) {
  // Older constructor fallback
  port = new SerialPort(portName, { baudRate });
}

port.on('open', () => {
  console.log(`RFID reader connected on ${portName}`);
  if (!apiKey) {
    console.warn('RFID_API_KEY is not set; scans will be skipped until configured.');
  }
});

port.on('data', async (data) => {
  const rfidUid = data.toString().trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (!rfidUid) return;
  console.log(`RFID scanned: ${rfidUid}`);

  if (!apiKey) {
    console.error('Missing RFID_API_KEY. Set environment variable before running middleware.');
    return;
  }

  try {
    const response = await axios.post(
      backendUrl,
      { rfid_uid: rfidUid },
      {
        headers: {
          'Content-Type': 'application/json',
          'X-API-Key': apiKey,
          'X-Reader-ID': readerId
        },
        timeout: 10000
      }
    );
    console.log('Backend response:', response.data);
  } catch (err) {
    const detail = err.response?.data || err.message;
    console.error('Error sending to backend:', detail);
  }
});

port.on('error', (err) => {
  console.error('Serial port error:', err.message);
});

// Keep process alive
process.stdin.resume();
