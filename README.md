# Stock Transfer Engine V3

A sophisticated AI-based stock management system with real-time monitoring, predictive analytics, and comprehensive reporting capabilities.

## Features

- **Real-time Stock Management**: Track inventory levels, transfers, and movements
- **AI-Powered Analytics**: Predictive stock forecasting and optimization recommendations
- **Comprehensive Dashboard**: Modern web interface with real-time charts and metrics
- **Advanced Reporting**: Detailed analytics and export capabilities for system integration
- **RESTful API**: Complete API for external system integration
- **Multi-user Support**: Role-based access control and user management
- **Audit Trail**: Complete transaction history and compliance reporting

## Technology Stack

- **Backend**: Python FastAPI with SQLAlchemy ORM
- **Database**: PostgreSQL with Redis caching
- **Frontend**: React with Material-UI
- **AI/ML**: scikit-learn, pandas for analytics
- **Containerization**: Docker and Docker Compose
- **Testing**: pytest for backend, Jest for frontend

## Quick Start

```bash
# Clone the repository
git clone https://github.com/pearcestephens/STOCK_TRANSFER_ENGINE_V3.git
cd STOCK_TRANSFER_ENGINE_V3

# Start with Docker Compose
docker-compose up -d

# Or run locally
pip install -r requirements.txt
python -m uvicorn app.main:app --reload
```

## API Documentation

Once running, visit:
- API Documentation: http://localhost:8000/docs
- Dashboard: http://localhost:3000

## Project Structure

```
├── backend/           # FastAPI backend application
├── frontend/          # React dashboard application
├── database/          # Database migrations and seeds
├── docker/            # Docker configuration files
├── tests/             # Test suites
├── docs/              # Documentation
└── scripts/           # Utility scripts
```

## License

MIT License - see LICENSE file for details.
