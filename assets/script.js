// assets/script.js
(() => {
  /* -------------------- utils -------------------- */
  const debounce = (fn, ms = 250) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, args), ms);
    };
  };

  async function fetchJSON(url) {
    try {
      const res = await fetch(url, { headers: { "Accept": "application/json" } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (_) {
      return null;
    }
  }

  // Money helpers (format with commas, safe unformat)
  function unformatMoney(s) {
    if (s == null) return 0;
    return parseFloat(String(s).replace(/,/g, "")) || 0;
  }
  function formatMoney(n) {
    const v = (typeof n === "number") ? n : unformatMoney(n);
    return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // Shorthand
  const $ = (sel) => document.querySelector(sel);

  // --- Utilities to fetch data ---
  async function fetchSuggestions(type, query) {
    const data = await fetchJSON(
      "suggest.php?type=" + encodeURIComponent(type) + "&q=" + encodeURIComponent(query || "")
    );
    return Array.isArray(data) ? data : [];
  }

  async function fetchAddressForSupplier(supplier) {
    if (!supplier) return "";
    const data = await fetchJSON(
      "suggest.php?type=address_for_supplier&supplier=" + encodeURIComponent(supplier.trim())
    );
    return (Array.isArray(data) && data[0]) ? data[0] : "";
  }

  async function fetchTIN(supplier, address) {
    if (!supplier || !address) return "";
    const data = await fetchJSON(
      "suggest.php?type=tin&supplier=" + encodeURIComponent(supplier.trim()) +
      "&address=" + encodeURIComponent(address.trim())
    );
    return (Array.isArray(data) && data[0]) ? data[0] : "";
  }

  async function fetchVatModeForSupplier(supplier) {
    if (!supplier) return "";
    const data = await fetchJSON(
      "suggest.php?type=vat_mode_for_supplier&supplier=" + encodeURIComponent(supplier.trim())
    );
    return (Array.isArray(data) && data[0]) ? String(data[0]) : "";
  }

  /* ---------------- ghost overlay ---------------- */
  function createGhostOverlay(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return null;
    let ghost = document.getElementById(inputId + "-ghost");
    if (!ghost) {
      ghost = document.createElement("span");
      ghost.id = inputId + "-ghost";
      ghost.style.position = "absolute";
      ghost.style.left = input.offsetLeft + 8 + "px";
      ghost.style.top = input.offsetTop + 8 + "px";
      ghost.style.color = "#bbb";
      ghost.style.pointerEvents = "none";
      ghost.style.fontFamily = "inherit";
      ghost.style.fontSize = "inherit";
      ghost.style.zIndex = 1;
      ghost.style.width = input.offsetWidth + "px";
      ghost.style.height = input.offsetHeight + "px";
      ghost.style.whiteSpace = "pre";
      ghost.style.overflow = "hidden";
      input.parentNode.style.position = "relative";
      input.parentNode.appendChild(ghost);
    }
    return ghost;
  }

  function attachAutocomplete(inputId, type) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const datalist = document.getElementById(inputId + "s");
    let suggestions = [];
    const ghost = createGhostOverlay(inputId);

    function updateGhost() {
      if (!ghost) return;
      const val = input.value || "";
      let match = "";
      if (val) {
        for (const option of suggestions) {
          if ((option || "").toLowerCase().startsWith(val.toLowerCase())) {
            match = option;
            break;
          }
        }
      }
      ghost.textContent = (match && match.toLowerCase() !== val.toLowerCase())
        ? val + match.slice(val.length)
        : "";
    }

    const doSuggest = debounce(async () => {
      suggestions = await fetchSuggestions(type, input.value);
      if (datalist) {
        datalist.innerHTML = "";
        suggestions.forEach(v => {
          const opt = document.createElement("option");
          opt.value = v;
          datalist.appendChild(opt);
        });
      }
      updateGhost();
    }, 200);

    input.addEventListener("input", doSuggest);
    input.addEventListener("focus", updateGhost);
    input.addEventListener("blur", () => { if (ghost) ghost.textContent = ""; });
    input.addEventListener("keyup", updateGhost);
    input.addEventListener("keydown", function(e) {
      if ((e.key === "Tab" || e.key === "Enter") && ghost && ghost.textContent && input.value !== ghost.textContent) {
        input.value = ghost.textContent;
        ghost.textContent = "";
        e.preventDefault();
        input.dispatchEvent(new Event("input"));
      }
    });
  }

  /* -------- supplier → address + TIN -------- */
  let supplierChangeToken = 0;
  let addressDirty = false;           // true when user manually edits Address
  let programmaticAddressSet = false; // guard to avoid marking dirty on code-set

  async function handleSupplierChangeOrInput() {
    const supplierEl = document.getElementById("supplier");
    const addressEl  = document.getElementById("address");
    if (!supplierEl || !addressEl) return;

    const supplier = (supplierEl.value || "").trim();
    const myToken = ++supplierChangeToken;

    if (!supplier) {
      // Clear downstream fields if supplier cleared
      programmaticAddressSet = true;
      addressEl.value = "";
      programmaticAddressSet = false;
      const modeEl = document.getElementById("vat_nvat");
      if (modeEl) {
        modeEl.value = "VAT";
        if (typeof window.__recalcPurchase === "function") window.__recalcPurchase();
      }
      await handleTINUpdate();
      return;
    }

    let foundAddress = "";
    let foundMode = "";
    try { foundAddress = await fetchAddressForSupplier(supplier); } catch {}
    try { foundMode = await fetchVatModeForSupplier(supplier); } catch {}

    // If another change happened while fetching, ignore this result
    if (myToken !== supplierChangeToken) return;

    // Only overwrite Address if user hasn't manually edited it after last supplier change,
    // or if it's currently empty.
    if (!addressDirty || !addressEl.value.trim()) {
      programmaticAddressSet = true;
      addressEl.value = foundAddress || "";
      programmaticAddressSet = false;
    }

    const modeEl = document.getElementById("vat_nvat");
    const normalizedMode = (foundMode || "").toUpperCase();
    if (modeEl && (normalizedMode === "VAT" || normalizedMode === "NONVAT")) {
      modeEl.value = (normalizedMode === "NONVAT") ? "NonVAT" : "VAT";
      if (typeof window.__recalcPurchase === "function") window.__recalcPurchase();
    }

    await handleTINUpdate();
  }

  async function handleAddressChangeOrInput() {
    await handleTINUpdate();
  }

  async function handleTINUpdate() {
    const supplier = document.getElementById("supplier")?.value || "";
    const address  = document.getElementById("address")?.value || "";
    const tinEl    = document.getElementById("tin");
    if (!tinEl) return;

    const s = supplier.trim();
    const a = address.trim();
    if (!s || !a) {
      tinEl.value = "";
      return;
    }
    const foundTIN = await fetchTIN(s, a);
    tinEl.value = foundTIN || "";
  }

  /* -------------- calculation -------------- */
  function num(v) { return isNaN(parseFloat(v)) ? 0 : parseFloat(v); }

  // sync helpers for formatted view fields if present
  function syncOthersFromHidden() {
    const vatable_view   = $("#vatable_view");
    const non_vat_view   = $("#non_vat_view");
    const input_vat_view = $("#input_vat_view");
    const total_view     = $("#total_view");
    const cash_view      = $("#cash_view");

    const vatable   = $("#vatable");
    const non_vat   = $("#non_vat");
    const input_vat = $("#input_vat");
    const total     = $("#total");
    const cash      = $("#cash");

    if (vatable_view && vatable)     vatable_view.value   = formatMoney(vatable.value || 0);
    if (non_vat_view && non_vat)     non_vat_view.value   = formatMoney(non_vat.value || 0);
    if (input_vat_view && input_vat) input_vat_view.value = formatMoney(input_vat.value || 0);
    if (total_view && total)         total_view.value     = formatMoney(total.value || 0);
    if (cash_view && cash)           cash_view.value      = formatMoney(cash.value || 0);
  }
  function fullSyncFromHidden() {
    const net_view = $("#net_view");
    const net      = $("#net");
    if (net_view && net) net_view.value = net.value ? formatMoney(net.value) : "";
    syncOthersFromHidden();
  }

  function recalc() {
    const mode       = (document.getElementById("vat_nvat")?.value || "VAT").toUpperCase();
    const vatableEl  = document.getElementById("vatable");
    const nonvatEl   = document.getElementById("non_vat");
    const netEl      = document.getElementById("net");
    const inputVatEl = document.getElementById("input_vat");
    const totalEl    = document.getElementById("total");
    const cashEl     = document.getElementById("cash");

    let vatable = vatableEl ? num(vatableEl.value) : 0;
    let nonvat  = nonvatEl ? num(nonvatEl.value) : 0;
    const net   = netEl ? num(netEl.value) : 0;

    // Disable NON-VAT when VAT is selected
    if (nonvatEl) nonvatEl.disabled = (mode === "VAT");

    if (mode === "NONVAT") {
      if (net > 0) nonvat = net;
      vatable = 0;
      if (vatableEl)  vatableEl.value  = vatable.toFixed(2);
      if (nonvatEl)   nonvatEl.value   = nonvat.toFixed(2);
      if (inputVatEl) inputVatEl.value = "0.00";
      if (totalEl)    totalEl.value    = nonvat.toFixed(2);
      if (cashEl)     cashEl.value     = nonvat.toFixed(2);
    } else {
      if (nonvatEl) nonvatEl.value = "";
      nonvat = 0;
      if (net > 0) {
        vatable = net / 1.12;
        if (vatableEl) vatableEl.value = vatable.toFixed(2);
      } else if (vatableEl) {
        vatableEl.value = (num(vatableEl.value)).toFixed(2);
      }
      const inputVAT = vatable * 0.12;
      const total    = vatable + nonvat;
      const cash     = inputVAT + total;
      if (inputVatEl) inputVatEl.value = inputVAT.toFixed(2);
      if (totalEl)    totalEl.value    = total.toFixed(2);
      if (cashEl)     cashEl.value     = cash.toFixed(2);
    }

    // Mirror to *_view
    syncOthersFromHidden();
  }

  // (optional) expose a safe hook
  window.__recalcPurchase = recalc;

  /* -------------- bind events -------------- */
  function bindEvents() {
    attachAutocomplete("supplier", "supplier");
    attachAutocomplete("project", "project");
    attachAutocomplete("address", "address");

    const descriptionFields = document.querySelectorAll('textarea[name="description"]');
    descriptionFields.forEach((el) => {
      const resize = () => {
        el.style.height = "auto";
        el.style.height = `${el.scrollHeight}px`;
      };

      // Keep pasted paragraphs readable instead of forcing a tiny fixed box.
      el.addEventListener("input", resize);
      el.addEventListener("paste", () => requestAnimationFrame(resize));
      requestAnimationFrame(resize);
    });

    const supplierEl = document.getElementById("supplier");
    if (supplierEl) {
      supplierEl.addEventListener("blur", handleSupplierChangeOrInput);
      supplierEl.addEventListener("change", handleSupplierChangeOrInput);
      supplierEl.addEventListener("input", debounce(handleSupplierChangeOrInput, 150));
    }

    const addressEl = document.getElementById("address");
    if (addressEl) {
      // track user edits to avoid overwriting address they typed
      addressEl.addEventListener("input", () => {
        if (!programmaticAddressSet) addressDirty = true;
      });
      addressEl.addEventListener("blur", handleAddressChangeOrInput);
      addressEl.addEventListener("change", handleAddressChangeOrInput);
      addressEl.addEventListener("input", debounce(handleAddressChangeOrInput, 150));
    }

    // Net view ↔ hidden
    const net_view = $("#net_view");
    const net      = $("#net");

    if (net_view && net) {
      const propagate = () => {
        net.value = unformatMoney(net_view.value); // keep raw number
        // trigger recalc
        recalc();
        Promise.resolve().then(syncOthersFromHidden);
      };
      net_view.addEventListener("input", propagate);

      // Format nicely on blur
      net_view.addEventListener("blur", function() {
        net.value   = unformatMoney(net_view.value).toFixed(2);
        this.value  = net.value ? formatMoney(net.value) : "";
        recalc();
        Promise.resolve().then(fullSyncFromHidden);
      });

      // Enter commits the value
      net_view.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          net_view.blur();
        }
      });
    }

    // If someone edits raw fields directly
    ["vatable", "non_vat"].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener("input", () => {
        recalc();
        Promise.resolve().then(syncOthersFromHidden);
      });
    });

    const modeSel = document.getElementById("vat_nvat");
    if (modeSel) modeSel.addEventListener("change", function() {
      const nonvatEl = document.getElementById("non_vat");
      if ((modeSel.value || "").toUpperCase() === "VAT" && nonvatEl) nonvatEl.value = "";
      recalc();
      Promise.resolve().then(fullSyncFromHidden);
    });

    // Clear button (scoped variable → no global collisions)
    const clearBtn = document.getElementById("clearFormBtn");
    const form = document.getElementById("purchaseForm");
    const dateInput = document.getElementById("date");

    if (form && dateInput) {
      form.addEventListener("submit", (e) => {
        const raw = (dateInput.value || "").trim();
        if (!raw) {
          e.preventDefault();
          alert("Date is required before saving.");
          dateInput.focus();
          return;
        }

        // <input type=\"date\"> submits as YYYY-MM-DD
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
          e.preventDefault();
          alert("Invalid date. Please use mm/dd/yyyy.");
          dateInput.focus();
        }
      });
    }

    if (clearBtn && form) {
      clearBtn.addEventListener("click", () => {
        if (!confirm("Clear all fields?")) return;
        form.reset();

        // Clear hidden calculated fields
        ["net","vatable","non_vat","input_vat","total","cash"].forEach(id=>{
          const el = document.getElementById(id); if (el) el.value = "";
        });

        // Ghost placeholders if present
        ["supplier-ghost","address-ghost","project-ghost"].forEach(id=>{
          const el = document.getElementById(id); if (el) el.textContent = "";
        });

        // Normalize net view and recalc zeros
        const nv = document.getElementById("net_view");
        if (nv) nv.value = "";
        recalc();
        Promise.resolve().then(fullSyncFromHidden);
      });
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    // Reset addressDirty when page loads; it becomes true only on user edits
    addressDirty = false;

    bindEvents();

    // Initial sync: if using *_view fields, mirror hidden to view and run recalc once
    fullSyncFromHidden();
    recalc();
  });
})(); // scoped IIFE
