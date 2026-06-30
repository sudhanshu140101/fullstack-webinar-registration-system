(() => {
  const form = document.getElementById("hero-admin-form");
  const list = document.getElementById("hero-highlights-list");
  const addBtn = document.getElementById("add-hero-highlight");
  const template = document.getElementById("hero-highlight-template");
  const countEl = document.getElementById("hero-highlight-count");
  const emptyState = document.getElementById("hero-highlights-empty");
  const statusPill = document.getElementById("hero-status-pill");
  const activeToggle = document.getElementById("hero-is-active");

  if (!form || !list || !addBtn || !template) {
    return;
  }

  const maxHighlights = Number(countEl?.dataset.max || 8);

  const getRows = () => Array.from(list.querySelectorAll("[data-highlight-row]"));

  const syncCount = () => {
    const count = getRows().length;
    if (countEl) {
      countEl.textContent = `${count} / ${maxHighlights} tags`;
    }
    if (emptyState) {
      emptyState.hidden = count > 0;
    }
    addBtn.disabled = count >= maxHighlights;
  };

  const renumberRows = () => {
    getRows().forEach((row, index) => {
      const input = row.querySelector("input");
      if (input) {
        input.name = `highlights[${index}][text]`;
      }
    });
    syncCount();
  };

  const syncStatusPill = () => {
    if (!statusPill || !activeToggle) {
      return;
    }
    const live = activeToggle.checked;
    statusPill.textContent = live ? "Live on site" : "Using fallback content";
    statusPill.classList.toggle("is-live", live);
    statusPill.classList.toggle("is-hidden", !live);
  };

  addBtn.addEventListener("click", () => {
    if (getRows().length >= maxHighlights) {
      return;
    }

    const index = getRows().length;
    const html = template.innerHTML.replaceAll("__INDEX__", String(index));
    const wrapper = document.createElement("div");
    wrapper.innerHTML = html.trim();
    const row = wrapper.firstElementChild;
    if (!row) {
      return;
    }

    if (emptyState) {
      emptyState.hidden = true;
    }

    list.append(row);
    renumberRows();
    row.querySelector("input")?.focus();
  });

  list.addEventListener("click", (event) => {
    const removeBtn = event.target.closest(".hero-remove-highlight");
    if (!removeBtn) {
      return;
    }

    const row = removeBtn.closest("[data-highlight-row]");
    row?.remove();
    renumberRows();
  });

  activeToggle?.addEventListener("change", syncStatusPill);

  syncCount();
  syncStatusPill();
})();
