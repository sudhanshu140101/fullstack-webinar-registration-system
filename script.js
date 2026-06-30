const nav = document.querySelector(".site-nav");
const menuToggle = document.querySelector(".menu-toggle");
const mobilePanel = document.querySelector(".mobile-panel");
const navOverlay = document.querySelector(".nav-overlay");
const mobilePanelClose = document.querySelector(".mobile-panel-close");
const stickyCta = document.querySelector(".sticky-cta");
let isMenuOpen = false;
let menuScrollY = 0;

const setMenuOpen = (open, options = {}) => {
  if (!nav || !menuToggle || !mobilePanel) {
    return;
  }

  isMenuOpen = open;

  nav.classList.toggle("open", open);
  mobilePanel.classList.toggle("is-open", open);
  navOverlay?.classList.toggle("is-open", open);
  document.body.classList.toggle("menu-open", open);

  menuToggle.setAttribute("aria-expanded", String(open));
  menuToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
  mobilePanel.setAttribute("aria-hidden", String(!open));
  navOverlay?.setAttribute("aria-hidden", String(!open));

  if (open) {
    menuScrollY = window.scrollY;
    document.body.style.top = `-${menuScrollY}px`;
    return;
  }

  document.body.style.top = "";
  if (!options.preserveScroll) {
    window.scrollTo(0, menuScrollY);
  }
};

const toggleMenu = () => {
  setMenuOpen(!isMenuOpen);
};

setMenuOpen(false);

menuToggle?.addEventListener("click", (event) => {
  event.preventDefault();
  event.stopPropagation();
  toggleMenu();
});

let mobilePanelTouchStartX = 0;

mobilePanel?.addEventListener(
  "touchstart",
  (event) => {
    mobilePanelTouchStartX = event.changedTouches[0]?.screenX ?? 0;
  },
  { passive: true }
);

mobilePanel?.addEventListener(
  "touchend",
  (event) => {
    const touchEndX = event.changedTouches[0]?.screenX ?? 0;
    const deltaX = touchEndX - mobilePanelTouchStartX;

    if (deltaX > 56) {
      setMenuOpen(false);
    }
  },
  { passive: true }
);

mobilePanelClose?.addEventListener("click", (event) => {
  event.preventDefault();
  setMenuOpen(false);
});

navOverlay?.addEventListener("click", () => {
  setMenuOpen(false);
});

const sections = Array.from(document.querySelectorAll(".section-anchor"));
const navLinks = Array.from(document.querySelectorAll(".site-nav .nav-link, .mobile-panel .nav-link"));
const navSectionIds = new Set(
  navLinks.map((link) => link.getAttribute("href")?.slice(1)).filter(Boolean)
);
const trackedSections = sections.filter((section) => navSectionIds.has(section.id));

let scrollSpyPaused = false;
let pendingNavSection = null;
let scrollIdleTimer = null;

const setActiveLink = (id) => {
  if (!id) {
    return;
  }

  navLinks.forEach((link) => {
    const isActive = link.getAttribute("href") === `#${id}`;
    link.classList.toggle("active", isActive);
  });
};

const getNavScrollOffset = () => {
  const navHeight = nav?.offsetHeight ?? 68;
  const stickyBanner = document.querySelector(
    ".seats-urgency-sticky .seats-urgency-banner--hero[data-seats-urgency]"
  );
  const isDesktopUrgencyBar = window.matchMedia("(min-width: 1201px)").matches;
  const stickyHeight =
    isDesktopUrgencyBar && stickyBanner && !stickyBanner.hidden ? stickyBanner.offsetHeight : 0;
  return navHeight + stickyHeight + 16;
};

const revealSection = (section) => {
  if (section?.classList.contains("reveal-on-scroll")) {
    section.classList.add("is-visible");
  }
};

const getActiveSectionId = () => {
  if (!trackedSections.length) {
    return "home";
  }

  if (isMenuOpen) {
    return pendingNavSection ?? trackedSections[0].id;
  }

  const scrollBottom = window.scrollY + window.innerHeight;
  const docHeight = document.documentElement.scrollHeight;

  if (scrollBottom >= docHeight - 48) {
    return trackedSections[trackedSections.length - 1].id;
  }

  const scrollPosition = window.scrollY + getNavScrollOffset();
  let activeId = trackedSections[0].id;

  trackedSections.forEach((section) => {
    const sectionTop = section.getBoundingClientRect().top + window.scrollY;
    if (scrollPosition >= sectionTop - 8) {
      activeId = section.id;
    }
  });

  return activeId;
};

const updateActiveNav = () => {
  if (scrollSpyPaused && pendingNavSection) {
    setActiveLink(pendingNavSection);
    return;
  }

  setActiveLink(getActiveSectionId());
};

const pauseScrollSpy = (sectionId) => {
  pendingNavSection = sectionId;
  scrollSpyPaused = true;
  setActiveLink(sectionId);
};

const resumeScrollSpyWhenIdle = () => {
  if (scrollIdleTimer) {
    window.clearTimeout(scrollIdleTimer);
  }

  scrollIdleTimer = window.setTimeout(() => {
    scrollSpyPaused = false;
    pendingNavSection = null;
    updateActiveNav();
  }, 160);
};

const scrollToSection = (target) => {
  const behavior = window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth";
  const top = target.getBoundingClientRect().top + window.scrollY - getNavScrollOffset();

  window.scrollTo({
    top: Math.max(0, top),
    behavior,
  });
};

const runAfterMenuClose = (callback) => {
  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(callback);
  });
};

const navigateToSection = (sectionId, href) => {
  const target = document.getElementById(sectionId);
  if (!target) {
    return false;
  }

  const wasMenuOpen = isMenuOpen;
  if (wasMenuOpen) {
    setMenuOpen(false, { preserveScroll: true });
  }

  pauseScrollSpy(sectionId);
  revealSection(target);

  const performScroll = () => {
    scrollToSection(target);
    history.replaceState(null, "", href);
    resumeScrollSpyWhenIdle();
  };

  if (wasMenuOpen) {
    runAfterMenuClose(performScroll);
  } else {
    window.requestAnimationFrame(performScroll);
  }

  return true;
};

document.querySelectorAll(".mobile-panel .nav-link, .mobile-panel .nav-pill[href^='#']").forEach((link) => {
  link.addEventListener("click", (event) => {
    const href = link.getAttribute("href");
    if (!href?.startsWith("#") || href.length < 2) {
      setMenuOpen(false);
      return;
    }

    const sectionId = href.slice(1);

    if (navigateToSection(sectionId, href)) {
      event.preventDefault();
    } else {
      setMenuOpen(false);
    }
  });
});

document.querySelectorAll(".nav-right .nav-link, .nav-right .nav-pill[href^='#'], .nav-brand[href^='#']").forEach((link) => {
  link.addEventListener("click", (event) => {
    const href = link.getAttribute("href");
    if (!href?.startsWith("#") || href.length < 2) {
      return;
    }

    const sectionId = href.slice(1);

    if (navigateToSection(sectionId, href)) {
      event.preventDefault();
    }
  });
});

window.addEventListener("scroll", () => {
  stickyCta?.classList.toggle("visible", window.scrollY > 520);

  if (scrollSpyPaused) {
    updateActiveNav();
    resumeScrollSpyWhenIdle();
    return;
  }

  updateActiveNav();
}, { passive: true });

updateActiveNav();

if (window.location.hash) {
  const initialSectionId = window.location.hash.slice(1);
  const initialTarget = document.getElementById(initialSectionId);

  if (initialTarget) {
    window.requestAnimationFrame(() => {
      pauseScrollSpy(initialSectionId);
      revealSection(initialTarget);
      scrollToSection(initialTarget);
      resumeScrollSpyWhenIdle();
    });
  }
}

window.addEventListener("resize", () => {
  if (window.innerWidth > 1200 && isMenuOpen) {
    setMenuOpen(false);
  }
  updateActiveNav();
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && isMenuOpen) {
    setMenuOpen(false);
  }
});

const initBenefitsSlider = () => {
  const slider = document.querySelector("[data-benefits-slider]");
  if (!slider) {
    return;
  }

  const slides = Array.from(slider.querySelectorAll(".benefits-slide"));
  const dotsWrap = slider.querySelector("[data-benefits-dots]");
  const prevButton = slider.querySelector("[data-benefits-prev]");
  const nextButton = slider.querySelector("[data-benefits-next]");
  const status = slider.querySelector("[data-slider-status]");
  const viewport = slider.querySelector(".benefits-slider-viewport");
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const slideCount = slides.length;
  if (!dotsWrap || slideCount === 0) {
    return;
  }

  const autoplayMs = prefersReducedMotion ? 0 : Number(slider.dataset.autoplay) || 2500;
  const initialAutoplayMs = prefersReducedMotion
    ? 0
    : Number(slider.dataset.initialAutoplay) || autoplayMs + 4500;
  const durationMs = prefersReducedMotion ? 0 : Number(slider.dataset.duration) || 900;

  if (viewport) {
    viewport.style.setProperty("--slide-duration", `${durationMs}ms`);
  }

  const slideStates = ["is-active", "is-prev", "is-next", "is-hidden"];

  let current = 0;
  let timer = null;
  let touchStartX = 0;
  let isLocked = false;
  let isInitialSlideHold = true;

  const wrapIndex = (index) => ((index % slideCount) + slideCount) % slideCount;

  const render = () => {
    const prevIndex = wrapIndex(current - 1);
    const nextIndex = wrapIndex(current + 1);

    slides.forEach((slide, index) => {
      slide.classList.remove(...slideStates);

      if (index === current) {
        slide.classList.add("is-active");
        slide.setAttribute("aria-hidden", "false");
      } else if (index === prevIndex) {
        slide.classList.add("is-prev");
        slide.setAttribute("aria-hidden", "true");
      } else if (index === nextIndex) {
        slide.classList.add("is-next");
        slide.setAttribute("aria-hidden", "true");
      } else {
        slide.classList.add("is-hidden");
        slide.setAttribute("aria-hidden", "true");
      }
    });

    dotsWrap.querySelectorAll("button").forEach((dot, index) => {
      dot.classList.toggle("active", index === current);
      dot.setAttribute("aria-current", index === current ? "true" : "false");
    });

    if (status) {
      status.textContent = `Showing slide ${current + 1} of ${slideCount}`;
    }
  };

  const goTo = (index) => {
    if (isLocked) {
      return;
    }

    const nextIndex = wrapIndex(index);
    if (nextIndex === current) {
      return;
    }

    if (durationMs > 0) {
      isLocked = true;
      window.setTimeout(() => {
        isLocked = false;
      }, durationMs);
    }

    current = nextIndex;
    if (current !== 0) {
      isInitialSlideHold = false;
    }
    render();
  };

  const getAutoplayDelay = () => {
    if (isInitialSlideHold && current === 0) {
      return initialAutoplayMs;
    }
    return autoplayMs;
  };

  const stopAutoplay = () => {
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }
  };

  const scheduleAutoplay = () => {
    if (!autoplayMs) {
      return;
    }

    stopAutoplay();
    timer = window.setTimeout(() => {
      if (current === 0) {
        isInitialSlideHold = false;
      }
      goTo(current + 1);
      scheduleAutoplay();
    }, getAutoplayDelay());
  };

  const startAutoplay = () => {
    scheduleAutoplay();
  };

  const restartAutoplay = () => {
    stopAutoplay();
    startAutoplay();
  };

  slides.forEach((_, index) => {
    const dot = document.createElement("button");
    dot.type = "button";
    dot.setAttribute("aria-label", `Go to slide ${index + 1}`);
    dot.addEventListener("click", () => {
      goTo(index);
      restartAutoplay();
    });
    dotsWrap.appendChild(dot);
  });

  prevButton?.addEventListener("click", () => {
    goTo(current - 1);
    restartAutoplay();
  });

  nextButton?.addEventListener("click", () => {
    goTo(current + 1);
    restartAutoplay();
  });

  slider.addEventListener("mouseenter", stopAutoplay);
  slider.addEventListener("mouseleave", startAutoplay);

  slider.addEventListener(
    "touchstart",
    (event) => {
      touchStartX = event.changedTouches[0]?.screenX ?? 0;
      stopAutoplay();
    },
    { passive: true }
  );

  slider.addEventListener(
    "touchend",
    (event) => {
      const touchEndX = event.changedTouches[0]?.screenX ?? 0;
      const deltaX = touchEndX - touchStartX;

      if (Math.abs(deltaX) >= 42) {
        goTo(current + (deltaX < 0 ? 1 : -1));
      }

      restartAutoplay();
    },
    { passive: true }
  );

  document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
      stopAutoplay();
    } else {
      startAutoplay();
    }
  });

  render();
  startAutoplay();
};

initBenefitsSlider();

document.querySelectorAll(".faq-item button").forEach((button) => {
  button.addEventListener("click", () => {
    const item = button.closest(".faq-item");
    const isOpen = item.classList.contains("open");

    document.querySelectorAll(".faq-item").forEach((faq) => {
      faq.classList.remove("open");
      faq.querySelector("button").setAttribute("aria-expanded", "false");
      faq.querySelector("span").textContent = "+";
    });

    if (!isOpen) {
      item.classList.add("open");
      button.setAttribute("aria-expanded", "true");
      button.querySelector("span").textContent = "−";
    }
  });
});

const revealObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        revealObserver.unobserve(entry.target);
      }
    });
  },
  {
    threshold: 0.12,
    rootMargin: "0px 0px -8% 0px",
  }
);

document.querySelectorAll(".reveal-on-scroll").forEach((section) => {
  revealObserver.observe(section);
});

const initCriteriaPillsMobileLayout = () => {
  const container = document.querySelector(".criteria-pills");
  if (!container) {
    return;
  }

  const mobileQuery = window.matchMedia("(max-width: 768px)");

  const getPillLabel = (pill) => pill.textContent.replace(/✓/g, "").trim();

  const restoreDesktopLayout = () => {
    if (!container.dataset.originalHtml) {
      return;
    }

    container.innerHTML = container.dataset.originalHtml;
    container.classList.remove("is-mobile-pyramid");
  };

  const applyMobileLayout = () => {
    if (!container.dataset.originalHtml) {
      container.dataset.originalHtml = container.innerHTML;
    }

    container.innerHTML = container.dataset.originalHtml;
    const pills = Array.from(container.querySelectorAll(":scope > div"));
    const sortedPills = pills.sort(
      (left, right) => getPillLabel(left).length - getPillLabel(right).length
    );

    container.innerHTML = "";
    container.classList.add("is-mobile-pyramid");

    sortedPills.forEach((pill) => {
      container.appendChild(pill);
    });
  };

  const updateLayout = () => {
    if (mobileQuery.matches) {
      applyMobileLayout();
      return;
    }

    restoreDesktopLayout();
  };

  updateLayout();
  mobileQuery.addEventListener("change", updateLayout);
};

initCriteriaPillsMobileLayout();

const PAST_INITIATIVE_VIDEOS = [
  "Bj09Evg9MOQ",
  "LbLOpwgFOIg",
  "usMo2HLtZ-k",
  "w0LI6-8WCbY",
  "7qTX0G5XnnE",
  "GnNZpbGPL3Y",
  "QpIFHgkWJkQ",
  "O0J9-TKnlxY",
  "VP3ZFB7kuKQ",
  "OZehlRkEFUA",
  "Yyvqutes4Ls",
  "w9VFAbBkg1g",
  "0vjJmM8HG5k",
  "JBeVMldJEzM",
  "DgDdTAuFs8I",
  "aIkvbHCCbxc",
  "rZ0jFndJ91w",
  "Or98Etc6lP8",
  "do3wcaMbdbQ",
  "Hv6XIBAI8Lk",
  "bcs1Ev85124",
  "6xE53183umE",
  "Sbw5gp0zN1k",
  "SIRe9sbLkzU",
];

const YOUTUBE_THUMBNAIL_QUALITIES = [
  "maxresdefault",
  "sddefault",
  "hqdefault",
  "mqdefault",
  "default",
  "1",
  "2",
  "3",
];

const getYouTubeThumbnailUrl = (videoId, quality) =>
  `https://i.ytimg.com/vi/${videoId}/${quality}.jpg`;

const applyYouTubeThumbnail = (img, videoId, qualityIndex = 0) => {
  if (qualityIndex >= YOUTUBE_THUMBNAIL_QUALITIES.length) {
    img.classList.remove("is-loading");
    return;
  }

  const quality = YOUTUBE_THUMBNAIL_QUALITIES[qualityIndex];

  const tryNextQuality = () => {
    applyYouTubeThumbnail(img, videoId, qualityIndex + 1);
  };

  const handleLoad = () => {
    img.removeEventListener("error", handleError);

    const isPlaceholder = img.naturalWidth <= 120 || img.naturalHeight <= 90;

    if (isPlaceholder && qualityIndex < YOUTUBE_THUMBNAIL_QUALITIES.length - 1) {
      tryNextQuality();
      return;
    }

    img.classList.remove("is-loading");
    img.dataset.thumbQuality = quality;
  };

  const handleError = () => {
    img.removeEventListener("load", handleLoad);
    tryNextQuality();
  };

  img.classList.add("is-loading");
  img.addEventListener("load", handleLoad, { once: true });
  img.addEventListener("error", handleError, { once: true });
  img.src = getYouTubeThumbnailUrl(videoId, quality);
};

const initVideoMarquee = () => {
  const marquee = document.querySelector("[data-video-marquee]");
  const tracks = marquee?.querySelectorAll("[data-video-track]");
  const modal = document.getElementById("video-modal");
  const iframe = document.getElementById("video-modal-iframe");
  const closeButton = modal?.querySelector(".video-modal-close");

  if (!marquee || !tracks?.length || !modal || !iframe || !closeButton) {
    return;
  }

  const createVideoCard = (videoId, index) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "video-card";
    button.dataset.videoId = videoId;
    button.setAttribute("aria-label", `Play MSME webinar video ${index + 1}`);

    const img = document.createElement("img");
    img.alt = "";
    img.width = 640;
    img.height = 360;
    img.loading = "lazy";
    img.decoding = "async";
    applyYouTubeThumbnail(img, videoId);

    const play = document.createElement("span");
    play.className = "video-card-play";
    play.setAttribute("aria-hidden", "true");

    button.append(img, play);
    return button;
  };

  const renderTrack = (track) => {
    track.innerHTML = "";
    PAST_INITIATIVE_VIDEOS.forEach((videoId, index) => {
      track.appendChild(createVideoCard(videoId, index));
    });
  };

  tracks.forEach(renderTrack);

  const pauseMarquee = () => {
    marquee.classList.add("is-paused");
  };

  const resumeMarquee = () => {
    marquee.classList.remove("is-paused");
  };

  const closeVideoModal = () => {
    iframe.src = "";
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("video-modal-open");
    resumeMarquee();
  };

  const openVideoModal = (videoId) => {
    pauseMarquee();
    iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1`;
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("video-modal-open");
    closeButton.focus();
  };

  marquee.addEventListener("click", (event) => {
    const card = event.target.closest(".video-card");
    if (!card?.dataset.videoId) {
      return;
    }
    openVideoModal(card.dataset.videoId);
  });

  closeButton.addEventListener("click", closeVideoModal);

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeVideoModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal.classList.contains("open")) {
      closeVideoModal();
    }
  });
};

initVideoMarquee();

const initPolicyAdvocacy = async () => {
  const section = document.getElementById("policy-advocacy");
  if (!section) {
    return;
  }

  const badge = section.querySelector(".policy-advocacy-title");
  const intro = section.querySelector(".policy-advocacy-intro");
  const grid = section.querySelector(".policy-advocacy-grid");

  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 8000);

    const response = await fetch("api/policy-advocacy.php", {
      headers: { Accept: "application/json" },
      signal: controller.signal,
    });
    window.clearTimeout(timeoutId);
    const payload = await response.json();
    const data = payload?.data;
    const cards = Array.isArray(data?.cards) ? data.cards : [];

    if (!payload?.success || cards.length === 0 || !grid) {
      return;
    }

    const sectionTitle = data.section_title || "Policy Advocacy";

    if (badge) {
      badge.textContent = sectionTitle;
      badge.hidden = false;
    }

    if (data.section_message && intro) {
      intro.textContent = data.section_message;
      intro.hidden = false;
    }

    grid.innerHTML = "";

    cards.forEach((card, index) => {
      const article = document.createElement("article");
      article.className = "policy-advocacy-card";
      article.setAttribute("role", "listitem");
      article.tabIndex = 0;
      article.style.setProperty("--card-delay", `${index * 90}ms`);

      const image = document.createElement("img");
      image.className = "policy-advocacy-image";
      image.src = card.image_url || "images/Slider-1.png";
      image.alt = card.message ? `${sectionTitle} — ${card.message}` : sectionTitle;
      image.width = 640;
      image.height = 400;
      image.loading = index === 0 ? "eager" : "lazy";
      image.decoding = "async";

      const overlay = document.createElement("div");
      overlay.className = "policy-advocacy-overlay";
      overlay.setAttribute("aria-hidden", "true");

      article.append(image, overlay);

      if (card.message) {
        const content = document.createElement("div");
        content.className = "policy-advocacy-content";
        const message = document.createElement("p");
        message.className = "policy-advocacy-message";
        message.textContent = card.message;
        content.append(message);
        article.append(content);
        article.classList.add("has-message");
      }

      grid.append(article);
    });

    section.hidden = false;
    section.classList.add("reveal-on-scroll");
    if (!section.classList.contains("is-visible")) {
      revealObserver.observe(section);
    }
  } catch {
    // Static fallback card remains visible.
  }
};

initPolicyAdvocacy();

const setHeroText = (element, value) => {
  if (!element) {
    return;
  }
  if (value) {
    element.textContent = value;
    element.hidden = false;
    return;
  }
  element.hidden = true;
};

const setHeroHtmlLines = (element, value) => {
  if (!element) {
    return;
  }
  if (!value) {
    element.hidden = true;
    element.textContent = "";
    return;
  }

  const lines = value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  element.innerHTML = "";
  lines.forEach((line, index) => {
    if (index > 0) {
      element.append(document.createElement("br"));
    }
    element.append(document.createTextNode(line));
  });
  element.hidden = false;
};

const renderHeroMeta = (grid, items) => {
  if (!grid || !Array.isArray(items)) {
    return;
  }

  grid.innerHTML = "";

  items.forEach((item) => {
    const article = document.createElement("article");
    article.className = "hero-meta-card";
    article.tabIndex = 0;

    const icon = document.createElement("span");
    icon.className = "meta-icon";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = item.icon || "📌";

    const body = document.createElement("div");
    const label = document.createElement("strong");
    label.textContent = item.label || "";
    const value = document.createElement("span");
    value.textContent = item.value || "";

    body.append(label, value);
    article.append(icon, body);
    grid.append(article);
  });

  grid.hidden = items.length === 0;
};

const renderHeroHighlights = (container, items) => {
  if (!container || !Array.isArray(items)) {
    return;
  }

  container.innerHTML = "";
  items.forEach((text) => {
    const tag = document.createElement("span");
    tag.textContent = text;
    container.append(tag);
  });
  container.hidden = items.length === 0;
};

const initHero = async () => {
  const section = document.querySelector("[data-hero-section]");
  if (!section) {
    return;
  }

  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 8000);

    const response = await fetch("api/hero.php", {
      headers: { Accept: "application/json" },
      signal: controller.signal,
    });
    window.clearTimeout(timeoutId);

    const payload = await response.json();
    const data = payload?.data;
    if (!payload?.success || !data) {
      return;
    }

    setHeroText(section.querySelector("[data-hero-badge]"), data.badge);
    setHeroText(section.querySelector("[data-hero-title]"), data.title);
    setHeroText(section.querySelector("[data-hero-subtitle]"), data.subtitle);

    const guest = data.guest || {};
    const guestCard = section.querySelector("[data-hero-guest]");
    setHeroText(section.querySelector("[data-hero-guest-label]"), guest.label);
    setHeroText(section.querySelector("[data-hero-guest-name]"), guest.name);
    setHeroText(section.querySelector("[data-hero-guest-role]"), guest.role);

    const hasGuest = guest.label || guest.name || guest.role;
    if (guestCard) {
      guestCard.hidden = !hasGuest;
    }

    renderHeroMeta(section.querySelector("[data-hero-meta-grid]"), data.meta);
    renderHeroHighlights(section.querySelector("[data-hero-highlights]"), data.highlights);
    setHeroHtmlLines(section.querySelector("[data-hero-copy]"), data.copy_text);

    const registerLink = section.querySelector("[data-hero-register]");
    if (registerLink) {
      if (data.register_url) {
        registerLink.href = data.register_url;
        registerLink.hidden = false;
      } else {
        registerLink.hidden = true;
      }
    }

    const chatLink = section.querySelector("[data-hero-chat]");
    if (chatLink) {
      if (data.chat_url) {
        chatLink.href = data.chat_url;
        chatLink.hidden = false;
      } else {
        chatLink.hidden = true;
      }
    }

    section.classList.add("hero-loaded");
    section.querySelector(".hero-inner")?.setAttribute("aria-busy", "false");
  } catch {
    // Hero stays hidden when admin data is unavailable.
  }
};

initHero();

const clampPercent = (value) => Math.min(100, Math.max(0, Number(value) || 0));

const renderSeatsUrgencyBanner = (banner, data) => {
  const messageEl = banner.querySelector("[data-seats-message]");
  const spotsEl = banner.querySelector("[data-seats-left]");
  const progressBar = banner.querySelector("[data-seats-progress]");
  const progressFill = banner.querySelector("[data-seats-progress-fill]");

  if (messageEl) {
    messageEl.textContent = data.message_text;
  }

  if (spotsEl) {
    spotsEl.textContent = String(data.spots_left);
  }

  const percent = clampPercent(data.progress_percent);
  if (progressFill) {
    progressFill.style.width = `${percent}%`;
  }
  if (progressBar) {
    progressBar.setAttribute("aria-valuenow", String(percent));
  }

  banner.hidden = false;
};

const initSeatsUrgency = async () => {
  const banners = document.querySelectorAll("[data-seats-urgency]");
  if (!banners.length) {
    return;
  }

  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 8000);

    const response = await fetch("api/seats-urgency.php", {
      headers: { Accept: "application/json" },
      signal: controller.signal,
    });
    window.clearTimeout(timeoutId);

    const payload = await response.json();
    const data = payload?.data;
    if (!payload?.success || !data) {
      return;
    }

    banners.forEach((banner) => renderSeatsUrgencyBanner(banner, data));
  } catch {
    // Banners stay hidden when CMS data is unavailable.
  }
};

initSeatsUrgency();

const renderSaveTheDateDetails = (container, items) => {
  if (!container || !Array.isArray(items)) {
    return;
  }

  container.innerHTML = "";
  items.forEach((text) => {
    const tag = document.createElement("span");
    tag.textContent = text;
    container.append(tag);
  });
  container.hidden = items.length === 0;
};

const initSaveTheDate = async () => {
  const section = document.querySelector("[data-save-the-date-section]");
  if (!section) {
    return;
  }

  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 8000);

    const response = await fetch("api/save-the-date.php", {
      headers: { Accept: "application/json" },
      signal: controller.signal,
    });
    window.clearTimeout(timeoutId);

    const payload = await response.json();
    const data = payload?.data;
    if (!payload?.success || !data) {
      return;
    }

    const badge = section.querySelector("[data-std-badge]");
    const tagline = section.querySelector("[data-std-tagline]");
    const headline = section.querySelector("[data-std-headline]");
    const copy = section.querySelector("[data-std-copy]");

    if (badge) {
      if (data.badge) {
        badge.textContent = data.badge;
        badge.hidden = false;
      } else {
        badge.hidden = true;
      }
    }

    if (tagline) {
      if (data.tagline) {
        tagline.textContent = data.tagline;
        tagline.hidden = false;
      } else {
        tagline.hidden = true;
      }
    }

    if (headline && data.headline) {
      headline.textContent = data.headline;
      headline.hidden = false;
    }

    renderSaveTheDateDetails(section.querySelector("[data-std-details]"), data.details);

    if (copy) {
      if (data.copy_text) {
        copy.textContent = data.copy_text;
        copy.hidden = false;
      } else {
        copy.hidden = true;
      }
    }
  } catch {
    // Static HTML fallback remains visible.
  }
};

initSaveTheDate();

const RECENT_REG_SESSION_KEY = "msme_recent_reg_notices_v1";

const getRegistrantInitials = (name) => {
  const parts = String(name).trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) {
    return "?";
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 1).toUpperCase();
  }
  return (parts[0].slice(0, 1) + parts[parts.length - 1].slice(0, 1)).toUpperCase();
};

const createRecentRegNotice = (item) => {
  const notice = document.createElement("div");
  notice.className = "recent-reg-notice";
  notice.setAttribute("role", "status");

  const avatar = document.createElement("span");
  avatar.className = "recent-reg-notice-avatar";
  avatar.setAttribute("aria-hidden", "true");
  avatar.textContent = getRegistrantInitials(item.name);

  const body = document.createElement("span");
  body.className = "recent-reg-notice-body";

  const line = document.createElement("span");
  line.className = "recent-reg-notice-line";

  const strong = document.createElement("strong");
  strong.textContent = item.name;

  line.append(strong, " registered");

  const time = document.createElement("span");
  time.className = "recent-reg-notice-time";
  time.textContent = item.registered_ago;

  body.append(line, time);
  notice.append(avatar, body);

  return notice;
};

const waitMs = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

const showRecentRegNotice = (root, item, prefersReducedMotion) => {
  const showMs = prefersReducedMotion ? 4800 : 3600;
  const hideMs = prefersReducedMotion ? 120 : 420;

  return new Promise((resolve) => {
    const notice = createRecentRegNotice(item);
    root.append(notice);

    requestAnimationFrame(() => {
      requestAnimationFrame(() => notice.classList.add("is-visible"));
    });

    window.setTimeout(() => {
      notice.classList.remove("is-visible");
      notice.classList.add("is-hiding");
      window.setTimeout(() => {
        notice.remove();
        resolve();
      }, hideMs);
    }, showMs);
  });
};

const runRecentRegistrationNotices = async (registrants) => {
  const root = document.getElementById("recent-reg-notices");
  if (!root || !Array.isArray(registrants) || registrants.length === 0) {
    return;
  }

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const initialDelay = prefersReducedMotion ? 600 : 2800;
  const gapMs = prefersReducedMotion ? 700 : 1300;

  root.hidden = false;

  await waitMs(initialDelay);

  for (let index = 0; index < registrants.length; index += 1) {
    await showRecentRegNotice(root, registrants[index], prefersReducedMotion);
    if (index < registrants.length - 1) {
      await waitMs(gapMs);
    }
  }

  root.hidden = true;
};

const initRecentRegistrationNotices = async () => {
  if (sessionStorage.getItem(RECENT_REG_SESSION_KEY) === "1") {
    return;
  }

  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 8000);

    const response = await fetch("api/recent-registrants.php", {
      headers: { Accept: "application/json" },
      signal: controller.signal,
    });
    window.clearTimeout(timeoutId);

    const payload = await response.json();
    const registrants = payload?.data?.registrants;

    if (!payload?.success || !Array.isArray(registrants) || registrants.length === 0) {
      return;
    }

    sessionStorage.setItem(RECENT_REG_SESSION_KEY, "1");
    await runRecentRegistrationNotices(registrants);
  } catch {
    // Notifications are optional; fail silently.
  }
};

initRecentRegistrationNotices();
