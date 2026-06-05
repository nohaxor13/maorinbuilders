<?php
$service = [
  'name' => 'Residential construction',
  'desc' => 'New builds, extensions, and structural works for houses and townhomes — planned, costed, and executed with proper supervision.',
  'estimate_range' => '₱25k–₱45k / sqm (typical, varies by finish level & location)',
  'timeline_note' => '12–24 weeks (typical)',
  'included' => [
    'Site visit + scope confirmation',
    'Bill of materials + itemized quotation',
    'Construction schedule + milestone checklist',
    'Daily/weekly supervision & quality checks',
    'Progress photo updates (optional client portal)',
    'Turnover punchlist & final inspection',
  ],
  'timeline' => [
    'Site visit and requirements gathering (1–3 days)',
    'Plans review / final scope + quotation (3–7 days)',
    'Mobilization + site preparation (3–7 days)',
    'Structure / shell works (4–10 weeks)',
    'MEP rough-ins (electrical/plumbing) (2–4 weeks)',
    'Finishing works + punchlist (3–6 weeks)',
  ],
  'notes' => 'We recommend a clear finish level (basic/standard/premium) and design/plan availability for more accurate costing.',
];

require __DIR__ . '/_service_page.php';
