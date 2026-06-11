<?php
if (!function_exists('mb_money')) {
  function mb_money($v){ return number_format((float)$v, 2); }
}
function mb_proposal_templates(){ return ['Residential Construction Proposal','Commercial Construction Proposal','Renovation Proposal','Interior Fit-Out Proposal','Design and Build Proposal','General Contractor Proposal','Custom Template']; }
function mb_proposal_letter_body_blocks(array $d, string $type='Residential Construction Proposal'): array {
  $g = function(string $k, string $def='') use ($d) { return htmlspecialchars((string)($d[$k] ?? $def), ENT_QUOTES, 'UTF-8'); };
  $money = function(string $k, string $def='0.00') use ($d) { return htmlspecialchars(mb_money($d[$k] ?? $def), ENT_QUOTES, 'UTF-8'); };
  $overview = [
    ['Proposal No.', $g('proposal_number')],
    ['Project Code', $g('project_code')],
    ['Project Name', $g('project_name')],
    ['Project Type', $g('project_type')],
    ['Location', $g('project_location')],
    ['Estimate No.', $g('estimate_no')],
  ];
  return [
    '<p>Date: '.$g('date_prepared', date('F d, Y')).'</p>',
    '<p><strong>'.$g('client_name','Client Name').'</strong><br>'.$g('client_company').'<br>'.$g('client_address').'</p>',
    '<p><strong>Subject:</strong> Proposal for '.$g('project_name','Project Name').'</p>',
    '<p>Dear '.$g('client_name','Client').',</p>',
    '<p>Greetings from Maorin Builders.</p>',
    '<p>We are pleased to submit our proposal for the project entitled <strong>'.$g('project_name','Project Name').'</strong> located at <strong>'.$g('project_location','Project Location').'</strong>.</p>',
    '<h3>Project Overview</h3><table><tbody>'.implode('', array_map(fn($r) => '<tr><td><strong>'.$r[0].'</strong></td><td>'.$r[1].'</td></tr>', $overview)).'<tr><td><strong>Estimated Duration</strong></td><td>'.$g('estimated_duration','To be discussed').'</td></tr></tbody></table>',
    '<h3>Scope of Work</h3><p>'.$g('scope_of_work','To be finalized based on approved plans, site conditions, and client requirements.').'</p>',
    '<h3>Project Cost Summary</h3><table class="proposal-cost-table"><thead><tr><th>Description</th><th>Amount</th></tr></thead><tbody>'
      .'<tr><td>Materials Cost</td><td>P'.$money('materials_cost').'</td></tr>'
      .'<tr><td>Labor Cost</td><td>P'.$money('labor_cost').'</td></tr>'
      .'<tr><td>Equipment Cost</td><td>P'.$money('equipment_cost').'</td></tr>'
      .'<tr><td>Professional Fee</td><td>P'.$money('professional_fee').'</td></tr>'
      .'<tr><td>Other Charges</td><td>P'.$money('other_charges').'</td></tr>'
      .'<tr><td><strong>Total Project Cost</strong></td><td><strong>P'.$money('total_amount').'</strong></td></tr>'
      .'</tbody></table>',
    $g('estimate_no') ? '<h3>Estimate Reference</h3><p>Based on estimate <strong>'.$g('estimate_no').'</strong> with total <strong>P'.$money('estimate_total').'</strong>.</p>' : '',
    '<h3>Payment Terms</h3><p>'.$g('payment_terms','Payment terms shall be discussed and finalized upon approval of this proposal.').'</p>',
    $g('exclusions') ? '<h3>Exclusions</h3><p>'.$g('exclusions').'</p>' : '',
    '<h3>Validity of Proposal</h3><p>This proposal shall remain valid until <strong>'.$g('validity_date','the stated validity date').'</strong>.</p>',
    '<p>Any significant changes in material prices, labor rates, project requirements, site conditions, or government regulations may require adjustment of the proposed cost.</p>',
    '<p>We appreciate the opportunity to submit this proposal and look forward to working with you.</p>',
    '<p>Respectfully yours,<br><br><strong>'.$g('prepared_by','Prepared By').'</strong><br>Maorin Builders</p>',
    '<hr><h3>Client Acceptance</h3><p>Approved By:</p><p>_________________________________<br>Name / Signature / Date</p>',
  ];
}
function mb_generate_proposal_letter_html($d, $type='Residential Construction Proposal'){
  return implode('', mb_proposal_letter_body_blocks($d, $type));
}
