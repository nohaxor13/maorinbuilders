<?php
// public/data/company.php
// Centralized company profile data for the public website.
// Update these placeholders with real details when ready.

return [
  'name' => 'Maorin Builders & Supply',
  'tagline' => 'Construction • Renovation • Design & Build',

  // --- Company profile ---
  'profile' => [
    'history' => [
      'title' => 'Our story',
      'text'  => 'Maorin Builders & Supplyis a Philippine-based construction team focused on delivering safe, durable builds with clear scope, reliable timelines, and transparent costing.'
    ],
    'mission' => 'Deliver quality construction work through disciplined planning, skilled execution, and honest communication.',
    'values' => [
      'Safety-first on every site',
      'Clear scope and documented changes',
      'Quality control checkpoints',
      'Respect for clients, neighbors, and timelines',
    ],
    'experience' => [
      'years' => '5+ years',
      'specialties' => [
        'Residential construction',
        'Commercial buildings & warehouses',
        'Renovation / remodeling',
        'Design & build packages',
      ],
    ],
    'licenses' => [
      // Replace with real numbers / documents
      ['label' => 'DTI/SEC Registration', 'value' => 'To be added'],
      ['label' => 'Mayor\'s Permit',     'value' => 'To be added'],
      ['label' => 'PCAB License',        'value' => 'To be added'],
      ['label' => 'BIR Registration',    'value' => 'To be added'],
    ],
    'safety' => [
      'We follow jobsite safety standards including PPE compliance, housekeeping, hazard identification, and daily toolbox meetings as applicable.',
      'We keep documentation for work permits, safety briefings, and inspection checkpoints to reduce risk and ensure quality.',
    ],
  ],

  // --- Contact ---
  'contact' => [
    'phone' => '+63 9304547614',
    'email' => 'maorinbuilders23@gmail.com',
    'address' => 'B.9 L.1 Malong Vinta Street Emivill Subd. Sasa Davao City',
    'office_hours' => [
      'Mon–Sat: 8:00 AM – 5:00 PM',
      'Sun: By appointment',
    ],

    // WhatsApp uses international format without spaces for best results
    'whatsapp_number' => '+63 9304547614',
    // Messenger: https://m.me/<page_or_username>
    'messenger_username' => 'MaorinBuilders',

    // Google Maps embed URL (replace with your pinned location)
    'map_embed_url' => 'https://www.google.com/maps?q=7.140785406644884,125.65552576134661&z=18&t=k&output=embed',
  ],
];
