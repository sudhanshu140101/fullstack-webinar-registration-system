(() => {
  const form = document.getElementById("policy-advocacy-form");
  const list = document.getElementById("policy-cards-list");
  const emptyState = document.getElementById("policy-cards-empty");
  const addBtn = document.getElementById("add-policy-card");
  const template = document.getElementById("policy-new-card-template");
  const countEl = document.getElementById("policy-card-count");
  const statusPill = document.getElementById("policy-status-pill");
  const activeToggle = document.getElementById("policy-is-active");

  if (!form || !list || !addBtn || !template) {
    return;
  }

  const maxCards = Number(countEl?.dataset.max || 12);
  let newIndex = 0;

  const getCards = () => Array.from(list.querySelectorAll(".policy-card-editor"));

  const getActiveCards = () =>
    getCards().filter((card) => !card.classList.contains("is-removed"));

  const syncCount = () => {
    const active = getActiveCards().length;
    if (countEl) {
      countEl.textContent = `${active} / ${maxCards} cards`;
    }
    if (emptyState) {
      emptyState.hidden = active > 0;
    }
    addBtn.disabled = active >= maxCards;
  };

  const syncStatusPill = () => {
    if (!statusPill || !activeToggle) {
      return;
    }
    const live = activeToggle.checked;
    statusPill.textContent = live ? "Visible on site" : "Hidden on site";
    statusPill.classList.toggle("is-live", live);
    statusPill.classList.toggle("is-hidden", !live);
  };

  const renumberCards = () => {
    getCards().forEach((card, index) => {
      const removed = card.classList.contains("is-removed");
      const numberEl = card.querySelector(".policy-card-number");
      const labelEl = card.querySelector(".policy-card-label");
      const sortInput = card.querySelector(".card-sort-order");

      if (numberEl) {
        numberEl.textContent = String(index + 1);
      }

      if (labelEl && !card.dataset.newCard) {
        labelEl.textContent = removed ? `Card ${index + 1} (removing)` : `Card ${index + 1}`;
      }

      if (sortInput) {
        sortInput.value = String(index);
      }

      card.dataset.cardIndex = String(index);

      if (!card.dataset.newCard) {
        const cardId = card.dataset.cardId;
        const deleteFlag = card.querySelector(".policy-delete-flag");
        const message = card.querySelector(`textarea[name^="cards["]`);
        const idInput = card.querySelector('input[name$="[id]"]');

        if (deleteFlag) {
          deleteFlag.name = `cards[${index}][delete]`;
        }
        if (idInput) {
          idInput.name = `cards[${index}][id]`;
        }
        if (message) {
          message.name = `cards[${index}][message]`;
        }
        if (sortInput) {
          sortInput.name = `cards[${index}][sort_order]`;
        }

        if (cardId) {
          const fileInput = card.querySelector(".policy-image-input");
          if (fileInput) {
            fileInput.name = `card_image_${cardId}`;
          }
        }
      } else {
        const newIdx = card.dataset.newIndex;
        const message = card.querySelector(`textarea[name^="new_cards["]`);
        const fileInput = card.querySelector(".policy-image-input");

        if (message) {
          message.name = `new_cards[${newIdx}][message]`;
        }
        if (sortInput) {
          sortInput.name = `new_cards[${newIdx}][sort_order]`;
        }
        if (fileInput) {
          fileInput.name = `new_card_image_${newIdx}`;
        }
      }
    });
  };

  const bindImagePreview = (card) => {
    const input = card.querySelector(".policy-image-input");
    const preview = card.querySelector(".policy-preview-image");
    if (!input || !preview) {
      return;
    }

    input.addEventListener("change", () => {
      const file = input.files?.[0];
      if (!file || !file.type.startsWith("image/")) {
        return;
      }

      const reader = new FileReader();
      reader.onload = () => {
        if (typeof reader.result === "string") {
          preview.src = reader.result;
        }
      };
      reader.readAsDataURL(file);
    });
  };

  const bindCard = (card) => {
    bindImagePreview(card);

    card.querySelector(".policy-move-up")?.addEventListener("click", () => {
      const prev = card.previousElementSibling;
      if (prev) {
        list.insertBefore(card, prev);
        renumberCards();
      }
    });

    card.querySelector(".policy-move-down")?.addEventListener("click", () => {
      const next = card.nextElementSibling;
      if (next) {
        list.insertBefore(next, card);
        renumberCards();
      }
    });

    card.querySelector(".policy-remove-new")?.addEventListener("click", () => {
      card.remove();
      renumberCards();
      syncCount();
    });

    card.querySelector(".policy-mark-remove")?.addEventListener("click", () => {
      const flag = card.querySelector(".policy-delete-flag");
      const removing = !card.classList.contains("is-removed");
      card.classList.toggle("is-removed", removing);
      if (flag) {
        flag.value = removing ? "1" : "0";
      }
      renumberCards();
      syncCount();
    });
  };

  const addNewCard = () => {
    if (getActiveCards().length >= maxCards) {
      return;
    }

    const index = getCards().length;
    const html = template.innerHTML
      .replaceAll("__INDEX__", String(index))
      .replaceAll("__NEW_INDEX__", String(newIndex))
      .replaceAll("__NUMBER__", String(index + 1));

    const wrapper = document.createElement("div");
    wrapper.innerHTML = html.trim();
    const card = wrapper.firstElementChild;
    if (!(card instanceof HTMLElement)) {
      return;
    }

    list.appendChild(card);
    bindCard(card);
    newIndex += 1;
    renumberCards();
    syncCount();
    card.scrollIntoView({ behavior: "smooth", block: "nearest" });
    card.querySelector(".policy-image-input")?.focus();
  };

  getCards().forEach(bindCard);
  syncCount();
  syncStatusPill();

  addBtn.addEventListener("click", addNewCard);
  activeToggle?.addEventListener("change", syncStatusPill);

  form.addEventListener("submit", (event) => {
    renumberCards();

    const missingImage = getActiveCards().some((card) => {
      if (!card.dataset.newCard) {
        return false;
      }
      const input = card.querySelector(".policy-image-input");
      return !input?.files?.length;
    });

    if (missingImage) {
      event.preventDefault();
      window.alert("Each new card needs a photo before saving.");
      return;
    }

    const activeCount = getActiveCards().length;
    if (activeCount > maxCards) {
      event.preventDefault();
      window.alert(`Maximum ${maxCards} cards allowed.`);
    }
  });
})();
