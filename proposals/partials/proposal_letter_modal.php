<div id="proposalLetterModal" class="proposal-letter-modal no-print" hidden>
  <div class="plm-panel">
    <button class="plm-close" data-plm-close>&times;</button>
    <div class="plm-header">
      <div>
        <h2>Proposal Letter</h2>
        <p>Minimal print-ready layout.</p>
      </div>
      <select id="plPaperSize">
        <option>A4</option><option>Letter</option><option>Legal</option><option>Short Bond Paper</option><option>Long Bond Paper</option>
      </select>
    </div>
    <div class="pl-toolbar no-print">
      <button id="plToggleHeader" type="button">Hide Header</button>
      <select id="plHeaderMode">
        <option value="text">Text Header</option>
        <option value="image">PNG Header</option>
      </select>
      <label class="pl-toolbar-upload" for="plHeaderImage">Upload PNG</label>
      <input id="plHeaderImage" type="file" accept="image/png" hidden>
      <input type="hidden" id="plExistingHeaderImagePath" value="">
      <select id="plTemplate">
        <option>Residential Construction Proposal</option><option>Commercial Construction Proposal</option><option>Renovation Proposal</option><option>Interior Fit-Out Proposal</option><option>Design and Build Proposal</option><option>General Contractor Proposal</option><option>Custom Template</option>
      </select>
      <button id="plResetTemplate" type="button">Reset</button>
      <button id="plSaveDraft" type="button">Save Draft</button>
      <button id="plSaveFinal" type="button">Save Final</button>
      <button id="plPrint" type="button">Print</button>
    </div>
    <?php include __DIR__.'/proposal_letter_sheet.php'; ?>
    <div id="plHistory" class="pl-history"></div>
  </div>
</div>
