<?php
$service = [
  'name' => 'Project management',
  'desc' => 'Planning, coordination, progress reporting, and quality control — keeping your build on schedule with fewer surprises.',
  'estimate_range' => 'By project size (management fee)',
  'timeline_note' => 'Ongoing (project duration)',
  'included' => [
    'Work breakdown, schedule, and milestones',
    'Subcontractor coordination and site supervision',
    'Weekly progress updates (photos + summary)',
    'QA/QC checkpoints and punch-list management',
    'Change order documentation and cost tracking',
    'Turnover checklist and final inspection support',
  ],
  'timeline' => [
    'Kickoff + scope confirmation',
    'Baseline schedule + procurement plan',
    'Ongoing supervision + reporting',
    'Quality checks + snag/punch list',
    'Turnover + documentation',
  ],
  'notes' => 'Best for clients who want strong oversight, documentation, and predictable delivery — even when multiple trades are involved.',
];

require __DIR__ . '/_service_page.php';
