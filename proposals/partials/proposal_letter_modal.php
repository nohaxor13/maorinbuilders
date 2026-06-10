<div id="proposalLetterModal" class="proposal-letter-modal no-print" hidden>
  <div class="plm-panel"><button class="plm-close" data-plm-close>&times;</button>
    <div class="plm-header"><div><h2>Proposal Letter</h2><p>Create a printable Maorin Builders letter.</p></div><div><select id="plPaperSize"><option>A4</option><option>Letter</option><option>Legal</option><option>Short Bond Paper</option><option>Long Bond Paper</option></select></div></div>
    <?php include __DIR__.'/proposal_letter_toolbar.php'; ?>
    <div class="plm-actions"><select id="plTemplate"><option>Residential Construction Proposal</option><option>Commercial Construction Proposal</option><option>Renovation Proposal</option><option>Interior Fit-Out Proposal</option><option>Design and Build Proposal</option><option>General Contractor Proposal</option><option>Custom Template</option></select><button id="plResetTemplate">Reset from Proposal Data</button><button id="plSaveDraft">Save Draft</button><button id="plSaveFinal">Save Final</button><button id="plPrint">Print</button></div>
    <?php include __DIR__.'/proposal_letter_sheet.php'; ?>
    <div id="plHistory" class="pl-history"></div>
  </div>
</div>
