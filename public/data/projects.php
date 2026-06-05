<?php
// public/data/projects.php
// Fallback starter dataset. Public pages prefer DB-backed content when available.

return [
  [
    'id' => 'p1',
    'title' => 'Modern 2‑Storey Residence',
    'location' => 'Cavite, PH',
    'year' => '2025',
    'type' => 'Residential',
    'status' => 'Completed',
    'cover' => '/assets/img/projects/residence.svg',
    'before' => '/assets/img/projects/p1_before.svg',
    'after' => '/assets/img/projects/p1_after.svg',
    'materials' => ['Reinforced concrete', 'Steel rebar', 'Standard finish package'],
    'summary' => 'A clean modern home with durable finishes and efficient space planning.',
    'gallery' => [
      '/assets/img/projects/residence.svg',
      '/assets/img/projects/p1_after.svg',
    ],
  ],
  [
    'id' => 'p2',
    'title' => 'Warehouse Renovation & Re‑layout',
    'location' => 'Laguna, PH',
    'year' => '2025',
    'type' => 'Renovation',
    'status' => 'Ongoing',
    'cover' => '/assets/img/projects/warehouse.svg',
    'before' => '/assets/img/projects/p2_before.svg',
    'after' => '/assets/img/projects/p2_after.svg',
    'materials' => ['Epoxy floor system', 'Steel framing', 'Roofing repairs'],
    'summary' => 'Structural reinforcement + optimized storage layout for faster operations.',
    'gallery' => [
      '/assets/img/projects/warehouse.svg',
      '/assets/img/projects/p2_after.svg',
    ],
  ],
  [
    'id' => 'p3',
    'title' => 'Commercial Fit‑Out (Retail)',
    'location' => 'Quezon City, PH',
    'year' => '2024',
    'type' => 'Commercial',
    'status' => 'Completed',
    'cover' => '/assets/img/projects/retail.svg',
    'before' => '/assets/img/projects/p3_before.svg',
    'after' => '/assets/img/projects/p3_after.svg',
    'materials' => ['Gypsum boards', 'Lighting & electrical', 'Fire safety compliance'],
    'summary' => 'Turnkey fit‑out including electrical, finishes, and safety compliance.',
    'gallery' => [
      '/assets/img/projects/retail.svg',
      '/assets/img/projects/p3_after.svg',
    ],
  ],
];
