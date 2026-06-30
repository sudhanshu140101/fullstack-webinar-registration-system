const toast = document.querySelector(".toast");
const form = document.querySelector(".registration-form");
const API_CSRF = "api/csrf.php";
const API_SUBMIT = "api/submit.php";
const API_SETTINGS = "api/registration-settings.php";
const API_SEND_OTP = "api/registration-otp-send.php";
const API_VERIFY_OTP = "api/registration-otp-verify.php";

const OTP_LENGTH = Math.max(4, Math.min(8, Number(form?.dataset.otpLength) || 6));

let paymentSettings = null;
let csrfToken = "";
let resendCountdown = null;
let mobileVerified = false;
let verifiedMobile = "";
let verifyInFlight = false;

const showToast = (message, duration = 4500) => {
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(showToast._timer);
  showToast._timer = setTimeout(() => toast.classList.remove("show"), duration);
};

const normalizeMobile = (value) => value.replace(/\D/g, "").slice(0, 10);

const isValidMobile = (mobile) => /^[6-9][0-9]{9}$/.test(mobile);

const submitButton = () => form?.querySelector(".registration-submit");

const fetchCsrfToken = async () => {
  const response = await fetch(API_CSRF, {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  });
  const payload = await response.json();
  if (!response.ok || !payload.success || !payload.token) {
    throw new Error("csrf_unavailable");
  }
  csrfToken = payload.token;
  return csrfToken;
};

const postForm = async (url, fields) => {
  const body = new FormData();
  body.append("csrf_token", csrfToken);
  Object.entries(fields).forEach(([key, value]) => body.append(key, value));

  const response = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-CSRF-Token": csrfToken,
    },
    body,
  });

  let payload = {};
  try {
    payload = await response.json();
  } catch {
    payload = { success: false, message: "Unexpected server response." };
  }

  if (response.status === 403 && payload.message?.includes("security token")) {
    await fetchCsrfToken();
    return postForm(url, fields);
  }

  return { response, payload };
};

const showOtpAlert = (message) => {
  const alertBox = document.getElementById("reg-otp-alert");
  if (!alertBox) return;
  if (!message) {
    alertBox.hidden = true;
    alertBox.textContent = "";
    return;
  }
  alertBox.textContent = message;
  alertBox.classList.remove("user-alert-success");
  alertBox.classList.add("user-alert-error");
  alertBox.hidden = false;
};

const setBusy = (button, busy, label) => {
  if (!button) return;
  button.disabled = busy;
  const labelEl = button.querySelector("span");
  if (labelEl && label) {
    labelEl.textContent = label;
  }
};

const clearResendCountdown = () => {
  if (resendCountdown) {
    clearInterval(resendCountdown);
    resendCountdown = null;
  }
};

const formatCountdown = (seconds) => {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  if (mins > 0) {
    return `${mins}m ${secs}s`;
  }
  return `${secs}s`;
};

const startResendCountdown = (seconds) => {
  clearResendCountdown();
  const resendOtpBtn = document.getElementById("reg-resend-otp");
  const resendTimer = document.getElementById("reg-resend-timer");
  if (!resendOtpBtn || !resendTimer) return;

  let remaining = Math.max(0, Number(seconds) || 0);
  resendOtpBtn.disabled = true;
  resendTimer.hidden = false;

  const tick = () => {
    if (remaining <= 0) {
      clearResendCountdown();
      resendOtpBtn.disabled = false;
      resendTimer.hidden = true;
      resendTimer.textContent = "";
      return;
    }
    resendTimer.textContent = `Resend available in ${formatCountdown(remaining)}`;
    remaining -= 1;
  };

  tick();
  resendCountdown = setInterval(tick, 1000);
};

const clearMobileVerification = () => {
  mobileVerified = false;
  verifiedMobile = "";
};

const sendRegistrationOtp = async () => {
  const mobileInput = document.getElementById("reg-mobile");
  const sendOtpBtn = document.getElementById("reg-send-otp");
  const mobile = normalizeMobile(mobileInput?.value || "");
  if (mobileInput) mobileInput.value = mobile;

  if (!isValidMobile(mobile)) {
    showOtpAlert("Please enter a valid 10-digit mobile number.");
    mobileInput?.focus();
    return;
  }

  if (verifiedMobile !== mobile) {
    clearMobileVerification();
  }

  showOtpAlert("");
  setBusy(sendOtpBtn, true, "Sending...");

  try {
    const { payload } = await postForm(API_SEND_OTP, { mobile });
    if (!payload.success) {
      showOtpAlert(payload.message || "Unable to send OTP.");
      if (payload.retry_after) {
        startResendCountdown(payload.retry_after);
      }
      return;
    }

    document.getElementById("reg-otp")?.focus();
    startResendCountdown(payload.retry_after || 120);
  } catch {
    showOtpAlert("Unable to send OTP. Please check your connection and try again.");
  } finally {
    setBusy(sendOtpBtn, false, "Send OTP to verify number");
  }
};

const verifyRegistrationOtp = async (silent = false) => {
  const mobileInput = document.getElementById("reg-mobile");
  const otpInput = document.getElementById("reg-otp");
  const mobile = normalizeMobile(mobileInput?.value || "");
  const otp = (otpInput?.value || "").replace(/\D/g, "").slice(0, OTP_LENGTH);
  if (otpInput) otpInput.value = otp;

  if (!isValidMobile(mobile)) {
    if (!silent) showOtpAlert("Please enter a valid mobile number.");
    return false;
  }

  const otpPattern = new RegExp(`^\\d{${OTP_LENGTH}}$`);
  if (!otpPattern.test(otp)) {
    if (!silent) showOtpAlert(`Please enter the ${OTP_LENGTH}-digit OTP.`);
    return false;
  }

  if (mobileVerified && verifiedMobile === mobile) {
    return true;
  }

  if (verifyInFlight) {
    return false;
  }

  verifyInFlight = true;
  if (!silent) showOtpAlert("");

  try {
    const { payload } = await postForm(API_VERIFY_OTP, { mobile, otp });
    if (!payload.success) {
      if (!silent) showOtpAlert(payload.message || "Invalid OTP. Please try again.");
      clearMobileVerification();
      return false;
    }

    mobileVerified = true;
    verifiedMobile = mobile;
    showOtpAlert("");
    return true;
  } catch {
    if (!silent) showOtpAlert("Unable to verify OTP. Please check your connection and try again.");
    return false;
  } finally {
    verifyInFlight = false;
  }
};

const initMobileOtp = () => {
  const mobileInput = document.getElementById("reg-mobile");
  const otpInput = document.getElementById("reg-otp");
  const sendOtpBtn = document.getElementById("reg-send-otp");
  const resendOtpBtn = document.getElementById("reg-resend-otp");

  mobileInput?.addEventListener("input", () => {
    if (!mobileInput) return;
    mobileInput.value = normalizeMobile(mobileInput.value);
    if (mobileVerified && mobileInput.value !== verifiedMobile) {
      clearMobileVerification();
      if (otpInput) otpInput.value = "";
    }
  });

  otpInput?.addEventListener("input", () => {
    if (!otpInput) return;
    otpInput.value = otpInput.value.replace(/\D/g, "").slice(0, OTP_LENGTH);
    if (mobileVerified) {
      clearMobileVerification();
    }
    if (otpInput.value.length === OTP_LENGTH) {
      verifyRegistrationOtp(true);
    }
  });

  sendOtpBtn?.addEventListener("click", sendRegistrationOtp);
  resendOtpBtn?.addEventListener("click", sendRegistrationOtp);
};

const formatPlaceName = (value) =>
  value
    .trim()
    .toLowerCase()
    .replace(/\s+/g, " ")
    .replace(/\b([a-z])/g, (_, char) => char.toUpperCase());

const lookupPincode = async (pincode, signal) => {
  const response = await fetch(`https://postal-pincode-api.vercel.app/api/v1/pincode/${pincode}`, {
    signal,
    mode: "cors",
    credentials: "omit",
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new Error("lookup_failed");
  }

  const data = await response.json();
  const rows = data?.data;
  if (!Array.isArray(rows) || !rows.length) {
    return null;
  }

  const row = rows.find((entry) => entry.state && entry.district) || rows[0];
  const state = formatPlaceName(row.state || "");
  const district = formatPlaceName(row.district || "");
  return state && district ? { state, district } : null;
};

const renderFee = (settings) => {
  const feeEl = document.querySelector("[data-registration-fee]");
  const labelEl = document.querySelector("[data-fee-label]");
  const amountEl = document.querySelector("[data-fee-amount]");
  if (!feeEl || !labelEl || !amountEl || !settings) return;

  const showFee =
    settings.payment_enabled &&
    settings.amount_paise > 0 &&
    settings.payment_configured;

  if (!showFee) {
    feeEl.hidden = true;
    return;
  }

  labelEl.textContent = settings.fee_label || "Registration Fee";
  amountEl.textContent = settings.amount_display || "";
  feeEl.hidden = false;
};

const loadPaymentSettings = async () => {
  try {
    const response = await fetch(API_SETTINGS, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const payload = await response.json();
    if (!response.ok || !payload.success || !payload.data) {
      return null;
    }
    paymentSettings = payload.data;
    renderFee(paymentSettings);
    return paymentSettings;
  } catch {
    return null;
  }
};

const initPincodeLookup = () => {
  const pincodeInput = form?.querySelector('[name="pincode"]');
  const stateInput = form?.querySelector('[name="state"]');
  const districtInput = form?.querySelector('[name="district"]');
  const hintEl = form?.querySelector("[data-pincode-hint]");
  const pincodeField = pincodeInput?.closest(".form-field");

  if (!pincodeInput || !stateInput || !districtInput) {
    return;
  }

  let debounceTimer = null;
  let abortController = null;
  let lastFetchedPin = "";

  const setHint = (message, tone = "idle") => {
    if (!hintEl) return;
    hintEl.textContent = message;
    hintEl.hidden = !message;
    hintEl.classList.remove("is-success", "is-warning", "is-loading");
    if (tone !== "idle") hintEl.classList.add(`is-${tone}`);
  };

  const clearAutoFilled = () => {
    if (stateInput.dataset.autoFilled === "true") {
      stateInput.value = "";
      delete stateInput.dataset.autoFilled;
    }
    if (districtInput.dataset.autoFilled === "true") {
      districtInput.value = "";
      delete districtInput.dataset.autoFilled;
    }
  };

  const runLookup = async (pincode) => {
    if (pincode.length !== 6 || pincode === lastFetchedPin) return;

    abortController?.abort();
    abortController = new AbortController();
    pincodeField?.classList.add("is-loading");
    setHint("Fetching state and district…", "loading");

    try {
      const location = await lookupPincode(pincode, abortController.signal);
      if (pincodeInput.value !== pincode) return;

      if (location) {
        lastFetchedPin = pincode;
        stateInput.value = location.state;
        stateInput.dataset.autoFilled = "true";
        districtInput.value = location.district;
        districtInput.dataset.autoFilled = "true";
        setHint("State and district filled automatically.", "success");
        return;
      }

      lastFetchedPin = "";
      clearAutoFilled();
      setHint("Pincode not found. Please enter state and district manually.", "warning");
    } catch (error) {
      if (error.name === "AbortError" || pincodeInput.value !== pincode) return;
      lastFetchedPin = "";
      setHint("Could not fetch location. Please enter state and district manually.", "warning");
    } finally {
      pincodeField?.classList.remove("is-loading");
    }
  };

  pincodeInput.addEventListener("input", () => {
    pincodeInput.value = pincodeInput.value.replace(/\D/g, "").slice(0, 6);
    clearTimeout(debounceTimer);
    abortController?.abort();
    pincodeField?.classList.remove("is-loading");

    const pincode = pincodeInput.value;
    if (pincode.length < 6) {
      if (lastFetchedPin && pincode !== lastFetchedPin) {
        clearAutoFilled();
        lastFetchedPin = "";
      }
      setHint("", "idle");
      return;
    }

    debounceTimer = setTimeout(() => runLookup(pincode), 350);
  });

  pincodeInput.addEventListener("blur", () => {
    const pincode = pincodeInput.value;
    if (pincode.length === 6 && pincode !== lastFetchedPin) {
      runLookup(pincode);
    }
  });

  stateInput.addEventListener("input", () => delete stateInput.dataset.autoFilled);
  districtInput.addEventListener("input", () => delete districtInput.dataset.autoFilled);
};

const initForm = () => {
  if (!form || form.dataset.ready === "true") return;
  form.dataset.ready = "true";

  initMobileOtp();
  initPincodeLookup();

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const mobile = normalizeMobile(form.querySelector('[name="mobile"]')?.value || "");
    if (!mobileVerified || mobile !== verifiedMobile) {
      const verified = await verifyRegistrationOtp(false);
      if (!verified) {
        showToast("Please verify your mobile number with OTP before submitting.");
        return;
      }
    }

    form.querySelectorAll("input:not([type='hidden'])").forEach((input) => {
      input.value = input.value.trim();
    });

    if (!form.checkValidity()) {
      form.querySelector(":invalid")?.focus();
      form.reportValidity();
      return;
    }

    const button = submitButton();
    const submitLabel = button?.querySelector("span");
    if (button) button.disabled = true;
    if (submitLabel) submitLabel.textContent = "Submitting…";

    try {
      const token = csrfToken || (await fetchCsrfToken());
      const formData = new FormData(form);
      formData.append("csrf_token", token);

      const response = await fetch(API_SUBMIT, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();

      if (!response.ok || !payload.success) {
        showToast(payload.message || "Unable to save registration. Please try again.");
        if (payload.message?.includes("verify your mobile")) {
          clearMobileVerification();
        }
        return;
      }

      if (payload.payment_required && payload.payment_url) {
        showToast(payload.message || "Proceed For The Payment");
        setTimeout(() => {
          window.location.href = payload.payment_url;
        }, 600);
        return;
      }

      showToast(payload.message || "Thank you. Your registration is complete.");
    } catch {
      showToast("Network error. Please check your connection and try again.");
    } finally {
      if (button) button.disabled = false;
      if (submitLabel) submitLabel.textContent = "Register Now";
    }
  });
};

fetchCsrfToken()
  .then(() => {
    loadPaymentSettings();
    initForm();
  })
  .catch(() => {
    showToast("Unable to initialize registration. Please refresh the page.");
  });
