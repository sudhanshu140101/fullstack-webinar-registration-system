<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$repo = new SeatsUrgencyRepository();
$statusMessage = '';
$statusType = 'success';

$section = $repo->getSection() ?? [
    'message_text' => '',
    'spots_left' => 0,
    'progress_percent' => 0,
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token. Please try again.';
        $statusType = 'error';
    } else {
        $message = Security::sanitizeString((string) ($_POST['message_text'] ?? ''), 255);
        $spotsLeft = max(0, min(9999, (int) ($_POST['spots_left'] ?? 0)));
        $progress = max(0, min(100, (int) ($_POST['progress_percent'] ?? 0)));
        $errors = [];

        if ($message === '') {
            $errors[] = 'Urgency message is required.';
        }

        if ($errors === []) {
            try {
                $repo->updateSection([
                    'message_text' => $message,
                    'spots_left' => $spotsLeft,
                    'progress_percent' => $progress,
                    'is_active' => isset($_POST['is_active']),
                ]);

                redirect('seats-urgency.php?saved=1');
            } catch (Throwable $exception) {
                log_app('Seats urgency save failed', ['error' => $exception->getMessage()]);
                $statusMessage = $exception->getMessage() ?: 'Could not save changes. Please try again.';
                $statusType = 'error';
            }
        } else {
            $statusMessage = implode(' ', $errors);
            $statusType = 'error';
        }

        $section = [
            'message_text' => $message,
            'spots_left' => $spotsLeft,
            'progress_percent' => $progress,
            'is_active' => isset($_POST['is_active']),
        ];
    }
}

if (isset($_GET['saved'])) {
    $statusMessage = 'Seats urgency banner saved successfully.';
}

$section = $repo->getSection() ?? $section;
$csrfToken = Security::getCsrfToken();
$pageTitle = 'Seats Urgency';
$activeNav = 'seats-urgency';

require __DIR__ . '/includes/header.php';
?>

<?php if ($statusMessage !== ''): ?>
  <div class="alert alert-<?= $statusType === 'error' ? 'error' : 'success' ?>" role="alert"><?= e($statusMessage) ?></div>
<?php endif; ?>

<form class="hero-admin-form" method="post">
  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />

  <div class="hero-admin-layout">
    <section class="panel hero-panel-settings">
      <div class="panel-head">
        <h2>Hero urgency banner</h2>
        <p class="panel-sub">Shown at the top of the homepage hero, below the navigation bar.</p>
      </div>

      <div class="admin-form">
        <label class="field">
          <span>Urgency message <small>(required)</small></span>
          <input
            type="text"
            name="message_text"
            maxlength="255"
            required
            value="<?= e((string) ($section['message_text'] ?? '')) ?>"
            placeholder="Hurry, seats for Sunday are low......"
          />
        </label>

        <div class="hero-admin-grid-2">
          <label class="field">
            <span>Spots left</span>
            <input
              type="number"
              name="spots_left"
              min="0"
              max="9999"
              step="1"
              required
              value="<?= (int) ($section['spots_left'] ?? 0) ?>"
            />
          </label>

          <label class="field">
            <span>Progress bar fill (%)</span>
            <input
              type="number"
              name="progress_percent"
              min="0"
              max="100"
              step="1"
              required
              value="<?= (int) ($section['progress_percent'] ?? 0) ?>"
            />
          </label>
        </div>

        <label class="field field-checkbox">
          <input
            type="checkbox"
            name="is_active"
            value="1"
            id="seats-is-active"
            <?= !empty($section['is_active']) ? 'checked' : '' ?>
          />
          <span>Show banner on the homepage <small>(uncheck to hide)</small></span>
        </label>

        <span class="policy-status-pill <?= !empty($section['is_active']) ? 'is-live' : 'is-hidden' ?>" id="seats-status-pill">
          <?= !empty($section['is_active']) ? 'Live on site' : 'Hidden on site' ?>
        </span>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>Preview</h2>
        <p class="panel-sub">Approximate appearance on the homepage.</p>
      </div>

      <div class="seats-urgency-preview" id="seats-preview">
        <div class="seats-urgency-banner seats-urgency-banner--preview">
          <div class="seats-urgency-header">
            <p class="seats-urgency-message" id="seats-preview-message"><?= e((string) ($section['message_text'] ?? '')) ?></p>
            <p class="seats-urgency-spots">
              <strong id="seats-preview-count"><?= (int) ($section['spots_left'] ?? 0) ?></strong> spots left
            </p>
          </div>
          <div
            class="seats-urgency-progress"
            role="progressbar"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="<?= (int) ($section['progress_percent'] ?? 0) ?>"
          >
            <div
              class="seats-urgency-progress-fill"
              id="seats-preview-fill"
              style="width: <?= (int) ($section['progress_percent'] ?? 0) ?>%;"
            ></div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <div class="form-actions policy-form-actions">
    <button type="submit" class="btn btn-primary">Save banner</button>
    <a class="btn btn-secondary" href="../index.html#home" target="_blank" rel="noopener noreferrer">Preview on site</a>
  </div>
</form>

<script>
(() => {
  const messageInput = document.querySelector('input[name="message_text"]');
  const spotsInput = document.querySelector('input[name="spots_left"]');
  const progressInput = document.querySelector('input[name="progress_percent"]');
  const activeInput = document.getElementById('seats-is-active');
  const statusPill = document.getElementById('seats-status-pill');
  const previewMessage = document.getElementById('seats-preview-message');
  const previewCount = document.getElementById('seats-preview-count');
  const previewFill = document.getElementById('seats-preview-fill');
  const progressBar = previewFill?.closest('[role="progressbar"]');

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const syncPreview = () => {
    if (previewMessage && messageInput) {
      previewMessage.textContent = messageInput.value.trim() || 'Urgency message';
    }
    if (previewCount && spotsInput) {
      previewCount.textContent = String(clamp(Number(spotsInput.value) || 0, 0, 9999));
    }
    if (previewFill && progressInput) {
      const percent = clamp(Number(progressInput.value) || 0, 0, 100);
      previewFill.style.width = `${percent}%`;
      if (progressBar) {
        progressBar.setAttribute('aria-valuenow', String(percent));
      }
    }
    if (statusPill && activeInput) {
      statusPill.textContent = activeInput.checked ? 'Live on site' : 'Hidden on site';
      statusPill.classList.toggle('is-live', activeInput.checked);
      statusPill.classList.toggle('is-hidden', !activeInput.checked);
    }
  };

  [messageInput, spotsInput, progressInput, activeInput].forEach((input) => {
    input?.addEventListener('input', syncPreview);
    input?.addEventListener('change', syncPreview);
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
