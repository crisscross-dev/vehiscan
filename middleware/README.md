# RFID Middleware Integration

## Purpose
This middleware connects the physical RFID scanner to the Vehiscan-RFID PHP backend.

## How it works
- Listens to the RFID scanner via serial port (e.g., COM3)
- Sends scanned RFID data to the backend API endpoint (`api/rfid/scan.php`)

## Setup
1. Install Node.js and npm
2. Run `npm install serialport axios` in this folder
3. Edit `rfid_reader.js` to match your scanner's port and baud rate
4. Set environment variables before starting:
	- `RFID_API_KEY` (required): API key from `rfid_api_keys`
	- `RFID_READER_ID` (optional): reader identifier override
5. Start the middleware: `node rfid_reader.js`

## Backend Endpoint
- Receives POST requests with `{ "rfid_uid": "<card_uid>" }`
- Requires headers:
	- `X-API-Key: <api_key>`
	- `X-Reader-ID: <reader_id>` (optional but recommended)
- Returns scan result and access decision in JSON

## Troubleshooting
- Check port and baud rate
- Ensure `RFID_API_KEY` is set
- Ensure backend API is accessible
- Review logs for errors
