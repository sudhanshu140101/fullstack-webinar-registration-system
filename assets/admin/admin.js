document.getElementById("admin-menu-toggle")?.addEventListener("click", () => {
  document.getElementById("admin-sidebar")?.classList.toggle("open");
});

const FLASH_ALERT_MS = 1000;
const FLASH_URL_PARAMS = ["payment_updated", "verification_updated", "deleted", "saved", "fee_saved"];
const FLASH_MESSAGES = {
  payment_updated: "Payment status updated.",
  verification_updated: "Verification status updated.",
  deleted: "Record deleted successfully.",
  saved: "Policy Advocacy section updated successfully.",
  fee_saved: "Registration fee settings saved.",
};

const cleanFlashUrlParams = () => {
  const url = new URL(window.location.href);
  let changed = false;

  FLASH_URL_PARAMS.forEach((param) => {
    if (url.searchParams.has(param)) {
      url.searchParams.delete(param);
      changed = true;
    }
  });

  if (!changed) {
    return;
  }

  const query = url.searchParams.toString();
  const nextUrl = url.pathname + (query ? `?${query}` : "") + url.hash;
  history.replaceState({}, "", nextUrl);
};

const getFlashMessageFromUrl = (url) => {
  for (const [param, message] of Object.entries(FLASH_MESSAGES)) {
    if (url.searchParams.get(param) === "1") {
      return message;
    }
  }

  return "";
};

const dismissToast = (toast) => {
  toast.classList.add("is-hiding");
  window.setTimeout(() => toast.remove(), 320);
};

const showToast = (message) => {
  const root = document.getElementById("admin-toast-root");
  if (!root || !message) {
    return;
  }

  const toast = document.createElement("div");
  toast.className = "alert alert-success alert-toast";
  toast.setAttribute("role", "status");
  toast.textContent = message;
  root.appendChild(toast);
  window.setTimeout(() => dismissToast(toast), FLASH_ALERT_MS);
};

const initFlashAlerts = () => {
  const url = new URL(window.location.href);
  const message = getFlashMessageFromUrl(url);

  cleanFlashUrlParams();

  if (message) {
    showToast(message);
  }
};

initFlashAlerts();
