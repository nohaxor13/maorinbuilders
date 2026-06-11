<?php
header('Content-Type: text/plain; charset=UTF-8');

echo "Maorin Builders HR / Payroll manual checklist\n";
echo "==========================================\n\n";

$cases = [
  'Daily employee present whole day',
  'Daily employee late 30 minutes with 2 hours OT',
  'Monthly employee full attendance for 26 days',
  'Monthly employee absent 2 days',
  'Weekly employee worked 6 days',
  'Hourly employee worked 7 regular hours and 2 OT hours',
  'Half-day employee',
  'Absent employee',
  'Paid leave and unpaid leave',
  'Project-rate employee should not auto-compute',
];

foreach ($cases as $index => $case) {
    echo ($index + 1) . ". " . $case . "\n";
}

echo "\nVerify in workspace:\n";
echo "- Attendance save stores status, late_minutes, worked_hours, regular_hours, overtime_hours, payable_day.\n";
echo "- Payroll preview shows rate type, base rate, daily/hourly conversion, payable days, regular/OT pay, gross and net.\n";
echo "- Saving a payroll period rebuilds items from attendance and updates period gross pay.\n";
