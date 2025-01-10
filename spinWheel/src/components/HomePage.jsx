import React from 'react';
import { useNavigate } from 'react-router-dom';
import logo from './360.png'

const HomePage = () => {
  const navigate = useNavigate();

  const games = [
    {
      title: "Spin Wheel Game",
      path: "/spinwheel",
      image: "https://script.viserlab.com/kasino/assets/images/frontend/banner/6649ec9ddb9761716120733.jpg",
      description: "Spin the wheel and win exciting prizes!"
    },
    {
      title: "Lucky Number Game",
      path: "/lucky",
      image: "https://thegamereward.com/wp-content/uploads/2022/09/Lucky-Win-Casino.jpeg",
      description: "Choose your lucky number and multiply your earnings!"
    },
    {
      title: "Dice Game",
      path: "/dice",
      image: "https://fthmb.tqn.com/CcCUqMPOoZtiIWsdxbCe4l-BykA=/3364x2242/filters:fill(auto,1)/Rolling-dice-GettyImages-93453966-58a6f50c3df78c345b634f6f.jpg",
      description: "Roll your Luck"
    },
     
    {
      title: "Cricket Bet Game",
      path: "/cricket",
      image: "https://tse4.mm.bing.net/th?id=OIP.pVW7gxivmMi-fqLJQQvR0AHaEA&rs=1&pid=ImgDetMain",
      description: "Play cricket and earn rewards!"
    }

  ];

  return (
    <div className="homepage-container">
      <nav className="navbar navbar-expand-lg navbar-dark fixed-top" style={{ backgroundColor: '#041A34' }}>
        <div className="container">
          <div 
            className="navbar-brand fw-bold text-info d-flex align-items-center cursor-pointer"
            onClick={() => navigate('/')}
          >
            <img
              src={logo}
              alt="Logo"
              style={{
                height: '60px',
                width: 'auto',
                marginRight: '10px',
                transition: 'transform 0.3s ease',
              }}
              onMouseOver={(e) => e.currentTarget.style.transform = 'scale(1.05)'}
              onMouseOut={(e) => e.currentTarget.style.transform = 'scale(1)'}
            />
          </div>

          <button
            className="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
          >
            <span className="navbar-toggler-icon"></span>
          </button>

          <div className="collapse navbar-collapse" id="navbarNav">
            <ul className="navbar-nav ms-auto">
              <li className="nav-item">
                <div 
                  className="nav-link cursor-pointer" 
                  onClick={() => navigate('/cricket')}
                >
                  Play Live Games
                </div>
              </li>
              <li className="nav-item">
                <div 
                  className="nav-link cursor-pointer" 
                  onClick={() => navigate('/about')}
                >
                  About Us
                </div>
              </li>
              <li className="nav-item">
                <div 
                  className="nav-link cursor-pointer" 
                  onClick={() => navigate('/payment')}
                >
                  Deposit
                </div>
              </li>
              <li className="nav-item">
                <div 
                  className="nav-link cursor-pointer" 
                  onClick={() => navigate('/withdraw')}
                >
                  Withdraw
                </div>
              </li>
            </ul>
          </div>
        </div>
      </nav>
      {/* Hero Section */}
      <section className="hero-section position-relative d-flex align-items-center min-vh-100">
        <div className="hero-overlay position-absolute w-100 h-100" style={{
          backgroundImage: `linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://script.viserlab.com/kasino/assets/images/frontend/banner/6649ec9ddb9761716120733.jpg')`,
          backgroundSize: 'cover',
          backgroundPosition: 'center'
        }}></div>
        <div className="container position-relative">
          <div className="row">
            <div className="col-lg-8 text-white">
              <h1 className="display-4 fw-bold mb-4">Earn Money While You Play</h1>
              <p className="lead mb-4">
                Contrary to popular belief, Lorem Ipsum is not simply random text. 
                It has roots in a piece of classical Latin literature.
              </p>
              <button className="btn btn-info btn-lg text-white px-5">
                Start Playing Now
              </button>
            </div>
          </div>
        </div>
      </section>

    {/* Games Section */}
<section className="py-5" style={{ backgroundColor: '#041A34' }}>
  <div className="container">
    <div className="text-center text-white mb-5">
      <h2 className="display-6 fw-bold">Fun and Win Money</h2>
      <p className="text-white">
        Contrary to popular belief, Lorem Ipsum is not simply random text.
      </p>
    </div>
    
    <div className="row g-4">
      {games.map((game, index) => (
        <div key={index} className="col-md-6 col-lg-4">
          <div 
            className="card h-100 game-card bg-dark text-white border-0" 
            onClick={() => navigate(game.path)}
            style={{ cursor: 'pointer' }}
          >
            <div className="card-img-overlay-container position-relative">
              <img 
                src={game.image} 
                className="card-img-top game-image" 
                alt={game.title}
                style={{ height: '300px', objectFit: 'cover' }}
              />
              <div className="card-img-overlay d-flex flex-column justify-content-end" 
                   style={{ background: 'linear-gradient(transparent, rgba(0,0,0,0.8))' }}>
                <h5 className="card-title fw-bold">{game.title}</h5>
                <p className="card-text">{game.description}</p>
              </div>
            </div>
          </div>
        </div>
      ))}
    </div>
  </div>
</section>
    {/* Footer */}
    <footer className="py-4 text-white" style={{ backgroundColor: '#041A34' }}>
  <div className="container">
    <div className="row gy-4">
      <div className="col-lg-3">
        <h5 className="fw-bold text-info mb-3">Logo</h5>
        <p className="text-white">
          Experience the thrill of gaming and earning.
        </p>
      </div>
      <div className="col-lg-3">
        <h5 className="fw-bold text-info mb-3">Quick Links</h5>
        <ul className="list-unstyled">
          <li>
            <a href="#about" className="text-decoration-none text-white hover-opacity">
              About Us
            </a>
          </li>
          <li>
            <a href="#contact" className="text-decoration-none text-white hover-opacity">
              Contact
            </a>
          </li>
        </ul>
      </div>
      <div className="col-lg-3">
        <h5 className="fw-bold text-info mb-3">Contact Info</h5>
        <ul className="list-unstyled text-white">
          <li>Support: support@example.com</li>
          <li>Phone: (123) 456-7890</li>
        </ul>
      </div>
      <div className="col-lg-3">
        <h5 className="fw-bold text-info mb-3">Download App</h5>
        <a 
          href="YOUR_APK_DOWNLOAD_URL_HERE" 
          className="btn btn-download"
          target="_blank"
          rel="noopener noreferrer"
        >
          <i className="fas fa-download me-2"></i>
          Download APK
        </a>
      </div>
    </div>
    <div className="text-center text-white mt-4 pt-4 border-top border-secondary">
      <small>&copy; 2024 All Rights Reserved</small>
    </div>
  </div>

  <style>
    {`
      .hover-opacity:hover {
        opacity: 0.8;
        transition: opacity 0.3s ease;
      }

      .btn-download {
        background: linear-gradient(45deg, #2196F3, #00BCD4);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 25px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
      }

      .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        background: linear-gradient(45deg, #1976D2, #0097A7);
        color: white;
      }

      .btn-download:active {
        transform: translateY(1px);
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
      }

      .game-card {
        transition: all 0.3s ease;
        background: linear-gradient(145deg, #041A34, #062952);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
      }

      .game-card:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 12px 25px rgba(0,0,0,0.3);
      }

      .game-image {
        transition: transform 0.5s ease;
        border-radius: 8px 8px 0 0;
      }

      .game-card:hover .game-image {
        transform: scale(1.08);
      }

      .card-img-overlay {
        background: linear-gradient(
          to bottom,
          rgba(4, 26, 52, 0),
          rgba(4, 26, 52, 0.8) 50%,
          rgba(4, 26, 52, 0.95) 100%
        );
        transition: all 0.3s ease;
        border-radius: 8px;
      }

      .game-card:hover .card-img-overlay {
        background: linear-gradient(
          to bottom,
          rgba(4, 26, 52, 0.2),
          rgba(4, 26, 52, 0.9) 50%,
          rgba(4, 26, 52, 0.98) 100%
        );
      }

      .card-title {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
      }

      .card-text {
        font-size: 1rem;
        opacity: 0.9;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
      }
    `}
  </style>
</footer>
      <style>
        {`
          .hero-section {
            margin-top: -56px; /* Adjust based on navbar height */
          }
          
          .game-card {
            transition: transform 0.3s ease;
          }
          
          .game-card:hover {
            transform: translateY(-5px);
          }
          
          .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
          }

          .game-image {
            transition: transform 0.3s ease;
          }

          .game-card:hover .game-image {
            transform: scale(1.05);
          }
        `}
      </style>
    </div>
  );
};

export default HomePage;