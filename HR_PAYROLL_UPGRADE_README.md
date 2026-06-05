# HR / Attendance / Payroll Upgrade

Open `workspace.php#employees` after importing `sql/maorinbuilders_full_upgrade.sql`.

New implementation includes:
- Admin-created departments and job titles with salary rates.
- Employee resume profile with photo upload.
- Employee document tracking for Birth Certificate, NBI, Police Clearance, Medical, National ID, and License.
- Employee office/field categories.
- Printable employee profile and employee ID preview.
- Calendar-style attendance with configurable late/overtime settings.
- Payroll preview and saved payroll period based on attendance status.

The PHP workspace API also performs guarded database upgrades automatically when `workspace.php` loads.
