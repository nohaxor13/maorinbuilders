<?php
$service = [
  'name' => 'Renovation / remodeling',
  'desc' => 'Upgrades, room conversions, waterproofing, re-tiling, repainting, and repair works — with proper inspection and clear change-order handling.',
  'estimate_range' => '₱200k–₱3M+ (typical; depends on scope)',
  'timeline_note' => '2–10 weeks (typical)',
  'included' => [
    'Site inspection + assessment of existing conditions',
    'Scope breakdown + itemized quotation',
    'Protection of existing areas (covering, dust control) as needed',
    'Demolition and haul-out (if required)',
    'Repairs, waterproofing, electrical/plumbing adjustments (as scoped)',
    'Finishes: paint, tiles, ceilings, cabinetry (as scoped)',
    'Punchlist + final turnover',
  ],
  'timeline' => [
    'Week 1: Inspection + scope confirmation + quotation',
    'Week 1–2: Mobilization + protection + demolition (if needed)',
    'Week 2–6: Main works (repairs / MEP / waterproofing)',
    'Week 4–9: Finishes + installation',
    'Final: Punchlist + cleaning + turnover',
  ],
  'notes' => 'Renovations often reveal hidden issues (leaks, wiring, termites). We document findings and propose options before proceeding.',
];

require __DIR__ . '/_service_page.php';
