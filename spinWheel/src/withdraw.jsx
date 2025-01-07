// WithdrawalRequest.jsx
import React, { useState } from 'react';
import 'bootstrap/dist/css/bootstrap.min.css';
import Swal from 'sweetalert2';

const WithdrawalRequest = () => {
  const userId = localStorage.getItem('user_id');
  const [formData, setFormData] = useState({
    userName: '',
    bankName: '',
    accountNumber: '',
    accountHolderName: '',
    ifscCode: '',
    amount: '',
    notes: '',
    userId: userId
  });
  const [loading, setLoading] = useState(false);

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch('http://145.223.21.62:3012/api/withdrawals/request', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Something went wrong');
      }

      // Show success message
      await Swal.fire({
        title: 'Success!',
        text: 'Withdrawal request submitted successfully',
        icon: 'success',
        confirmButtonColor: '#3085d6'
      });

      // Reset form
      setFormData({
        userName: '',
        bankName: '',
        accountNumber: '',
        accountHolderName: '',
        ifscCode: '',
        amount: '',
        notes: ''
      });
    } catch (error) {
      Swal.fire({
        title: 'Error!',
        text: error.message || 'Failed to submit withdrawal request',
        icon: 'error',
        confirmButtonColor: '#d33'
      });
    } finally {
      setLoading(false);
    }
  };

  // Validate IFSC code format
  const validateIFSC = (code) => {
    const ifscPattern = /^[A-Z]{4}0[A-Z0-9]{6}$/;
    return ifscPattern.test(code);
  };

  // Validate account number (basic validation)
  const validateAccountNumber = (number) => {
    return /^\d{9,18}$/.test(number); // Most bank accounts are between 9 and 18 digits
  };

  return (
    <div className="container py-4">
      <div className="row justify-content-center">
        <div className="col-12 col-md-8 col-lg-6">
          <div className="card shadow">
            <div className="card-header bg-primary text-white">
              <h4 className="mb-0">Request Withdrawal</h4>
            </div>
            <div className="card-body">
              <form onSubmit={handleSubmit} className="needs-validation" noValidate>
                {/* <div className="column">
                  <div className="col-md-6 mb-3">
                    <label htmlFor="userName" className="form-label">User Name*</label>
                    <input
                      type="text"
                      className="form-control"
                      id="userName"
                      name="userName"
                      value={formData.userName}
                      onChange={handleInputChange}
                      required
                      minLength="2"
                      placeholder="Enter your name"
                    />
                    <div className="invalid-feedback">
                      Please enter your name
                    </div>
                  </div> */}

                  {/* <div className="col-md-6 mb-3">
                    <label htmlFor="bankName" className="form-label">Bank Name*</label>
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
                      Please enter bank name
                    </div>
                  </div>
                </div> */}

                <div className="row">
                  <div className="col-12 mb-3">
                    <label htmlFor="accountHolderName" className="form-label">Account Holder Name*</label>
                    <input
                      type="text"
                      className="form-control"
                      id="accountHolderName"
                      name="accountHolderName"
                      value={formData.accountHolderName}
                      onChange={handleInputChange}
                      required
                      placeholder="Enter account holder name"
                    />
                    <div className="invalid-feedback">
                      Please enter account holder name
                    </div>
                  </div>
                </div>

              
                  <div className="col-12 mb-3">
                    <label htmlFor="accountNumber" className="form-label">IPay Mobile Number*</label>
                    <input
                      type="text"
                      className="form-control"
                      id="accountNumber"
                      name="accountNumber"
                      value={formData.accountNumber}
                      onChange={handleInputChange}
                      required
                      pattern="\d{9,18}"
                      placeholder="Enter Ipay Mobile Number"
                    />
                    <div className="invalid-feedback">
                      Please enter a valid phone number (9-18 digits)
                    </div>
                  </div>

                  {/* <div className="col-md-6 mb-3">
                    <label htmlFor="ifscCode" className="form-label">IFSC Code*</label>
                    <input
                      type="text"
                      className="form-control"
                      id="ifscCode"
                      name="ifscCode"
                      value={formData.ifscCode}
                      onChange={handleInputChange}
                      required
                      pattern="^[A-Z]{4}0[A-Z0-9]{6}$"
                      placeholder="Enter IFSC code"
                    />
                    <div className="invalid-feedback">
                      Please enter a valid IFSC code
                    </div>
                  </div> */}
                

                <div className="mb-3">
                  <label htmlFor="amount" className="form-label">Withdrawal Amount*</label>
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
                      min="1"
                      step="0.01"
                      placeholder="0.00"
                    />
                    <div className="invalid-feedback">
                      Please enter a valid amount
                    </div>
                  </div>
                </div>

                <div className="mb-3">
                  <label htmlFor="notes" className="form-label">Notes (Optional)</label>
                  <textarea
                    className="form-control"
                    id="notes"
                    name="notes"
                    value={formData.notes}
                    onChange={handleInputChange}
                    rows="3"
                    placeholder="Add any additional notes here"
                  />
                </div>

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
                      ></span>
                      Processing...
                    </>
                  ) : 'Submit Withdrawal Request'}
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default WithdrawalRequest;