<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../includes/estimator_system.php';
require_feature($pdo, 'public_site');
mb_estimator_bootstrap($pdo);
$title = 'Project Cost Estimator';
$company = require __DIR__ . '/data/company.php';
$estimatorSettings = mb_estimator_settings($pdo);
include __DIR__ . '/templates/header.php';
?>
<main class="container py-4 py-lg-5 estimator-page">
  <section class="estimator-hero card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-body p-4 p-lg-5">
      <div class="row g-4 align-items-center">
        <div class="col-lg-7">
          <div class="text-uppercase small fw-semibold text-success mb-2">Public Project Cost Estimator</div>
          <h1 class="display-6 fw-bold mb-3">Plan your project with a guided preliminary estimate.</h1>
          <p class="text-secondary mb-3"><?= htmlspecialchars($estimatorSettings['intro_text'], ENT_QUOTES, 'UTF-8') ?></p>
          <div class="alert alert-warning border-0 rounded-4 mb-0">
            <strong>Important:</strong> <?= htmlspecialchars($estimatorSettings['public_disclaimer_text'], ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="estimator-hero-card">
            <div class="hero-pill">Visitor and client friendly</div>
            <ul class="list-unstyled mb-0 small text-secondary d-grid gap-2">
              <li>Step-by-step measurement and scope wizard</li>
              <li>Manual entry always available as fallback</li>
              <li>Optional sketch-based area and perimeter tool</li>
              <li>Preliminary range only, never a fake final quotation</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div id="estimatorApp" class="estimator-shell"
       data-config-url="<?= htmlspecialchars(pub_url('/public/api/estimator_config.php'), ENT_QUOTES, 'UTF-8') ?>"
       data-calculate-url="<?= htmlspecialchars(pub_url('/public/api/estimator_calculate.php'), ENT_QUOTES, 'UTF-8') ?>"
       data-submit-url="<?= htmlspecialchars(pub_url('/public/api/estimator_submit.php'), ENT_QUOTES, 'UTF-8') ?>"
       data-contact-url="<?= htmlspecialchars(pub_url('/public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row gap-4">
          <aside class="estimator-progress">
            <div class="small text-uppercase fw-semibold text-secondary mb-3">Estimator Steps</div>
            <ol class="list-unstyled mb-0" id="estimatorStepList"></ol>
            <div class="estimator-summary-card mt-4">
              <div class="fw-semibold mb-2">Live Summary</div>
              <div id="estimatorMiniSummary" class="small text-secondary">Select your project type to begin.</div>
            </div>
          </aside>

          <section class="flex-grow-1">
            <div id="estimatorAlert" class="alert d-none"></div>
            <div id="estimatorLoading" class="text-center text-secondary py-5">Loading estimator configuration...</div>
            <form id="estimatorWizard" class="d-none" enctype="multipart/form-data">
              <div id="estimatorStepHost"></div>
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="estimatorPrev">Back</button>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-outline-primary rounded-pill px-4" id="estimatorCalc">Preview Estimate</button>
                  <button type="button" class="btn btn-primary rounded-pill px-4" id="estimatorNext">Next</button>
                </div>
              </div>
            </form>
          </section>
        </div>
      </div>
    </div>
  </div>
</main>

<style>
.estimator-page .estimator-hero{background:linear-gradient(135deg,#ffffff,#f5fbf6)}
.estimator-hero-card{background:linear-gradient(135deg,rgba(8,67,22,.06),rgba(228,206,25,.12));border:1px solid rgba(8,67,22,.1);border-radius:1.5rem;padding:1.25rem}
.hero-pill{display:inline-flex;padding:.45rem .8rem;border-radius:999px;background:#0a4b1a;color:#fff;font-size:.8rem;font-weight:700;margin-bottom:1rem}
.estimator-shell .estimator-progress{width:280px;max-width:100%;flex:0 0 280px}
.estimator-step-item{display:flex;gap:.85rem;padding:.75rem 0;border-bottom:1px solid rgba(15,23,42,.08)}
.estimator-step-index{width:2rem;height:2rem;border-radius:999px;background:#e8efe9;color:#0a4b1a;display:flex;align-items:center;justify-content:center;font-weight:800;flex:none}
.estimator-step-item.active .estimator-step-index{background:#0a4b1a;color:#fff}
.estimator-step-item.done .estimator-step-index{background:#e4ce19;color:#0b1220}
.estimator-summary-card{padding:1rem;border-radius:1.25rem;background:#f8fafc;border:1px solid rgba(15,23,42,.08)}
.wizard-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.wizard-grid .full{grid-column:1/-1}
.wizard-card-option{display:block;border:1px solid rgba(15,23,42,.12);border-radius:1.25rem;padding:1rem;background:#fff;cursor:pointer;height:100%}
.wizard-card-option.active{border-color:#0a4b1a;box-shadow:0 0 0 .2rem rgba(8,67,22,.08)}
.wizard-card-option input{display:none}
.wizard-card-option .title{font-weight:800;color:#0b1220}
.wizard-card-option .meta{font-size:.85rem;color:#5b677a}
.estimator-result-card{border:1px solid rgba(15,23,42,.08);border-radius:1.25rem;background:#fff;padding:1rem}
.estimator-price-range{font-size:2rem;font-weight:900;color:#0a4b1a}
.estimator-inline-list{display:flex;flex-wrap:wrap;gap:.5rem}
.estimator-inline-list span{background:#f1f5f9;border-radius:999px;padding:.35rem .7rem;font-size:.82rem}
.sketch-toolbar{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}
.sketch-toolbar .form-select,.sketch-toolbar .form-control,.sketch-toolbar .btn{border-radius:999px}
.sketch-canvas-wrap{position:relative;border:1px solid rgba(15,23,42,.12);border-radius:1.25rem;overflow:hidden;background-image:linear-gradient(to right,rgba(15,23,42,.05) 1px,transparent 1px),linear-gradient(to bottom,rgba(15,23,42,.05) 1px,transparent 1px);background-size:24px 24px;background-color:#fff}
.sketch-canvas-wrap canvas{display:block;width:100%;max-width:100%}
.sketch-disclaimer{font-size:.82rem;background:#fff8e6;border:1px solid rgba(228,206,25,.35);color:#6f5300;border-radius:1rem;padding:.85rem}
@media (max-width: 992px){.estimator-shell .estimator-progress{width:100%;flex-basis:auto}.wizard-grid{grid-template-columns:1fr}}
</style>

<script src="https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js" integrity="sha256-SPjwkVvrUS/H/htIwO6wdd0IA8eQ79/XXNAH+cPuoso=" crossorigin="anonymous"></script>
<script>
(function(){
  const app=document.getElementById('estimatorApp');
  if(!app){return;}
  const state={
    config:null,
    step:0,
    csrf:'',
    result:null,
    drawing:null,
    payload:{
      project_type_id:0,
      measurement_method:'manual',
      finish_level_id:0,
      scope_item_ids:[],
      site_condition_ids:[],
      timeline_rule_id:0,
      city:'',
      barangay:'',
      project_address:'',
      manual:{},
      drawing:{},
      lead:{preferred_contact_method:'Call'}
    }
  };
  const steps=[
    {key:'intro',title:'Intro',desc:'Understand what this estimator covers.'},
    {key:'project',title:'Project Type',desc:'Choose the kind of project you are planning.'},
    {key:'measurement',title:'Measurement Method',desc:'Pick manual entry or sketch-based measurement.'},
    {key:'measurements',title:'Project Measurements',desc:'Enter your preliminary project dimensions.'},
    {key:'finish',title:'Finish Level',desc:'Select the finish quality you want to target.'},
    {key:'scope',title:'Scope Inclusions',desc:'Choose what to include in the estimate.'},
    {key:'site',title:'Location and Site',desc:'Tell us where the project is and its conditions.'},
    {key:'timeline',title:'Timeline',desc:'Set your preferred delivery pace.'},
    {key:'result',title:'Estimate Result',desc:'Review the preliminary estimate range.'},
    {key:'lead',title:'Submit Lead',desc:'Send the estimate to Maorin Builders.'}
  ];

  const alertBox=document.getElementById('estimatorAlert');
  const loading=document.getElementById('estimatorLoading');
  const form=document.getElementById('estimatorWizard');
  const stepHost=document.getElementById('estimatorStepHost');
  const stepList=document.getElementById('estimatorStepList');
  const miniSummary=document.getElementById('estimatorMiniSummary');
  const prevBtn=document.getElementById('estimatorPrev');
  const nextBtn=document.getElementById('estimatorNext');
  const calcBtn=document.getElementById('estimatorCalc');

  function h(s){return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
  function peso(v){return new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP',minimumFractionDigits:2}).format(Number(v||0));}
  function showAlert(type,msg){alertBox.className='alert alert-'+type;alertBox.textContent=msg;alertBox.classList.remove('d-none');}
  function clearAlert(){alertBox.classList.add('d-none');alertBox.textContent='';}
  function getProjectType(){return (state.config.projectTypes||[]).find(row=>Number(row.id)===Number(state.payload.project_type_id))||null;}
  function getFinishLevel(){return (state.config.finishLevels||[]).find(row=>Number(row.id)===Number(state.payload.finish_level_id))||null;}
  function getScopeItems(){return (state.config.scopeItems||[]).filter(row=>state.payload.scope_item_ids.includes(Number(row.id)));}
  function getTimelineRule(){return (state.config.timelineRules||[]).find(row=>Number(row.id)===Number(state.payload.timeline_rule_id))||null;}
  function updateSummary(){
    const project=getProjectType();
    const finish=getFinishLevel();
    const method=state.payload.measurement_method==='sketch'?'Sketch measurement':'Manual measurement';
    const parts=[];
    if(project){parts.push(project.name);}
    parts.push(method);
    if(finish){parts.push(finish.name);}
    if(state.result){parts.push(state.result.range_display);}
    miniSummary.innerHTML=parts.length ? h(parts.join(' | ')) : 'Select your project type to begin.';
  }
  function renderStepList(){
    stepList.innerHTML=steps.map((step,index)=>`<li class="estimator-step-item ${index===state.step?'active':''} ${index<state.step?'done':''}"><div class="estimator-step-index">${index+1}</div><div><div class="fw-semibold">${h(step.title)}</div><div class="small text-secondary">${h(step.desc)}</div></div></li>`).join('');
  }
  function cardOptions(rows,name,current,metaBuilder){
    return `<div class="wizard-grid">${rows.map(row=>`<label class="wizard-card-option ${Number(current)===Number(row.id)?'active':''}"><input type="radio" name="${h(name)}" value="${Number(row.id)}" ${Number(current)===Number(row.id)?'checked':''}><div class="title">${h(row.name)}</div><div class="meta">${metaBuilder(row)}</div></label>`).join('')}</div>`;
  }
  function measurementFields(){
    const project=getProjectType();
    if(!project){return '<div class="alert alert-info">Choose a project type first.</div>';}
    const manual=state.payload.manual||{};
    const isFence=String(project.measurement_type)==='linear_meter';
    const isRoof=String(project.slug)==='roofing';
    const isRenovation=['house-renovation','kitchen-renovation','bathroom-renovation','repair-maintenance'].includes(String(project.slug));
    return `
      <div class="wizard-grid">
        ${!isFence ? `<label>Floor area (sqm)<input class="form-control" type="number" step="0.01" data-field="manual.floor_area_sqm" value="${h(manual.floor_area_sqm||'')}"></label>`:''}
        <label>Number of floors<input class="form-control" type="number" min="1" step="1" data-field="manual.number_of_floors" value="${h(manual.number_of_floors||'1')}"></label>
        <label>Number of bedrooms<input class="form-control" type="number" min="0" step="1" data-field="manual.number_of_bedrooms" value="${h(manual.number_of_bedrooms||'')}"></label>
        <label>Number of bathrooms<input class="form-control" type="number" min="0" step="1" data-field="manual.number_of_bathrooms" value="${h(manual.number_of_bathrooms||'')}"></label>
        ${isFence ? `<label>Length in meters<input class="form-control" type="number" step="0.01" data-field="manual.length_m" value="${h(manual.length_m||'')}"></label>`:''}
        <label>Height when applicable (m)<input class="form-control" type="number" step="0.01" data-field="manual.height_m" value="${h(manual.height_m||'')}"></label>
        ${isRoof ? `<label>Roof area (sqm)<input class="form-control" type="number" step="0.01" data-field="manual.roof_area_sqm" value="${h(manual.roof_area_sqm||'')}"></label>`:''}
        ${isRenovation ? `<label>Affected renovation area (sqm)<input class="form-control" type="number" step="0.01" data-field="manual.affected_area_sqm" value="${h(manual.affected_area_sqm||'')}"></label>`:''}
      </div>`;
  }
  function resultHtml(){
    if(!state.result){
      return '<div class="alert alert-info">Use "Preview Estimate" after completing the measurement, finish, scope, site, and timeline steps.</div>';
    }
    return `
      <div class="estimator-result-card">
        <div class="small text-uppercase text-secondary fw-semibold mb-2">Preliminary Estimate</div>
        <div class="estimator-price-range mb-2">${h(state.result.range_display.replace('Estimated preliminary range: ',''))}</div>
        <div class="text-secondary mb-3">${h(state.result.disclaimer)}</div>
        <div class="wizard-grid">
          <div><div class="small text-secondary">Estimated duration</div><div class="fw-semibold">${Number(state.result.duration_min_days)} to ${Number(state.result.duration_max_days)} days</div></div>
          <div><div class="small text-secondary">Project type</div><div class="fw-semibold">${h(state.result.project_type.name)}</div></div>
          <div><div class="small text-secondary">Measurement summary</div><div class="fw-semibold">${Number(state.result.measurement_summary.normalized_area_sqm||0).toFixed(2)} sqm</div></div>
          <div><div class="small text-secondary">Finish level</div><div class="fw-semibold">${h(state.result.finish_level.name)}</div></div>
        </div>
        <hr>
        <div class="mb-3"><div class="small text-secondary mb-2">Selected inclusions</div><div class="estimator-inline-list">${(state.result.selected_scopes||[]).map(item=>`<span>${h(item.name)}</span>`).join('')||'<span>None selected</span>'}</div></div>
        <div class="row g-3">
          <div class="col-md-6"><div class="small text-secondary mb-2">Assumptions</div><ul class="small mb-0">${(state.result.assumptions||[]).map(item=>`<li>${h(item)}</li>`).join('')}</ul></div>
          <div class="col-md-6"><div class="small text-secondary mb-2">Exclusions</div><ul class="small mb-0">${(state.result.exclusions||[]).map(item=>`<li>${h(item)}</li>`).join('')}</ul></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-primary rounded-pill" id="jumpToLead">Submit to Maorin Builders</button>
          <button type="button" class="btn btn-outline-primary rounded-pill" id="printEstimate">Print Estimate</button>
          <button type="button" class="btn btn-outline-secondary rounded-pill" id="contactMb">Contact Maorin Builders</button>
          <button type="button" class="btn btn-outline-secondary rounded-pill" id="requestInspection">Request Site Inspection</button>
        </div>
      </div>`;
  }
  function stepHtml(){
    const settings=state.config.settings||{};
    switch(steps[state.step].key){
      case 'intro':
        return `<div class="estimator-result-card"><h3 class="h4 mb-3">Preliminary estimate only</h3><p class="text-secondary">${h(settings.intro_text||'')}</p><div class="alert alert-warning border-0 rounded-4 mb-0"><strong>Disclaimer:</strong> ${h(settings.public_disclaimer_text||'')}</div></div>`;
      case 'project':
        return cardOptions(state.config.projectTypes||[],'project_type_id',state.payload.project_type_id,row=>`${h(row.description||'')}<div class="mt-2 small">Measurement: ${h(row.measurement_type||'sqm')}</div>`);
      case 'measurement':
        return `<div class="wizard-grid">
          <label class="wizard-card-option ${state.payload.measurement_method==='manual'?'active':''}"><input type="radio" name="measurement_method" value="manual" ${state.payload.measurement_method==='manual'?'checked':''}><div class="title">Manual measurement</div><div class="meta">Enter floor area, length, roof area, and other guided fields.</div></label>
          <label class="wizard-card-option ${state.payload.measurement_method==='sketch'?'active':''} ${settings.enable_sketch_tool? '':'opacity-50'}"><input type="radio" name="measurement_method" value="sketch" ${state.payload.measurement_method==='sketch'?'checked':''} ${settings.enable_sketch_tool?'':'disabled'}><div class="title">Sketch / draw measurement</div><div class="meta">Draw a preliminary layout to compute area and perimeter.</div></label>
        </div>`;
      case 'measurements':
        return state.payload.measurement_method==='sketch' ? sketchHtml() : measurementFields();
      case 'finish':
        return cardOptions(filteredFinishLevels(),'finish_level_id',state.payload.finish_level_id,row=>`${h(row.description||'')}<div class="mt-2 small">Multiplier: ${Number(row.multiplier||1).toFixed(2)}</div>`);
      case 'scope':
        return `<div class="wizard-grid">${filteredScopeItems().map(row=>`<label class="wizard-card-option ${state.payload.scope_item_ids.includes(Number(row.id))?'active':''}"><input type="checkbox" name="scope_item_ids" value="${Number(row.id)}" ${state.payload.scope_item_ids.includes(Number(row.id))?'checked':''}><div class="title">${h(row.name)}</div><div class="meta">${h(row.description||'')}</div></label>`).join('')}</div>`;
      case 'site':
        return `<div class="wizard-grid">
          <label>City<input class="form-control" data-field="city" value="${h(state.payload.city||'')}"></label>
          <label>Barangay<input class="form-control" data-field="barangay" value="${h(state.payload.barangay||'')}"></label>
          <label class="full">Project address<textarea class="form-control" rows="3" data-field="project_address">${h(state.payload.project_address||'')}</textarea></label>
          <div class="full"><div class="fw-semibold mb-2">Site conditions</div><div class="wizard-grid">${(state.config.siteRules||[]).map(row=>`<label class="wizard-card-option ${state.payload.site_condition_ids.includes(Number(row.id))?'active':''}"><input type="checkbox" name="site_condition_ids" value="${Number(row.id)}" ${state.payload.site_condition_ids.includes(Number(row.id))?'checked':''}><div class="title">${h(row.name)}</div><div class="meta">${h(row.description||'')}</div></label>`).join('')}</div></div>
        </div>`;
      case 'timeline':
        return cardOptions(state.config.timelineRules||[],'timeline_rule_id',state.payload.timeline_rule_id,row=>`Multiplier ${Number(row.multiplier||1).toFixed(2)} | Duration adjustment ${Number(row.duration_adjustment_days||0)} days`);
      case 'result':
        return resultHtml();
      case 'lead':
        return `<div class="wizard-grid">
          <label>Full name<input class="form-control" data-field="lead.full_name" value="${h(state.payload.lead.full_name||'')}"></label>
          <label>Mobile number<input class="form-control" data-field="lead.mobile_number" value="${h(state.payload.lead.mobile_number||'')}"></label>
          <label>Email (optional)<input class="form-control" data-field="lead.email" value="${h(state.payload.lead.email||'')}"></label>
          <label>Preferred contact method<select class="form-select" data-field="lead.preferred_contact_method"><option ${state.payload.lead.preferred_contact_method==='Call'?'selected':''}>Call</option><option ${state.payload.lead.preferred_contact_method==='Text'?'selected':''}>Text</option><option ${state.payload.lead.preferred_contact_method==='Email'?'selected':''}>Email</option></select></label>
          <label>Preferred consultation date (optional)<input class="form-control" type="date" data-field="lead.preferred_consultation_date" value="${h(state.payload.lead.preferred_consultation_date||'')}"></label>
          <label class="full">Notes / project description<textarea class="form-control" rows="4" data-field="lead.project_description">${h(state.payload.lead.project_description||'')}</textarea></label>
          ${(state.config.settings.enable_file_upload ? `<label class="full">Optional attachment<input class="form-control" type="file" id="estimatorAttachment" accept=".jpg,.jpeg,.png,.pdf"></label>` : '')}
          <div class="full d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary rounded-pill" id="submitEstimate">Submit to Maorin Builders</button>
            <button type="button" class="btn btn-outline-primary rounded-pill" id="downloadEstimate">Download Estimate PDF</button>
            <button type="button" class="btn btn-outline-secondary rounded-pill" id="contactMb">Contact Maorin Builders</button>
          </div>
        </div>`;
      default:return '';
    }
  }
  function filteredFinishLevels(){
    const pid=Number(state.payload.project_type_id||0);
    return (state.config.finishLevels||[]).filter(row=>!row.project_type_id||Number(row.project_type_id)===pid);
  }
  function filteredScopeItems(){
    const pid=Number(state.payload.project_type_id||0);
    return (state.config.scopeItems||[]).filter(row=>!row.project_type_id||Number(row.project_type_id)===pid);
  }
  function sketchHtml(){
    const drawingText=state.config.settings.drawing_disclaimer_text||'';
    return `
      <div class="sketch-disclaimer mb-3">${h(drawingText)}</div>
      <div class="sketch-toolbar">
        <select class="form-select form-select-sm" id="sketchUnit"></select>
        <label class="btn btn-outline-secondary btn-sm mb-0"><input class="form-check-input me-1" type="checkbox" id="sketchSnap" checked>Snap to grid</label>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-tool="select">Select</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-tool="rect">Rectangle</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-tool="polygon">Polygon</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-tool="line">Line</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-tool="label">Label</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="sketchDelete">Delete Selected</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="sketchUndo">Undo</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="sketchRedo">Redo</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="sketchClear">Clear</button>
      </div>
      <div class="sketch-canvas-wrap mb-3"><canvas id="sketchCanvas" width="900" height="480"></canvas></div>
      <div class="wizard-grid">
        <label>Area<input class="form-control" id="sketchArea" readonly value="${h((state.payload.drawing||{}).area_value||'0')}"></label>
        <label>Perimeter<input class="form-control" id="sketchPerimeter" readonly value="${h((state.payload.drawing||{}).perimeter_value||'0')}"></label>
        <div class="full small text-secondary">Rectangle mode now adds width and height dimension lines automatically. Line mode creates a measured dimension line with a live length label. Double-click in polygon mode to finish the area shape.</div>
      </div>`;
  }
  function setField(path,value){
    const parts=path.split('.');
    let ref=state.payload;
    while(parts.length>1){
      const key=parts.shift();
      if(!ref[key]){ref[key]={};}
      ref=ref[key];
    }
    ref[parts[0]]=value;
  }
  function bindCommon(){
    stepHost.querySelectorAll('[data-field]').forEach(el=>{
      const evt=el.tagName==='SELECT'?'change':'input';
      el.addEventListener(evt,()=>{setField(el.dataset.field,el.value); updateSummary();});
    });
    stepHost.querySelectorAll('input[name="project_type_id"]').forEach(el=>el.addEventListener('change',()=>{state.payload.project_type_id=Number(el.value); state.payload.finish_level_id=0; state.payload.scope_item_ids=[]; render();}));
    stepHost.querySelectorAll('input[name="measurement_method"]').forEach(el=>el.addEventListener('change',()=>{state.payload.measurement_method=el.value; render();}));
    stepHost.querySelectorAll('input[name="finish_level_id"]').forEach(el=>el.addEventListener('change',()=>{state.payload.finish_level_id=Number(el.value); updateSummary(); render();}));
    stepHost.querySelectorAll('input[name="scope_item_ids"]').forEach(el=>el.addEventListener('change',()=>{
      const id=Number(el.value);
      state.payload.scope_item_ids=el.checked ? [...new Set([...state.payload.scope_item_ids,id])] : state.payload.scope_item_ids.filter(v=>v!==id);
      render();
    }));
    stepHost.querySelectorAll('input[name="site_condition_ids"]').forEach(el=>el.addEventListener('change',()=>{
      const id=Number(el.value);
      state.payload.site_condition_ids=el.checked ? [...new Set([...state.payload.site_condition_ids,id])] : state.payload.site_condition_ids.filter(v=>v!==id);
      render();
    }));
    stepHost.querySelectorAll('input[name="timeline_rule_id"]').forEach(el=>el.addEventListener('change',()=>{state.payload.timeline_rule_id=Number(el.value); updateSummary(); render();}));
    document.getElementById('jumpToLead')?.addEventListener('click',()=>{state.step=9; render();});
    document.getElementById('printEstimate')?.addEventListener('click',()=>window.print());
    document.getElementById('contactMb')?.addEventListener('click',()=>window.location.href=app.dataset.contactUrl);
    document.getElementById('requestInspection')?.addEventListener('click',()=>{state.step=9; state.payload.lead.project_description=(state.payload.lead.project_description||'') + '\nRequesting site inspection.'; render();});
    document.getElementById('downloadEstimate')?.addEventListener('click',()=>window.print());
    document.getElementById('submitEstimate')?.addEventListener('click',submitLead);
    if(steps[state.step].key==='measurements' && state.payload.measurement_method==='sketch'){ initSketch(); }
  }
  function validateStep(index){
    if(index===1 && !state.payload.project_type_id){showAlert('warning','Please select a project type.'); return false;}
    if(index===3){
      const manual=state.payload.manual||{};
      const area=Number(manual.floor_area_sqm||manual.affected_area_sqm||manual.roof_area_sqm||0);
      const length=Number(manual.length_m||0);
      const hasSketch=Number((state.payload.drawing||{}).normalized_area_sqm||0)>0 || Number((state.payload.drawing||{}).normalized_perimeter_m||0)>0;
      if(state.payload.measurement_method==='sketch' && !hasSketch){showAlert('warning','Please draw at least one shape or measurement line.'); return false;}
      if(state.payload.measurement_method==='manual' && area<=0 && length<=0){showAlert('warning','Please enter at least one valid measurement.'); return false;}
    }
    if(index===4 && !state.payload.finish_level_id){showAlert('warning','Please choose a finish level.'); return false;}
    if(index===7 && !state.payload.timeline_rule_id){showAlert('warning','Please choose a timeline preference.'); return false;}
    if(index===9){
      if(!state.result){showAlert('warning','Preview the estimate first before submitting.'); return false;}
      if(!(state.payload.lead.full_name||'').trim()){showAlert('warning','Please enter your full name.'); return false;}
      if(state.config.settings.require_phone && !(state.payload.lead.mobile_number||'').trim()){showAlert('warning','Please enter your mobile number.'); return false;}
    }
    clearAlert();
    return true;
  }
  async function calculate(){
    if(!validateStep(3) || !validateStep(4) || !validateStep(7)){return;}
    clearAlert();
    const res=await fetch(app.dataset.calculateUrl,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'fetch'},body:JSON.stringify(state.payload)});
    const data=await res.json();
    if(!res.ok||!data.ok){throw new Error(data.message||'Calculation failed.');}
    state.result=data.result;
    updateSummary();
    return data.result;
  }
  async function submitLead(){
    try{
      if(!validateStep(9)){return;}
      const fd=new FormData();
      fd.append('csrf_token',state.csrf);
      fd.append('payload_json',JSON.stringify(state.payload));
      const file=document.getElementById('estimatorAttachment')?.files?.[0];
      if(file){fd.append('attachment',file);}
      const res=await fetch(app.dataset.submitUrl,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}});
      const data=await res.json();
      if(!res.ok||!data.ok){throw new Error(data.message||'Submission failed.');}
      state.result=data.result||state.result;
      showAlert('success',data.message||'Estimate submitted.');
      state.step=8;
      render();
    }catch(err){showAlert('danger',err.message||String(err));}
  }
  function render(){
    renderStepList();
    stepHost.innerHTML=stepHtml();
    form.classList.remove('d-none');
    loading.classList.add('d-none');
    prevBtn.disabled=state.step===0;
    nextBtn.textContent=state.step===steps.length-1?'Finish':'Next';
    calcBtn.classList.toggle('d-none', !['site','timeline','result'].includes(steps[state.step].key));
    bindCommon();
    updateSummary();
  }
  function snapValue(v,grid,on){return on?Math.round(v/grid)*grid:v;}
  function initSketch(){
    if(!window.fabric){showAlert('warning','Sketch tool could not load. Manual measurement is still available.');return;}
    if(window.fabric.Textbox){ window.fabric.Textbox.prototype.textBaseline='alphabetic'; }
    if(window.fabric.IText){ window.fabric.IText.prototype.textBaseline='alphabetic'; }
    if(window.fabric.Text){ window.fabric.Text.prototype.textBaseline='alphabetic'; }
    const canvasEl=document.getElementById('sketchCanvas');
    if(!canvasEl || canvasEl.dataset.bound){return;}
    canvasEl.dataset.bound='1';
    const canvas=new fabric.Canvas('sketchCanvas',{selection:true,preserveObjectStacking:true});
    const unitSelect=document.getElementById('sketchUnit');
    const snap=document.getElementById('sketchSnap');
    const grid=24;
    let tool='select', drawing=false, startX=0, startY=0, temp=null, history=[], future=[], polygonPoints=[], polygonPreview=null;
    (state.config.settings.allowed_units||[]).forEach(unit=>{
      const opt=document.createElement('option');
      opt.value=unit; opt.textContent=unit;
      if(unit===((state.payload.drawing||{}).unit||state.config.settings.default_unit)){opt.selected=true;}
      unitSelect.appendChild(opt);
    });
    function saveHistory(){history.push(JSON.stringify(canvas.toJSON(['dimensionRole','measurementRole']))); if(history.length>40){history.shift();} future=[]; compute();}
    function restore(stackFrom,stackTo){
      if(stackFrom.length<2){return;}
      stackTo.push(stackFrom.pop());
      const json=stackFrom[stackFrom.length-1];
      canvas.loadFromJSON(json,()=>{canvas.renderAll(); compute();});
    }
    function unitFactor(unit){return {meter:1,centimeter:0.01,millimeter:0.001,feet:0.3048,inch:0.0254}[unit]||1;}
    function toMeters(px){ return (px / grid) * unitFactor(unitSelect.value||'meter'); }
    function formatMeters(meters){
      if(meters >= 1){ return `${meters.toFixed(2)} m`; }
      return `${(meters*100).toFixed(1)} cm`;
    }
    function getRectPoints(obj){
      const left=obj.left||0, top=obj.top||0;
      const w=(obj.width||0)*(obj.scaleX||1), h=(obj.height||0)*(obj.scaleY||1);
      return [{x:left,y:top},{x:left+w,y:top},{x:left+w,y:top+h},{x:left,y:top+h}];
    }
    function getPolygonPoints(obj){
      const matrix=obj.calcTransformMatrix();
      return (obj.points||[]).map(p=>{
        const local=new fabric.Point((p.x-(obj.pathOffset?.x||0)), (p.y-(obj.pathOffset?.y||0)));
        const world=fabric.util.transformPoint(local,matrix);
        return {x:world.x,y:world.y};
      });
    }
    function lineLength(points){
      let total=0;
      for(let i=0;i<points.length;i++){
        const a=points[i], b=points[(i+1)%points.length];
        total += Math.hypot(b.x-a.x,b.y-a.y);
      }
      return total;
    }
    function polygonArea(points){
      let sum=0;
      for(let i=0;i<points.length;i++){
        const a=points[i], b=points[(i+1)%points.length];
        sum += (a.x*b.y) - (b.x*a.y);
      }
      return Math.abs(sum/2);
    }
    function clearDimensionObjects(){
      canvas.getObjects().filter(obj=>obj.dimensionRole==='overlay').forEach(obj=>canvas.remove(obj));
    }
    function addDimensionLine(x1,y1,x2,y2,label,color){
      const line=new fabric.Line([x1,y1,x2,y2],{stroke:color,strokeWidth:1.5,selectable:false,evented:false,excludeFromExport:true,dimensionRole:'overlay'});
      const midX=(x1+x2)/2, midY=(y1+y2)/2;
      const text=new fabric.Textbox(label,{left:midX,top:midY,fontSize:12,fill:color,backgroundColor:'rgba(255,255,255,0.92)',textAlign:'center',originX:'center',originY:'center',editable:false,selectable:false,evented:false,excludeFromExport:true,width:90,dimensionRole:'overlay'});
      canvas.add(line,text);
    }
    function renderDimensions(){
      clearDimensionObjects();
      canvas.getObjects().forEach(obj=>{
        if(obj.dimensionRole==='overlay'){ return; }
        if(obj.type==='rect'){
          const pts=getRectPoints(obj);
          addDimensionLine(pts[0].x,pts[0].y-16,pts[1].x,pts[1].y-16,formatMeters(toMeters(Math.hypot(pts[1].x-pts[0].x,pts[1].y-pts[0].y))),'#0a4b1a');
          addDimensionLine(pts[1].x+16,pts[1].y,pts[2].x+16,pts[2].y,formatMeters(toMeters(Math.hypot(pts[2].x-pts[1].x,pts[2].y-pts[1].y))),'#0a4b1a');
        }else if(obj.type==='line' && obj.measurementRole==='dimension'){
          addDimensionLine(obj.x1||0,obj.y1||0,obj.x2||0,obj.y2||0,formatMeters(toMeters(Math.hypot((obj.x2||0)-(obj.x1||0),(obj.y2||0)-(obj.y1||0)))),'#c2410c');
        }else if(obj.type==='polygon'){
          const pts=getPolygonPoints(obj);
          if(pts.length>1){
            const first=pts[0], second=pts[1];
            addDimensionLine(first.x,first.y-16,second.x,second.y-16,`Perim ${formatMeters(toMeters(lineLength(pts)))}`,'#b45309');
          }
        }
      });
      canvas.renderAll();
    }
    function compute(){
      let areaPx=0, periPx=0;
      canvas.getObjects().forEach(obj=>{
        if(obj.dimensionRole==='overlay'){ return; }
        if(obj.type==='rect'){
          const pts=getRectPoints(obj);
          areaPx += polygonArea(pts);
          periPx += lineLength(pts);
        }else if(obj.type==='polygon'){
          const pts=getPolygonPoints(obj);
          if(pts.length>2){
            areaPx += polygonArea(pts);
            periPx += lineLength(pts);
          }
        }else if(obj.type==='line' && obj.measurementRole==='dimension'){
          periPx+=Math.hypot((obj.x2||0)-(obj.x1||0),(obj.y2||0)-(obj.y1||0));
        }
      });
      const factor=unitFactor(unitSelect.value||'meter');
      const sqm=((areaPx*factor*factor)/(grid*grid));
      const pm=((periPx*factor)/grid);
      const areaEl=document.getElementById('sketchArea');
      const perEl=document.getElementById('sketchPerimeter');
      if(areaEl){areaEl.value=`${sqm.toFixed(2)} sqm`;}
      if(perEl){perEl.value=`${pm.toFixed(2)} m`;}
      state.payload.drawing={
        unit:unitSelect.value||'meter',
        scale_ratio:factor,
        canvas_json:JSON.stringify(canvas.toJSON()),
        preview_image:canvas.toDataURL({format:'png',quality:0.8}),
        area_value:Number(sqm.toFixed(2)),
        area_unit:'sqm',
        perimeter_value:Number(pm.toFixed(2)),
        perimeter_unit:'m',
        normalized_area_sqm:Number(sqm.toFixed(2)),
        normalized_perimeter_m:Number(pm.toFixed(2))
      };
      state.drawing=state.payload.drawing;
      renderDimensions();
    }
    function point(pointer){return {x:snapValue(pointer.x,grid,snap.checked),y:snapValue(pointer.y,grid,snap.checked)};}
    function clearPolygonPreview(){
      if(polygonPreview){ canvas.remove(polygonPreview); polygonPreview=null; }
    }
    canvas.on('mouse:down',opt=>{
      const p=point(canvas.getPointer(opt.e));
      if(tool==='rect'){
        drawing=true; startX=p.x; startY=p.y;
        temp=new fabric.Rect({left:startX,top:startY,width:1,height:1,fill:'rgba(8,67,22,.14)',stroke:'#0a4b1a',strokeWidth:2});
        canvas.add(temp);
      }else if(tool==='line'){
        drawing=true; startX=p.x; startY=p.y;
        temp=new fabric.Line([startX,startY,startX,startY],{stroke:'#c2410c',strokeWidth:2,measurementRole:'dimension'});
        canvas.add(temp);
      }else if(tool==='label'){
        const text=new fabric.Textbox('Label',{left:p.x,top:p.y,fontSize:18,fill:'#0b1220',width:120,editable:true});
        canvas.add(text); canvas.setActiveObject(text); saveHistory();
      }else if(tool==='polygon'){
        polygonPoints.push({x:p.x,y:p.y});
        if(polygonPoints.length>2 && opt.e.detail===2){
          clearPolygonPreview();
          const poly=new fabric.Polygon(polygonPoints,{fill:'rgba(228,206,25,.20)',stroke:'#b45309',strokeWidth:2});
          canvas.add(poly); polygonPoints=[]; saveHistory();
        }
      }
    });
    canvas.on('mouse:move',opt=>{
      const p=point(canvas.getPointer(opt.e));
      if(tool==='polygon' && polygonPoints.length){
        clearPolygonPreview();
        const previewPoints=[...polygonPoints,p];
        polygonPreview=new fabric.Polyline(previewPoints,{fill:'rgba(228,206,25,.08)',stroke:'#b45309',strokeDashArray:[6,4],strokeWidth:1.5,selectable:false,evented:false,excludeFromExport:true,dimensionRole:'overlay'});
        canvas.add(polygonPreview);
        canvas.renderAll();
      }
      if(!drawing || !temp){return;}
      if(tool==='rect'){
        temp.set({left:Math.min(startX,p.x),top:Math.min(startY,p.y),width:Math.abs(p.x-startX),height:Math.abs(p.y-startY)});
      }else if(tool==='line'){
        temp.set({x2:p.x,y2:p.y});
      }
      canvas.renderAll();
    });
    canvas.on('mouse:up',()=>{ if(drawing){drawing=false; temp=null; saveHistory();} });
    unitSelect.addEventListener('change',compute);
    stepHost.querySelectorAll('[data-tool]').forEach(btn=>btn.addEventListener('click',()=>{tool=btn.dataset.tool; clearPolygonPreview(); polygonPoints=[]; canvas.isDrawingMode=false; canvas.selection=tool==='select';}));
    document.getElementById('sketchUndo')?.addEventListener('click',()=>restore(history,future));
    document.getElementById('sketchRedo')?.addEventListener('click',()=>{ if(!future.length){return;} const json=future.pop(); history.push(json); canvas.loadFromJSON(json,()=>{canvas.renderAll(); compute();}); });
    document.getElementById('sketchDelete')?.addEventListener('click',()=>{ const active=canvas.getActiveObjects(); if(!active.length){return;} active.forEach(obj=>canvas.remove(obj)); canvas.discardActiveObject(); saveHistory(); });
    document.getElementById('sketchClear')?.addEventListener('click',()=>{canvas.clear(); clearPolygonPreview(); polygonPoints=[]; saveHistory();});
    canvas.on('object:modified',saveHistory);
    canvas.on('object:moving',renderDimensions);
    canvas.on('object:scaling',renderDimensions);
    history=[JSON.stringify(canvas.toJSON(['dimensionRole','measurementRole']))];
    compute();
  }
  prevBtn.addEventListener('click',()=>{if(state.step>0){state.step--; render();}});
  nextBtn.addEventListener('click',async()=>{
    try{
      if(!validateStep(state.step)){return;}
      if(steps[state.step].key==='timeline'){await calculate(); state.step=8; render(); return;}
      if(state.step<steps.length-1){state.step++; render();}
    }catch(err){showAlert('danger',err.message||String(err));}
  });
  calcBtn.addEventListener('click',async()=>{try{await calculate(); showAlert('success','Preliminary estimate updated.'); if(steps[state.step].key!=='result'){state.step=8; render();}}catch(err){showAlert('danger',err.message||String(err));}});

  fetch(app.dataset.configUrl,{headers:{'X-Requested-With':'fetch'}})
    .then(r=>r.json())
    .then(data=>{
      if(!data.ok){throw new Error(data.message||'Could not load estimator config.');}
      state.config=data.config;
      state.csrf=data.csrf_token||'';
      if(!state.payload.timeline_rule_id && data.config.timelineRules && data.config.timelineRules[1]){state.payload.timeline_rule_id=Number(data.config.timelineRules[1].id);}
      render();
    })
    .catch(err=>showAlert('danger',err.message||String(err)));
})();
</script>
<?php include __DIR__ . '/templates/footer.php'; ?>
