const API_CSRF = "api/csrf.php";
const API_SEND_OTP = "api/login-otp-send.php";
const API_VERIFY_OTP = "api/login-otp-verify.php";

const form = document.getElementById("login-form");
const alertBox = document.getElementById("login-alert");
const mobileStep = document.getElementById("login-step-mobile");
const otpStep = document.getElementById("login-step-otp");
const mobileInput = document.getElementById("login-mobile");
const otpInput = document.getElementById("login-otp");
const sendOtpBtn = document.getElementById("login-send-otp");
const verifyOtpBtn = document.getElementById("login-verify-otp");
const resendOtpBtn = document.getElementById("login-resend-otp");
const resendTimer = document.getElementById("login-resend-timer");
const changeMobileBtn = document.getElementById("login-change-mobile");

let csrfToken = form?.querySelector('input[name="csrf_token"]')?.value || "";
let resendCountdown = null;

const normalizeMobile = (value) => value.replace(/\D/g, "").slice(0, 10);

const isValidMobile = (mobile) => /^[6-9][0-9]{9}$/.test(mobile);

const showAlert = (message, type = "error") => {
  if (!alertBox) return;
  if (!message) {
    alertBox.hidden = true;
    alertBox.textContent = "";
    return;
  }
  alertBox.textContent = message;
  alertBox.classList.remove("user-alert-error", "user-alert-success");
  alertBox.classList.add(type === "success" ? "user-alert-success" : "user-alert-error");
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

const refreshCsrfToken = async () => {
  const response = await fetch(API_CSRF, {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  });
  const payload = await response.json();
  if (!response.ok || !payload.success || !payload.token) {
    throw new Error("csrf_unavailable");
  }
  csrfToken = payload.token;
  const hidden = form?.querySelector('input[name="csrf_token"]');
  if (hidden) {
    hidden.value = csrfToken;
  }
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
    await refreshCsrfToken();
    return postForm(url, fields);
  }

  return { response, payload };
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

const showOtpStep = () => {
  if (!mobileStep || !otpStep) return;
  mobileStep.hidden = true;
  otpStep.hidden = false;
  otpInput?.focus();
};

const showMobileStep = () => {
  if (!mobileStep || !otpStep) return;
  otpStep.hidden = true;
  mobileStep.hidden = false;
  if (otpInput) otpInput.value = "";
  showAlert("");
  clearResendCountdown();
  if (resendOtpBtn) resendOtpBtn.disabled = false;
  if (resendTimer) resendTimer.hidden = true;
  mobileInput?.focus();
};

const sendOtp = async () => {
  const mobile = normalizeMobile(mobileInput?.value || "");
  if (mobileInput) mobileInput.value = mobile;

  if (!isValidMobile(mobile)) {
    showAlert("Please enter a valid 10-digit mobile number.");
    mobileInput?.focus();
    return;
  }

  showAlert("");
  setBusy(sendOtpBtn, true, "Sending...");

  try {
    const { response, payload } = await postForm(API_SEND_OTP, { mobile });
    if (!payload.success) {
      showAlert(payload.message || "Unable to send OTP.");
      if (payload.retry_after) {
        startResendCountdown(payload.retry_after);
      }
      return;
    }

    showOtpStep();
    showAlert("");
    startResendCountdown(payload.retry_after || 120);
  } catch {
    showAlert("Unable to send OTP. Please check your connection and try again.");
  } finally {
    setBusy(sendOtpBtn, false, "Send OTP");
  }
};

const verifyOtp = async () => {
  const mobile = normalizeMobile(mobileInput?.value || "");
  const otp = (otpInput?.value || "").replace(/\D/g, "").slice(0, 6);
  if (otpInput) otpInput.value = otp;

  if (!isValidMobile(mobile)) {
    showAlert("Please enter a valid mobile number.");
    showMobileStep();
    return;
  }

  if (!/^\d{6}$/.test(otp)) {
    showAlert("Please enter the 6-digit OTP.");
    otpInput?.focus();
    return;
  }

  showAlert("");
  setBusy(verifyOtpBtn, true, "Verifying...");

  try {
    const { payload } = await postForm(API_VERIFY_OTP, { mobile, otp });
    if (!payload.success) {
      showAlert(payload.message || "Invalid OTP. Please try again.");
      otpInput?.focus();
      return;
    }

    window.location.href = "dashboard.php";
  } catch {
    showAlert("Unable to verify OTP. Please check your connection and try again.");
  } finally {
    setBusy(verifyOtpBtn, false, "Verify & Login");
  }
};

sendOtpBtn?.addEventListener("click", sendOtp);
verifyOtpBtn?.addEventListener("click", verifyOtp);
resendOtpBtn?.addEventListener("click", sendOtp);
changeMobileBtn?.addEventListener("click", showMobileStep);

mobileInput?.addEventListener("input", () => {
  if (mobileInput) {
    mobileInput.value = normalizeMobile(mobileInput.value);
  }
});

otpInput?.addEventListener("input", () => {
  if (otpInput) {
    otpInput.value = otpInput.value.replace(/\D/g, "").slice(0, 6);
  }
});

form?.addEventListener("submit", (event) => {
  event.preventDefault();
  if (!otpStep?.hidden) {
    verifyOtp();
  } else {
    sendOtp();
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Enter") return;
  if (!otpStep?.hidden) {
    event.preventDefault();
    verifyOtp();
  }
});

refreshCsrfToken().catch(() => {
  showAlert("Unable to initialize login. Please refresh the page.");
});
