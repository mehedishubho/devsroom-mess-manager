For a **Bangladesh Mess Management System**, the biggest mistake is building only meal counting and expense tracking. Most messes in Bangladesh have unique workflows such as bazar management, meal off/on requests, guest meals, fixed monthly costs, advance deposits, due tracking, and monthly closing.

Below is a detailed product requirement prompt you can use for Laravel 13 + Laravel Boost + Tyro Dashboard + MySQL.

---

# Project: Mess Management System (Bangladesh)

## Tech Stack

- Laravel 13
- Laravel Boost
- MySQL 8+
- Tyro Dashboard UI
- Role & Permission Management
- Responsive Design (Mobile First)
- PWA Ready (Optional Future)

---

# Overview

Develop a complete Mess Management System designed specifically for Bangladesh messes, bachelor hostels, student hostels, and shared accommodations.

The system should automate:

- Member management
- Daily meal tracking
- Market/Bazar expense tracking
- Fixed monthly expenses
- Monthly meal rate calculation
- Individual member billing
- Advance payments
- Due management
- Guest meal management
- Monthly reports

---

# User Roles

## Super Admin

Can manage everything.

Permissions:

- Manage messes
- Manage members
- Manage expenses
- Manage meal entries
- Manage payments
- Monthly closing
- Reports
- Settings

---

## Mess Manager

Can manage assigned mess.

Permissions:

- Member management
- Daily meals
- Bazar entries
- Expenses
- Payments
- Reports

---

## Member

Limited access.

Permissions:

- View own meals
- View bill
- View payment history
- Submit meal off request
- View monthly reports

---

# Module 1: Mess Management

Support multiple messes.

Fields:

- Mess Name
- Address
- Manager Name
- Mobile Number
- Monthly Rent
- Status

Features:

- Multiple mess support
- Active/Inactive mess
- Member count

---

# Module 2: Member Management

Fields:

- Full Name
- Mobile Number
- Email
- NID (Optional)
- Profession
- Room Number
- Seat Number
- Joining Date
- Leaving Date
- Status
- Emergency Contact
- Profile Image

Features:

- Active members
- Former members
- Member search
- Member profile
- Name-based URLs (`/mess/members/{slug}`)
- Duplicate prevention (email + mobile unique per mess)
- Deactivate, soft-delete, and (super-admin) permanent delete

---

# Module 3: Meal Management

Bangladesh-specific meal system.

Meal Types:

- Breakfast
- Lunch
- Dinner

Default Values:

Breakfast = 0.5 Meal

Lunch = 1 Meal

Dinner = 1 Meal

Configurable from settings.

Features:

- Daily meal entry
- Bulk meal entry
- Monthly meal summary
- Meal history

---

# Module 4: Meal Off System

Members can mark:

- Meal Off
- Vacation
- Outside Tour

Features:

- Date range selection
- Auto meal deduction
- Approval workflow

---

# Module 5: Guest Meal Management

Guest meals are very common.

Fields:

- Guest Name
- Member Name
- Date
- Meal Type
- Quantity

Features:

- Add guest meal
- Charge member
- Include in monthly bill

---

# Module 6: Market/Bazar Management

Most important module.

Fields:

- Purchase Date
- Purchased By
- Vendor Name
- Description
- Amount
- Receipt Image

Examples:

- Rice
- Fish
- Meat
- Vegetables
- Oil
- Gas

Features:

- Daily bazar tracking
- Monthly bazar report
- Expense analytics

---

# Module 7: Fixed Monthly Expenses

Separate from bazar expenses.

Examples:

- Mess Rent
- Cook Salary
- Maid Salary
- Internet Bill
- Electricity Bill
- Water Bill
- Gas Bill
- Security Cost

Important Rule:

Meal Rate Calculation MUST NOT include fixed expenses.

Fixed expenses should be:

```
Total Fixed Expenses
÷
Current Active Members
=
Per Member Fixed Cost
```

Then:

```
Member Final Bill
=
Meal Cost
+
Per Member Fixed Cost
```

---

# Module 8: Expense Categories

Default Categories:

- Market/Bazar
- Rent
- Cook Salary
- Internet
- Electricity
- Water
- Gas
- Maintenance
- Cleaning
- Others

Admin can create custom categories.

---

# Module 9: Payment Management

Fields:

- Member
- Date
- Amount
- Payment Method
- Reference Number
- Notes

Methods:

- Cash
- bKash
- Nagad
- Rocket
- Bank Transfer

Features:

- Payment history
- Advance payment
- Due payment

---

# Module 10: Advance Balance System

Many members deposit money in advance.

Features:

- Credit balance
- Carry forward
- Auto adjustment

Example:

```
Previous Advance = 1000

Current Bill = 850

Remaining Advance = 150
```

---

# Module 11: Monthly Closing System

Critical feature.

When month closes:

System calculates:

- Total Meals
- Total Bazar Cost
- Meal Rate
- Fixed Cost
- Individual Bills

Formula:

### Meal Rate

```
Meal Rate
=
Total Bazar Cost
÷
Total Meals
```

### Member Meal Cost

```
Member Total Meals
×
Meal Rate
```

### Final Bill

```
Meal Cost
+
Fixed Cost Share
-
Payments
-
Advance Balance
```

Generate immutable monthly snapshot.

---

# Module 12: Reports

## Monthly Report

Shows:

- Total Members
- Total Meals
- Meal Rate
- Total Market Cost
- Fixed Expenses

---

## Member Statement

Shows:

- Meals
- Guest Meals
- Payments
- Due
- Advance

---

## Expense Report

Filter by:

- Date
- Category
- Month

---

## Payment Report

Filter by:

- Member
- Method
- Date

---

# Module 13: Dashboard

Cards:

- Total Members
- Today's Meals
- Current Month Meal Rate
- Monthly Expenses
- Total Due
- Total Advance

Charts:

- Expense Trend
- Meal Trend
- Payment Trend

---

# Module 14: Notification System

In-app bell (always on) plus multi-channel delivery. Admin enables channels mess-wide; each member picks their own subset.

Notifications for:

- Monthly closing
- Due reminder
- Payment received
- Meal off approval
- Backup failure

Channels (shipped):

- Email (Laravel mail driver / SMTP)
- WhatsApp (Twilio WhatsApp API)
- Telegram (Bot API)
- SMS / phone (Vonage or Twilio)

Features:

- Admin configures active channels + credentials in the dashboard
- Multiple channels can be active at once
- Per-notification-type routing matrix
- Per-member channel preferences (a subset of admin-enabled channels)
- Fail-open: a down/misconfigured channel never blocks the triggering action

---

# Module 15: Settings

Configurable:

- Breakfast value
- Lunch value
- Dinner value
- Currency (BDT)
- Date format
- Fiscal settings
- Auto monthly close

---

# Module 16: Audit Logs

Track:

- Meal changes
- Expense edits
- Payment edits
- Member updates

Store:

- User
- Action
- Timestamp

---

# Database Modules

```text
users
roles
permissions

messes

members

meal_settings
meal_entries
meal_off_requests
guest_meals

expense_categories
expenses

payments
advance_balances

monthly_closings
monthly_member_summaries

notifications
audit_logs

settings
```

# Bangladesh-Specific Requirements

- Currency: BDT (৳)
- Support bKash, Nagad, Rocket
- Meal Rate based ONLY on Bazar Expenses
- Fixed Expenses distributed equally among active members
- Advance balance carry forward
- Guest meal charging
- Monthly closing lock system
- Mobile-friendly UI for mess managers
- Bengali and English language support (future-ready)
