// PaymentReceipt.jsx
import React, { useState,useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import 'bootstrap/dist/css/bootstrap.min.css';
import Swal from 'sweetalert2';

const PaymentReceipt = () => {
 
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [previewUrl, setPreviewUrl] = useState(null);
  const userId = localStorage.getItem('user_id');
  const navigate = useNavigate();

  useEffect(() => {
    const userId = localStorage.getItem('user_id');
    if (!userId) {
      navigate('/login');
    }
  }, [navigate]);

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    setError('');
  };

 
  const [formData, setFormData] = useState({
    bankName: '',
    accountNumber: '', // This is used as reference number
    amount: '',
    receipt: null,
    userId: userId
  });
  

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) {
      setError('Please select a file');
      setPreviewUrl(null);
      return;
    }

    // Check file type
    if (!file.type.match('image/(jpeg|png|jpg)')) {
      setError('Please upload only JPG or PNG images');
      setPreviewUrl(null);
      return;
    }

    // Check file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      setError('File size must be less than 5MB');
      setPreviewUrl(null);
      return;
    }

    setFormData(prev => ({
      ...prev,
      receipt: file
    }));
    setError('');
    
    // Create preview URL
    const fileUrl = URL.createObjectURL(file);
    setPreviewUrl(fileUrl);
  };

  const resetForm = () => {
    setFormData({
      bankName: '',
      accountNumber: '',
      amount: '',
      receipt: null,
      userId
    });
    setPreviewUrl(null);
    setError('');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      // Validate inputs
      if (!formData.bankName.trim()) {
        throw new Error('Bank name is required');
      }
      if (!formData.accountNumber.trim()) {
        throw new Error('Reference number is required');
      }
      if (!formData.amount || parseFloat(formData.amount) <= 0) {
        throw new Error('Please enter a valid amount');
      }
      if (!formData.receipt) {
        throw new Error('Please upload a receipt');
      }

      const formDataToSend = new FormData();
      Object.keys(formData).forEach(key => {
        formDataToSend.append(key, formData[key]);
      });

      const response = await fetch('http://145.223.21.62:3012/api/payments/upload', {
        method: 'POST',
        body: formDataToSend,
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Failed to upload payment receipt');
      }

      // Show success message
      await Swal.fire({
        title: 'Success!',
        text: 'Payment receipt uploaded successfully',
        icon: 'success',
        confirmButtonColor: '#3085d6'
      });

      // Reset form
      resetForm();
    } catch (err) {
      setError(err.message);
      Swal.fire({
        title: 'Error!',
        text: err.message,
        icon: 'error',
        confirmButtonColor: '#d33'
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container py-4">
      <div className="row justify-content-center">
        <div className="col-12 col-md-8 col-lg-6">
          <div className="card shadow">
            <div className="card-header bg-primary text-white">
              <h4 className="mb-0">Upload Payment Receipt</h4>
            </div>
            <div className="card-body">
              <form onSubmit={handleSubmit} className="needs-validation" noValidate>
                <div className="mb-3">
                  <label htmlFor="bankName" className="form-label">Mobile Number*</label>
                  <input
                    type="text"
                    className="form-control"
                    id="bankName"
                    name="bankName"
                    value={formData.bankName}
                    onChange={handleInputChange}
                    required
                    placeholder="Enter bank name"
                  />
                  <div className="invalid-feedback">
                    Please enter Mobile Number
                  </div>
                </div>

                <div className="mb-3">
                  <label htmlFor="accountNumber" className="form-label">Reference Number*</label>
                  <input
                    type="text"
                    className="form-control"
                    id="accountNumber"
                    name="accountNumber"
                    value={formData.accountNumber}
                    onChange={handleInputChange}
                    required
                    placeholder="Enter reference number"
                  />
                  <div className="invalid-feedback">
                    Please enter reference number
                  </div>
                </div>

                <div className="mb-3">
                  <label htmlFor="amount" className="form-label">Amount*</label>
                  <div className="input-group">
                    <span className="input-group-text">$</span>
                    <input
                      type="number"
                      className="form-control"
                      id="amount"
                      name="amount"
                      value={formData.amount}
                      onChange={handleInputChange}
                      required
                      placeholder="0.00"
                      min="0.01"
                      step="0.01"
                    />
                  </div>
                  <div className="invalid-feedback">
                    Please enter a valid amount
                  </div>
                </div>

                <div className="mb-3">
                  <label htmlFor="receipt" className="form-label">Upload Receipt*</label>
                  <input
                    type="file"
                    className="form-control"
                    id="receipt"
                    name="receipt"
                    accept="image/jpeg,image/png,image/jpg"
                    onChange={handleFileChange}
                    required
                  />
                  <div className="form-text text-muted">
                    Maximum file size: 5MB (JPG or PNG only)
                  </div>
                </div>

                {previewUrl && (
                  <div className="mb-3 text-center">
                    <label className="form-label">Receipt Preview</label>
                    <div className="border rounded p-2">
                      <img
                        src={previewUrl}
                        alt="Receipt preview"
                        className="img-fluid img-thumbnail"
                        style={{ maxHeight: '200px' }}
                      />
                    </div>
                  </div>
                )}

                {error && (
                  <div className="alert alert-danger mb-3" role="alert">
                    {error}
                  </div>
                )}

                <button 
                  type="submit" 
                  className="btn btn-primary w-100" 
                  disabled={loading}
                >
                  {loading ? (
                    <>
                      <span 
                        className="spinner-border spinner-border-sm me-2" 
                        role="status" 
                        aria-hidden="true"
                      />
                      Uploading...
                    </>
                  ) : 'Upload Receipt'}
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PaymentReceipt;