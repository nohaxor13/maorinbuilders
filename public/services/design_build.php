<?php
$service = [
  'name' => 'Design & build packages',
  'desc' => 'One coordinated team for concept, planning, costing, and construction — faster alignment, fewer handoffs, and clearer accountability.',
  'estimate_range' => 'Package-based (depends on size & finish level)',
  'timeline_note' => 'Varies (typical: 4–8 weeks planning + construction schedule)',
  'included' => [
    'Concept + layout ideation (based on requirements)',
    'Budget alignment and value engineering options',
    'Plans + bill of materials (project dependent)',
    'Permitting support (as required)',
    'Construction execution with supervision + QA/QC',
    'Progress updates and documented change orders',
  ],
  'timeline' => [
    'Discovery + requirements gathering',
    'Concept + budget alignment',
    'Final plans + costing',
    'Procurement + mobilization',
    'Construction + milestones',
    'Turnover + warranty/after-care (if included)',
  ],
  'notes' => 'Recommended when you want one team responsible from design to turnover. Reduces delays caused by mismatched plans and site realities.',
];

require __DIR__ . '/_service_page.php';
