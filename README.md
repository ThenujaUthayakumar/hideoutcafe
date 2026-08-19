# Aura Cafe Point of Sale (POS) & Management System
### Core PHP (OOP & PDO) & MySQL Enterprise Cafe Solution

A high-performance, modern, and beautiful Point of Sale (POS) and cafe management system built entirely with **Pure / Core PHP** and **MySQL**. Designed specifically for specialty coffee shops, artisan bakeries, bistros, and quick-service cafes.

---

## Key Features

1. **Role-Based Access Control (RBAC)**:
   - **Administrator**: Full administrative control, user/staff management, store settings, tax rules, and audit logs.
   - **Manager**: Inventory management, product variants/modifiers, petty cash expenses, and sales reporting.
   - **Cashier / Barista**: Streamlined POS terminal, live cart, table floor map, customer lookup, and shift register drawer.

2. **Interactive Cafe POS Terminal (`pos.php`)**:
   - Touch and keyboard-friendly layout with instant category ribbons.
   - Real-time product search with barcode & SKU matching.
   - **Item Customization Modal**: Choose drink size (Small, Regular, Large), plant milks (Oat, Almond, Soy, Coconut), artisan syrups (Vanilla, Caramel, Hazelnut), extra espresso shots, and sweetness/ice levels.
   - **Dine-In, Takeaway & Delivery** order modes with visual table assignment.
   - Quick cash payment calculator with preset denomination buttons ($10, $20, $50, $100, Exact) and live return change calculator.
   - Split payments, Card/POS terminal, and UPI/QR code payment modes.
   - **Order Parking / Hold Order**: Suspend active carts and recall them at any time.

3. **Kitchen Display System (KDS) / Live Barista Screen (`kds.php`)**:
   - Real-time queue for food & beverage preparation.
   - Sound alert chime when new orders arrive.
   - 1-click status workflow: `Pending` -> `Preparing` -> `Ready` -> `Served/Completed`.

4. **Cafe Floor & Table Management (`tables.php`)**:
   - Color-coded table map: `Available` (Green), `Occupied` (Red), `Billed` (Yellow).
   - Direct 1-click ordering from any table.

5. **Receipts & Invoices**:
   - **80mm & 58mm Thermal Printer Slip** (`print_receipt.php`) formatted for direct POS receipt printers.
   - **A4 Printable Tax Invoice** (`order_view.php`) for corporate orders and records.

6. **Customer CRM & Loyalty Points (`customers.php`)**:
   - Customer directory with purchase tracking.
   - Automatic loyalty points credit on POS checkout.

7. **Shift Cash Register & Drawer Management (`cash_register.php`)**:
   - Opening float tracking, shift sales aggregation, and end-of-day counted cash discrepancy reconciliation.

8. **Petty Cash & Expense Tracking (`expenses.php`)**:
   - Track green coffee bean purchases, dairy deliveries, and store supplies.

9. **Financial Reports & Analytics (`reports.php`)**:
   - Date range sales summaries, top 10 selling products, payment mode shares, cashier performance, and 1-click **Export to CSV / Excel**.

---

## Installation & Setup Instructions

### 1. Requirements:
- **PHP**: 7.4 or 8.0 / 8.1 / 8.2 / 8.3+
- **MySQL / MariaDB**: 5.7 or 8.0+
- **Web Server**: Apache / Nginx / XAMPP / WAMP / Laragon / PHP Built-in Server

### 2. Database Import:
1. Open **phpMyAdmin** (or MySQL CLI / Workbench).
2. Create a new database named `cafe_pos_db`.
3. Import the SQL file located at `database/database.sql`.

### 3. Database Credentials Configuration:
If your MySQL credentials differ from the standard XAMPP defaults (`root` with no password), open `config/config.php` and adjust:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cafe_pos_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Running the Application:
#### Option A: Using XAMPP / WAMP / Laragon
Move or symlink the `cafe-pos` folder into your `htdocs` or `www` directory, then navigate to:
`http://localhost/cafe-pos/`

#### Option B: Using PHP Built-in Server
Open your terminal in the `cafe-pos` directory and run:
```bash
php -S localhost:8000
```
Then visit: `http://localhost:8000` in your web browser.

---

## Default Login Credentials

| Role | Email | Password | Access Level |
|---|---|---|---|
| **Admin** | `admin@cafepos.com` | `admin123` | Full System Access |
| **Manager** | `manager@cafepos.com` | `manager123` | Menu, Inventory, Expenses, Reports |
| **Cashier** | `cashier@cafepos.com` | `cashier123` | POS, Tables, Orders, Customer CRM |
| **Barista** | `barista@cafepos.com` | `cashier123` | KDS, POS, Orders |

*(Tip: The login page includes 1-click demo credential autofill buttons for instant testing).*

---

## POS Keyboard Shortcuts

- `F1`: Focus Product Search input
- `F2`: Open Payment / Checkout Modal
- `F4`: Park / Hold Current Order
- `F7`: View / Recall Parked Orders
- `F9`: Clear Current Order Cart
- `Esc`: Close any active modal

---

## File Structure

```
cafe-pos/
├── config/
│   ├── config.php          # System configuration, timezone, database constants
│   ├── database.php        # PDO Singleton Database Handler
│   └── functions.php       # Auth guards, CSRF security, currency & settings helpers
├── database/
│   └── database.sql        # Full MySQL DDL schema + Seeded menu items & sample orders
├── assets/
│   ├── css/
│   │   └── style.css       # Custom styles & thermal 80mm/58mm print CSS
│   └── js/
│       ├── app.js          # Web Audio sound FX, Toast notifications, Modals
│       └── pos.js          # POS terminal engine, cart calculations, keypad logic
├── includes/
│   ├── header.php          # Top bar navbar, role badges & shift register status
│   ├── sidebar.php         # Left navigation sidebar with RBAC filtering
│   ├── footer.php          # Modals, shift drawer dialog, scripts
│   └── receipt_template.php # 80mm / 58mm thermal printable slip layout
├── api/
│   ├── pos_checkout.php    # Atomic sale transaction & stock deduction API
│   ├── get_products.php    # Product & variant search API
│   ├── get_customers.php   # Customer autocomplete API
│   ├── save_customer.php   # Quick customer registration API
│   ├── hold_order.php      # Park & recall orders API
│   ├── update_kds.php      # Live kitchen display status API
│   └── drawer_action.php   # Shift opening/closing & float management API
├── index.php               # Smart route redirector
├── login.php               # Secure login with 1-click demo accounts
├── logout.php              # Session destruction
├── dashboard.php           # Analytics dashboard & Chart.js sales trends
├── pos.php                 # Core Cafe POS Terminal
├── kds.php                 # Live Kitchen / Barista Display Screen
├── tables.php              # Floor plan & table occupancy manager
├── orders.php              # Sales history & order voiding/reprints
├── order_view.php          # A4 printable invoice details
├── print_receipt.php       # Thermal printer slip window
├── products.php            # Product menu CRUD & stock tracking
├── categories.php          # Category CRUD & icon selection
├── modifiers.php           # Milk, syrup & topping add-on CRUD
├── customers.php           # Customer directory & loyalty CRM
├── cash_register.php       # Shift drawer logs & discrepancy reconciliation
├── expenses.php            # Cafe expenses & petty cash logs
├── reports.php             # Date range financial reports & CSV export
└── settings.php            # Store profile, taxes, currency & receipt customization
```
