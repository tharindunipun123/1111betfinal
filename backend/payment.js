// server.js
const express = require('express');
const mysql = require('mysql2');
const fileUpload = require('express-fileupload');
const path = require('path');
const cors = require('cors');
require('dotenv').config();

const app = express();

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(fileUpload({
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB max file size
}));
app.use('/uploads', express.static('uploads'));

// Database connection
const db = mysql.createConnection({
  host: '127.0.0.1',
  user: 'newuser',
  password: 'newuser_password',
  database: 'spin_wheel_db',
}).promise();

// Test database connection
db.connect()
  .then(() => console.log('Connected to MySQL database'))
  .catch(err => console.error('Database connection error:', err));

// Withdrawal Request API
app.post('/api/withdrawals/request', async (req, res) => {
  try {
    const {
      userName,
      bankName,
      accountNumber,
      accountHolderName,
      ifscCode,
      amount,
      notes,
      userId
    } = req.body;

    console.log(userId);


    const [result] = await db.execute(
      `INSERT INTO withdrawal_requests 
       (user_name, bank_name, account_number, account_holder_name, ifsc_code, amount, notes,users_id)
       VALUES (?, ?, ?, ?, ?, ?, ?,?)`,
      [userName, bankName, accountNumber, accountHolderName, ifscCode, amount, notes,userId]
    );

    res.status(201).json({
      success: true,
      message: 'Withdrawal request submitted successfully',
      data: { id: result.insertId }
    });
  } catch (error) {
    console.error('Error in withdrawal request:', error);
    res.status(500).json({
      success: false,
      message: 'Error processing withdrawal request'
    });
  }
});

// Get all withdrawal requests
app.get('/api/withdrawals', async (req, res) => {
  try {
    const [rows] = await db.query('SELECT * FROM withdrawal_requests ORDER BY created_at DESC');
    res.json({
      success: true,
      data: rows
    });
  } catch (error) {
    console.error('Error fetching withdrawal requests:', error);
    res.status(500).json({
      success: false,
      message: 'Error fetching withdrawal requests'
    });
  }
});

// Payment Receipt Upload API
app.post('/api/payments/upload', async (req, res) => {
  try {
    if (!req.files || !req.files.receipt) {
      return res.status(400).json({
        success: false,
        message: 'No receipt file uploaded'
      });
    }

    const { bankName, accountNumber, amount,userId } = req.body;
    const receiptFile = req.files.receipt;

    

    // Validate file type
    if (!receiptFile.mimetype.startsWith('image/')) {
      return res.status(400).json({
        success: false,
        message: 'Please upload only image files'
      });
    }

    // Create unique filename
    const fileExt = path.extname(receiptFile.name);
    const fileName = Date.now() + fileExt;
    const uploadPath = path.join(__dirname, 'uploads', fileName);

    // Move file to uploads directory 
    await receiptFile.mv(uploadPath);

    // Save payment receipt details to database
    const [result] = await db.execute(
      `INSERT INTO payment_receipts 
       (bank_name, reference_number, amount, receipt_url,users_id)
       VALUES (?, ?, ?, ?,?)`,
      [bankName, accountNumber, amount, `/uploads/${fileName}` , userId]
    );

    res.status(201).json({
      success: true,
      message: 'Payment receipt uploaded successfully',
      data: {
        id: result.insertId,
        receiptUrl: `/uploads/${fileName}`
      }
    });
  } catch (error) {
    console.error('Error in payment receipt upload:', error);
    res.status(500).json({
      success: false,
      message: 'Error processing payment receipt'
    });
  }
});

// Get all payment receipts
app.get('/api/payments', async (req, res) => {
  try {
    const [rows] = await db.query('SELECT * FROM payment_receipts ORDER BY created_at DESC');
    res.json({
      success: true,
      data: rows
    });
  } catch (error) {
    console.error('Error fetching payment receipts:', error);
    res.status(500).json({
      success: false,
      message: 'Error fetching payment receipts'
    });
  }
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({
    success: false,
    message: 'Something went wrong!'
  });
});

// Start server
const PORT = process.env.PORT || 3012;
app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`);
});