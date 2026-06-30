(() => {
  const form = document.getElementById("save-the-date-form");
  const list = document.getElementById("std-details-list");
  const addBtn = document.getElementById("add-std-detail");
  const template = document.getElementById("std-detail-template");
  const countEl = document.getElementById("std-detail-count");
  const emptyState = document.getElementById("std-details-empty");
  const statusPill = document.getElementById("std-status-pill");
  const activeToggle = document.getElementById("std-is-active");

  if (!form || !list || !addBtn || !template) {
    return;
  }

  const maxDetails = Number(countEl?.dataset.max || 8);

  const getRows = () => Array.from(list.querySelectorAll("[data-std-detail-row]"));

  const syncCount = () => {
    const count = getRows().length;
    if (countEl) {
      countEl.textContent = `${count} / ${maxDetails} detail pills`;
    }
    if (emptyState) {
      emptyState.hidden = count > 0;
    }
    addBtn.disabled = count >= maxDetails;
  };

  const renumberRows = () => {
    getRows().forEach((row, index) => {
      const input = row.querySelector("input");
      if (input) {
        input.name = `details[${index}][text]`;
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
    if (getRows().length >= maxDetails) {
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
    const removeBtn = event.target.closest(".std-remove-detail");
    if (!removeBtn) {
      return;
    }

    removeBtn.closest("[data-std-detail-row]")?.remove();
    renumberRows();
  });

  activeToggle?.addEventListener("change", syncStatusPill);

  syncCount();
  syncStatusPill();
})();
