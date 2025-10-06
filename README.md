# MDRRMO-GLAN Incident Reporting and Response Coordination System

A comprehensive incident reporting system built with vanilla PHP and MySQL, designed for organizational use (Hospitals, Police, Fire Departments, etc.).

## Features

### 🔐 Authentication & Roles

- **Session-based authentication** with secure password hashing
- **Role-based access control**:
  - **Admin**: Full system access, manage all organizations and users
  - **Organization Account**: Manage assigned reports for their organization
  - **Responder**: Submit new incident reports

### 📊 Incident Management

- **Complete incident reporting** with photos, witnesses, and detailed information
- **File upload system** for incident photos (JPG/PNG, max 5MB)
- **Status tracking**: Pending → In Progress → Resolved → Closed
- **Severity levels**: Low, Medium, High, Critical
- **Categories**: Fire, Accident, Security, Medical, Emergency, Other

### 📈 Analytics Dashboard

- **Real-time statistics** and charts using Chart.js
- **Reports by category, severity, and status**
- **Monthly and daily trends**
- **Organization rankings**
- **Response time analysis**
- **Peak hours analysis**

### 🔍 Audit & Security

- **Complete audit logging** for all user actions
- **Secure file uploads** with validation
- **Input sanitization** and SQL injection protection
- **Session management** with timeout

## Installation

### Prerequisites

- **WAMP64** (Windows, Apache, MySQL, PHP)
- **PHP 7.4+** with PDO MySQL extension
- **MySQL 5.7+**

### Setup Instructions

1. **Clone/Download the project**

   ```bash
   # Place the incident-management folder in your WAMP64 www directory
   # Usually: C:\wamp64\www\incident-management
   ```

2. **Database Setup**

   - Start WAMP64 and ensure MySQL is running
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database called `incident_management`
   - Import the database schema:
     ```sql
     -- Run the contents of database/schema.sql
     ```
   - Import the seed data:
     ```sql
     -- Run the contents of database/seed_data.sql
     ```

3. **Configuration**

   - Update database credentials in `config/database.php` if needed:
     ```php
     private $host = 'localhost';
     private $db_name = 'incident_management';
     private $username = 'root';
     private $password = ''; // Your MySQL password
     ```

4. **File Permissions**

   - Ensure the `uploads/` directory is writable:
     ```bash
     # Set permissions for uploads directory
     chmod 755 uploads/
     ```

5. **Access the Application**
   - Open your browser and navigate to:
     ```
     http://localhost:8060/incident-management/
     ```

## Default Login Credentials

### Admin Account

- **Email**: admin@incidentmgmt.com
- **Password**: admin123

### Organization Account (Hospital)

- **Email**: sarah.johnson@cityhospital.com
- **Password**: org123

### Responder Account

- **Email**: john.smith@email.com
- **Password**: resp123

## Project Structure

```
incident-management/
├── config/
│   ├── config.php          # Application configuration
│   └── database.php        # Database connection
├── auth/
│   ├── login.php           # Login page
│   └── logout.php          # Logout handler
├── dashboard/
│   ├── admin.php           # Admin dashboard
│   ├── organization.php    # Organization dashboard
│   ├── responder.php       # Responder dashboard
│   ├── analytics.php       # Analytics dashboard
│   └── audit.php           # Audit logs
├── reports/
│   ├── create.php          # Create incident report
│   ├── view.php            # View incident report
│   ├── edit.php            # Edit incident report
│   ├── index.php           # All reports (Admin)
│   ├── organization.php    # Organization reports
│   └── my-reports.php      # User's reports
├── organizations/
│   ├── index.php           # Manage organizations
│   └── users.php           # Manage users
├── views/
│   ├── header.php          # Common header
│   ├── sidebar.php         # Navigation sidebar
│   └── footer.php          # Common footer
├── uploads/                # File upload directory
├── database/
│   ├── schema.sql          # Database schema
│   └── seed_data.sql       # Sample data
├── index.php               # Main entry point
└── README.md               # This file
```

## Database Schema

### Core Tables

- **organizations**: Hospital, Police, Fire Department, etc.
- **users**: System users with role-based access
- **incident_reports**: Main incident data
- **incident_photos**: Uploaded photos for incidents
- **incident_witnesses**: Witness information
- **incident_updates**: Status updates and progress
- **incident_comments**: Internal comments
- **audit_logs**: System activity tracking

## Key Features Explained

### Incident Report Workflow

1. **Responder** creates a new incident report
2. Report is assigned to an **Organization** (Hospital, Police, etc.)
3. **Organization users** can update status and add progress updates
4. **Admin** can view and manage all reports across organizations

### File Upload System

- Supports JPG and PNG images
- Maximum file size: 5MB per file
- Files stored in `/uploads` directory
- Secure file validation and storage

### Analytics Dashboard

- **Charts and graphs** using Chart.js
- **Real-time statistics** for reports, status, and trends
- **Export functionality** for data analysis
- **Role-based analytics** (users see only their relevant data)

### Security Features

- **Password hashing** using PHP's password_hash()
- **SQL injection protection** with prepared statements
- **Input sanitization** for all user inputs
- **Session management** with automatic timeout
- **Audit logging** for all system activities

## Customization

### Adding New Organization Types

1. Update the `org_type` ENUM in the database schema
2. Add options to the organization creation forms
3. Update the seed data if needed

### Adding New Incident Categories

1. Update the `category` ENUM in the database schema
2. Add options to the incident creation forms
3. Update analytics charts to include new categories

### Styling Customization

- Modify CSS in `views/header.php`
- Bootstrap 5 is used for responsive design
- Font Awesome icons are included

## Troubleshooting

### Common Issues

1. **Database Connection Error**

   - Check WAMP64 MySQL service is running
   - Verify database credentials in `config/database.php`
   - Ensure database `incident_management` exists

2. **File Upload Issues**

   - Check `uploads/` directory permissions
   - Verify PHP upload settings in php.ini
   - Ensure file size limits are appropriate

3. **Session Issues**

   - Check PHP session configuration
   - Verify session directory is writable
   - Clear browser cookies if needed

4. **Permission Errors**
   - Ensure proper file permissions on all directories
   - Check Apache/PHP error logs for specific issues

### Support

For issues or questions, check the error logs in WAMP64 or contact your system administrator.

## License

This project is created for educational and organizational use. Please ensure compliance with your organization's policies and local regulations when implementing incident reporting systems.
