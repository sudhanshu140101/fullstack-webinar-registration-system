<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$repo = new SaveTheDateRepository();
$statusMessage = '';
$statusType = 'success';
$maxDetails = SaveTheDateRepository::maxDetails();

$section = $repo->getSection() ?? [
    'badge' => '',
    'tagline' => '',
    'headline' => '',
    'copy_text' => '',
    'is_active' => 1,
];
$details = $repo->listDetails();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token. Please try again.';
        $statusType = 'error';
    } else {
        $headline = Security::sanitizeString((string) ($_POST['headline'] ?? ''), 200);
        $errors = [];

        if ($headline === '') {
            $errors[] = 'Event headline is required (e.g. 25th July 2026 · New Delhi).';
        }

        $postedDetails = is_array($_POST['details'] ?? null) ? $_POST['details'] : [];
        $detailTexts = [];
        foreach ($postedDetails as $index => $detail) {
            $text = Security::sanitizeString((string) ($detail['text'] ?? ''), 120);
            if ($text === '') {
                continue;
            }
            $detailTexts[] = [
                'text' => $text,
                'sort_order' => (int) $index,
            ];
        }

        if (count($detailTexts) > $maxDetails) {
            $errors[] = 'Maximum ' . $maxDetails . ' detail pills allowed.';
        }

        if ($errors === []) {
            try {
                $repo->updateSection([
                    'badge' => Security::sanitizeString((string) ($_POST['badge'] ?? ''), 80),
                    'tagline' => Security::sanitizeString((string) ($_POST['tagline'] ?? ''), 120),
                    'headline' => $headline,
                    'copy_text' => Security::sanitizeString((string) ($_POST['copy_text'] ?? ''), 2000),
                    'is_active' => isset($_POST['is_active']),
                ]);
                $repo->replaceDetails($detailTexts);

                redirect('save-the-date.php?saved=1');
            } catch (Throwable $exception) {
                log_app('Save the date save failed', ['error' => $exception->getMessage()]);
                $statusMessage = $exception->getMessage() ?: 'Could not save changes. Please try again.';
                $statusType = 'error';
            }
        } else {
            $statusMessage = implode(' ', $errors);
            $statusType = 'error';
        }

        $section = [
            'badge' => (string) ($_POST['badge'] ?? ''),
            'tagline' => (string) ($_POST['tagline'] ?? ''),
            'headline' => $headline,
            'copy_text' => (string) ($_POST['copy_text'] ?? ''),
            'is_active' => isset($_POST['is_active']),
        ];
        $details = [];
        foreach ($detailTexts as $item) {
            $details[] = ['text' => $item['text'], 'sort_order' => $item['sort_order']];
        }
    }
}

if (isset($_GET['saved'])) {
    $statusMessage = 'Save the Date section saved successfully.';
}

$section = $repo->getSection() ?? $section;
$details = $repo->listDetails();
$detailCount = count($details);
$csrfToken = Security::getCsrfToken();
$pageTitle = 'Save the Date';
$activeNav = 'save-the-date';

require __DIR__ . '/includes/header.php';
?>

<?php if ($statusMessage !== ''): ?>
  <div class="alert alert-<?= $statusType === 'error' ? 'error' : 'success' ?>" role="alert"><?= e($statusMessage) ?></div>
<?php endif; ?>

<form class="hero-admin-form" method="post" id="save-the-date-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />

  <div class="hero-admin-layout">
    <section class="panel hero-panel-settings">
      <div class="panel-head">
        <h2>Section heading</h2>
        <p class="panel-sub">Badge, tagline, and main event headline shown in the Save the Date block.</p>
      </div>

      <div class="admin-form">
        <label class="field">
          <span>Badge</span>
          <input type="text" name="badge" maxlength="80" value="<?= e((string) ($section['badge'] ?? '')) ?>" placeholder="Save the Date" />
        </label>

        <label class="field">
          <span>Tagline</span>
          <input type="text" name="tagline" maxlength="120" value="<?= e((string) ($section['tagline'] ?? '')) ?>" placeholder="Mark Your Calendar" />
        </label>

        <label class="field">
          <span>Event headline <small>(required)</small></span>
          <input type="text" name="headline" maxlength="200" required value="<?= e((string) ($section['headline'] ?? '')) ?>" placeholder="25th July 2026 · New Delhi" />
        </label>

        <label class="field field-checkbox">
          <input type="checkbox" name="is_active" value="1" id="std-is-active" <?= !empty($section['is_active']) ? 'checked' : '' ?> />
          <span>Use CMS content on the homepage <small>(uncheck to show built-in fallback text)</small></span>
        </label>

        <span class="policy-status-pill <?= !empty($section['is_active']) ? 'is-live' : 'is-hidden' ?>" id="std-status-pill">
          <?= !empty($section['is_active']) ? 'Live on site' : 'Using fallback content' ?>
        </span>
      </div>
    </section>

    <section class="panel hero-panel-highlights">
      <div class="panel-head policy-cards-toolbar">
        <div>
          <h2>Event detail pills</h2>
          <p class="panel-sub">Short labels shown below the headline (time, venue, session, language).</p>
        </div>
        <button type="button" class="btn btn-primary btn-small" id="add-std-detail" <?= $detailCount >= $maxDetails ? 'disabled' : '' ?>>
          + Add detail
        </button>
      </div>

      <div class="hero-highlights-list" id="std-details-list">
        <p class="hero-highlights-empty" id="std-details-empty" <?= $detailCount > 0 ? 'hidden' : '' ?>>No details yet. Add time, venue, and other event info.</p>
        <?php foreach ($details as $index => $detail): ?>
          <div class="hero-highlight-row" data-std-detail-row>
            <input type="text" name="details[<?= (int) $index ?>][text]" maxlength="120" value="<?= e((string) ($detail['text'] ?? '')) ?>" placeholder="12 PM – 3 PM" />
            <button type="button" class="btn btn-ghost btn-small std-remove-detail" aria-label="Remove detail">Remove</button>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="panel-sub hero-highlight-count" id="std-detail-count" data-max="<?= (int) $maxDetails ?>">
        <?= (int) $detailCount ?> / <?= (int) $maxDetails ?> detail pills
      </p>
    </section>

    <section class="panel hero-panel-cta">
      <div class="panel-head">
        <h2>Closing paragraph</h2>
        <p class="panel-sub">Supporting text at the bottom of the section.</p>
      </div>

      <div class="admin-form">
        <label class="field">
          <span>Body copy</span>
          <textarea name="copy_text" rows="4" maxlength="2000" placeholder="Join industry leaders, banks, NBFCs…"><?= e((string) ($section['copy_text'] ?? '')) ?></textarea>
        </label>
      </div>
    </section>
  </div>

  <div class="form-actions policy-form-actions">
    <button type="submit" class="btn btn-primary">Save section</button>
    <a class="btn btn-secondary" href="../index.html#save-the-date" target="_blank" rel="noopener noreferrer">Preview on site</a>
  </div>
</form>

<template id="std-detail-template">
  <div class="hero-highlight-row" data-std-detail-row>
    <input type="text" name="details[__INDEX__][text]" maxlength="120" value="" placeholder="Event detail" />
    <button type="button" class="btn btn-ghost btn-small std-remove-detail" aria-label="Remove detail">Remove</button>
  </div>
</template>

<script src="../assets/admin/save-the-date.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
