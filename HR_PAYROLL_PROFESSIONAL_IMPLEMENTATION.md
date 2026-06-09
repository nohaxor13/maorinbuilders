# Maorin Builders HR / Payroll Professional Implementation

Updated modules in `workspace.php`:

- Employees: office/field categories, resume-style profile, photo upload, document tracking, view/edit/delete, printable profile, ID preview.
- Departments: admin-created dropdown records for employee profile.
- Job Titles & Rates: admin-created job title dropdown with salary/rate type.
- Attendance: calendar-style daily checking with present/late/absent/half-day/leave/rest-day statuses.
- Attendance Settings: work start/end, late grace, overtime threshold, overtime multiplier.
- Payroll: preview and save payroll periods from attendance records.

The workspace API performs guarded table/column creation at runtime, and the SQL file includes the main CREATE TABLE migrations.
