<div id="proposalLetterModal" class="proposal-letter-modal" hidden>
  <div class="plm-panel">
    <header class="pl-appbar no-print">
      <div class="pl-title-block">
        <h2>Proposal Letter</h2>
        <p id="plProposalMeta">Select a proposal</p>
      </div>
      <span id="plStatusBadge" class="pl-status-badge">Draft ready</span>
      <label class="pl-field compact">
        <span>Paper Size</span>
        <select id="plPaperSize">
          <option>A4</option>
          <option>Letter</option>
          <option>Legal</option>
          <option>Short Bond Paper</option>
          <option>Long Bond Paper</option>
        </select>
      </label>
      <label class="pl-field wide">
        <span>Template</span>
        <select id="plTemplate">
          <option>Residential Construction Proposal</option>
          <option>Commercial Construction Proposal</option>
          <option>Renovation Proposal</option>
          <option>Interior Fit-Out Proposal</option>
          <option>Design and Build Proposal</option>
          <option>General Contractor Proposal</option>
          <option>Custom Template</option>
        </select>
      </label>
      <label class="pl-field compact">
        <span>Zoom</span>
        <div class="pl-zoom-control">
          <button id="plZoomOut" type="button" class="pl-zoom-btn" title="Zoom out">-</button>
          <select id="plZoom">
            <option value="fit">Fit Width</option>
            <option value="0.75">75%</option>
            <option value="0.9">90%</option>
            <option value="1">100%</option>
            <option value="1.1">110%</option>
            <option value="1.25">125%</option>
            <option value="1.5">150%</option>
          </select>
          <button id="plZoomIn" type="button" class="pl-zoom-btn" title="Zoom in">+</button>
        </div>
      </label>
      <div class="pl-app-actions">
        <button id="plSaveDraft" type="button" class="pl-btn secondary">Save Draft</button>
        <button id="plSaveFinal" type="button" class="pl-btn success">Save Final</button>
        <button id="plPreview" type="button" class="pl-btn secondary">Print Preview</button>
        <button id="plPrint" type="button" class="pl-btn primary">Print</button>
        <button data-plm-close type="button" class="pl-btn dark">Close</button>
      </div>
    </header>

    <div class="pl-previewbar no-print">
      <button id="plBackToEdit" type="button" class="pl-btn secondary">Back to Edit</button>
      <span class="pl-previewbar-label">Print Preview</span>
      <label class="pl-field compact">
        <span>Paper Size</span>
        <select id="plPreviewPaperSize">
          <option>A4</option>
          <option>Letter</option>
          <option>Legal</option>
          <option>Short Bond Paper</option>
          <option>Long Bond Paper</option>
        </select>
      </label>
      <button id="plPreviewPrint" type="button" class="pl-btn primary">Print</button>
    </div>

    <nav class="pl-formatbar no-print" aria-label="Document formatting toolbar">
      <div class="pl-toolbar-group">
        <span class="pl-toolbar-label">Font</span>
        <select id="plFontFamily" class="pl-tool-select" title="Font family">
          <option value="Arial">Arial</option>
          <option value="Calibri">Calibri</option>
          <option value="Times New Roman">Times New Roman</option>
          <option value="Georgia">Georgia</option>
          <option value="Verdana">Verdana</option>
        </select>
        <select id="plFontSize" class="pl-tool-select" title="Font size">
          <option value="12px">12</option>
          <option value="13px">13</option>
          <option value="14px">14</option>
          <option value="16px">16</option>
          <option value="18px">18</option>
          <option value="20px">20</option>
          <option value="24px">24</option>
        </select>
      </div>
      <div class="pl-toolbar-group">
        <span class="pl-toolbar-label">Style</span>
        <button type="button" class="pl-tool-btn" data-pl-command="bold" title="Bold"><strong>B</strong></button>
        <button type="button" class="pl-tool-btn" data-pl-command="italic" title="Italic"><em>I</em></button>
        <button type="button" class="pl-tool-btn" data-pl-command="underline" title="Underline"><u>U</u></button>
        <button type="button" class="pl-tool-btn" data-pl-command="strikeThrough" title="Strikethrough"><s>S</s></button>
        <button type="button" class="pl-tool-btn wide" data-pl-command="removeFormat" title="Clear formatting">Clear</button>
      </div>
      <div class="pl-toolbar-group">
        <span class="pl-toolbar-label">Color</span>
        <label class="pl-color-field" title="Text color">
          <span>Text</span>
          <input type="color" id="plTextColor" class="pl-tool-color" value="#111827">
        </label>
        <label class="pl-color-field" title="Highlight color">
          <span>Highlight</span>
          <input type="color" id="plHighlightColor" class="pl-tool-color" value="#fff3a3">
        </label>
      </div>
      <div class="pl-toolbar-group">
        <span class="pl-toolbar-label">Paragraph</span>
        <button type="button" class="pl-tool-btn" data-pl-command="justifyLeft" title="Align left">L</button>
        <button type="button" class="pl-tool-btn" data-pl-command="justifyCenter" title="Align center">C</button>
        <button type="button" class="pl-tool-btn" data-pl-command="justifyRight" title="Align right">R</button>
        <button type="button" class="pl-tool-btn" data-pl-command="justifyFull" title="Justify">J</button>
        <button type="button" class="pl-tool-btn wide" data-pl-command="insertUnorderedList" title="Bulleted list">Bullets</button>
        <button type="button" class="pl-tool-btn wide" data-pl-command="insertOrderedList" title="Numbered list">Numbers</button>
        <button type="button" class="pl-tool-btn" data-pl-command="outdent" title="Outdent">-</button>
        <button type="button" class="pl-tool-btn" data-pl-command="indent" title="Indent">+</button>
      </div>
      <div class="pl-toolbar-group">
        <span class="pl-toolbar-label">Insert</span>
        <button id="plInsertRule" type="button" class="pl-tool-btn wide" title="Insert line">Line</button>
        <button id="plInsertTable" type="button" class="pl-tool-btn wide" title="Insert table">Table</button>
        <button id="plAddTableRow" type="button" class="pl-tool-btn wide" title="Add table row">Row</button>
        <button id="plAddTableColumn" type="button" class="pl-tool-btn wide" title="Add table column">Column</button>
      </div>
      <div class="pl-toolbar-group">
        <span class="pl-toolbar-label">History</span>
        <button type="button" class="pl-tool-btn wide" data-pl-command="undo" title="Undo">Undo</button>
        <button type="button" class="pl-tool-btn wide" data-pl-command="redo" title="Redo">Redo</button>
      </div>
      <div class="pl-toolbar-group">
        <span class="pl-toolbar-label">Tools</span>
        <button id="plResetTemplate" type="button" class="pl-tool-btn wide" title="Reset from proposal data">Reset</button>
        <button id="plToggleLetterhead" type="button" class="pl-tool-btn wide" title="Show or hide letterhead settings">Letterhead</button>
        <button id="plToggleDetailsPanel" type="button" class="pl-tool-btn wide" title="Hide details panel">Hide Details</button>
        <button id="plToggleHistoryPanel" type="button" class="pl-tool-btn wide" title="Hide saved letters">Hide History</button>
      </div>
    </nav>

    <main class="pl-workbench">
      <aside class="pl-side-panel pl-details-panel no-print">
        <div class="pl-panel-header">
          <div>
            <h3>Letter Details</h3>
            <p>Keep the document focused while you prepare it for printing.</p>
          </div>
        </div>
        <div class="pl-info-card">
          <span class="pl-info-label">Proposal</span>
          <strong id="plInfoProposalNumber">Not selected</strong>
          <span id="plInfoProjectName" class="pl-info-muted">Choose a proposal to begin.</span>
        </div>
        <div class="pl-info-card">
          <span class="pl-info-label">Tips</span>
          <span class="pl-info-muted">Use the toolbar for formatting, switch paper size as needed, then preview before printing.</span>
        </div>
        <section class="pl-collapsible" data-open="false">
          <button id="plToggleLetterheadPanel" type="button" class="pl-collapse-btn">
            <span>Letterhead Settings</span>
            <span class="pl-collapse-indicator">+</span>
          </button>
          <div class="pl-collapsible-body">
            <label class="pl-side-field">
              <span>Header Mode</span>
              <select id="plHeaderMode">
                <option value="text">Text Header</option>
                <option value="image">PNG Header</option>
              </select>
            </label>
            <label class="pl-side-field">
              <span>Header Title</span>
              <input id="plHeaderTitle" type="text" placeholder="Maorin Builders">
            </label>
            <label class="pl-side-field">
              <span>Header Subtitle</span>
              <input id="plHeaderSubtitle" type="text" placeholder="Construction - Renovation - Design & Build">
            </label>
            <label class="pl-side-field">
              <span>Header Line 1</span>
              <input id="plHeaderLine1" type="text" placeholder="Address - Contact Number - Email">
            </label>
            <label class="pl-side-field">
              <span>Header Line 2</span>
              <input id="plHeaderLine2" type="text" placeholder="Additional details">
            </label>
            <label class="pl-side-field">
              <span>Header PNG</span>
              <label class="pl-upload-btn" for="plHeaderImage">Upload PNG</label>
              <input id="plHeaderImage" type="file" accept="image/png" hidden>
              <input type="hidden" id="plExistingHeaderImagePath" value="">
            </label>
            <label class="pl-check-field">
              <input id="plShowHeader" type="checkbox" checked>
              <span>Show header</span>
            </label>
          </div>
        </section>
      </aside>

      <section class="pl-canvas-wrap">
        <?php include __DIR__.'/proposal_letter_sheet.php'; ?>
      </section>

      <aside class="pl-side-panel pl-history-panel no-print">
        <div class="pl-panel-header">
          <div>
            <h3>Saved Letters</h3>
            <p>Open, print, or duplicate earlier versions.</p>
          </div>
        </div>
        <div id="plHistory"></div>
      </aside>
    </main>
  </div>
</div>
