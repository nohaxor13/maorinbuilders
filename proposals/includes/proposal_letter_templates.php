<?php
function mb_money($v){ return number_format((float)$v, 2); }
function mb_proposal_templates(){ return ['Residential Construction Proposal','Commercial Construction Proposal','Renovation Proposal','Interior Fit-Out Proposal','Design and Build Proposal','General Contractor Proposal','Custom Template']; }
function mb_generate_proposal_letter_html($d, $type='Residential Construction Proposal'){
  $g=function($k,$def=''){ return htmlspecialchars((string)($GLOBALS['__tpl'][$k] ?? $def), ENT_QUOTES, 'UTF-8'); };
  $GLOBALS['__tpl']=$d;
  $scope=$g('scope_of_work','To be finalized based on approved plans, site conditions, and client requirements.');
  return '<section class="letter-section">'
  .'<p>Date: '.$g('date_prepared',date('F d, Y')).'</p>'
  .'<p><strong>'.$g('client_name','Client Name').'</strong><br>'.$g('client_company').'<br>'.$g('client_address').'</p>'
  .'<p><strong>Subject:</strong> Proposal for '.$g('project_name','Project Name').'</p>'
  .'<p>Dear '.$g('client_name','Client').',</p>'
  .'<p>Greetings from Maorin Builders.</p>'
  .'<p>We are pleased to submit our proposal for the project entitled <strong>'.$g('project_name','Project Name').'</strong> located at <strong>'.$g('project_location','Project Location').'</strong>.</p>'
  .'<h3>Project Overview</h3><table><tr><td><strong>Project Name</strong></td><td>'.$g('project_name').'</td></tr><tr><td><strong>Project Location</strong></td><td>'.$g('project_location').'</td></tr><tr><td><strong>Estimated Duration</strong></td><td>'.$g('estimated_duration','To be discussed').'</td></tr></table>'
  .'<h3>Scope of Work</h3><p>'.$scope.'</p>'
  .'<h3>Project Cost Summary</h3><table class="proposal-cost-table"><thead><tr><th>Description</th><th>Amount</th></tr></thead><tbody>'
  .'<tr><td>Materials Cost</td><td>₱'.$g('materials_cost','0.00').'</td></tr><tr><td>Labor Cost</td><td>₱'.$g('labor_cost','0.00').'</td></tr><tr><td>Equipment Cost</td><td>₱'.$g('equipment_cost','0.00').'</td></tr><tr><td>Professional Fee</td><td>₱'.$g('professional_fee','0.00').'</td></tr><tr><td>Other Charges</td><td>₱'.$g('other_charges','0.00').'</td></tr><tr><td><strong>Total Project Cost</strong></td><td><strong>₱'.$g('total_amount','0.00').'</strong></td></tr></tbody></table>'
  .'<h3>Payment Terms</h3><p>'.$g('payment_terms','Payment terms shall be discussed and finalized upon approval of this proposal.').'</p>'
  .'<h3>Validity of Proposal</h3><p>This proposal shall remain valid until <strong>'.$g('validity_date','the stated validity date').'</strong>.</p>'
  .'<p>Any significant changes in material prices, labor rates, project requirements, site conditions, or government regulations may require adjustment of the proposed cost.</p>'
  .'<p>We appreciate the opportunity to submit this proposal and look forward to working with you.</p>'
  .'<p>Respectfully yours,<br><br><strong>'.$g('prepared_by','Prepared By').'</strong><br>Maorin Builders</p><hr><h3>Client Acceptance</h3><p>Approved By:</p><p>_________________________________<br>Name / Signature / Date</p></section>';
}
