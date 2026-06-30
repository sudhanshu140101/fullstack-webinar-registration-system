<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';

$repo = new HeroRepository();
$statusMessage = '';
$statusType = 'success';
$maxHighlights = HeroRepository::maxHighlights();

$section = $repo->getSection() ?? [
    'badge' => '',
    'title' => '',
    'subtitle' => '',
    'guest_label' => '',
    'guest_name' => '',
    'guest_role' => '',
    'copy_text' => '',
    'register_url' => 'register.html',
    'chat_url' => '',
    'fee_note' => '',
    'is_active' => 1,
];
$metaItems = $repo->listMetaItems();
$highlights = $repo->listHighlights();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token. Please try again.';
        $statusType = 'error';
    } else {
        $title = Security::sanitizeString((string) ($_POST['title'] ?? ''), 200);
        $errors = [];

        if ($title === '') {
            $errors[] = 'Hero title is required.';
        }

        $postedHighlights = is_array($_POST['highlights'] ?? null) ? $_POST['highlights'] : [];
        $highlightTexts = [];
        foreach ($postedHighlights as $index => $highlight) {
            $text = Security::sanitizeString((string) ($highlight['text'] ?? ''), 80);
            if ($text === '') {
                continue;
            }
            $highlightTexts[] = [
                'text' => $text,
                'sort_order' => (int) $index,
            ];
        }

        if (count($highlightTexts) > $maxHighlights) {
            $errors[] = 'Maximum ' . $maxHighlights . ' highlight tags allowed.';
        }

        $registerUrlRaw = Security::sanitizeString((string) ($_POST['register_url'] ?? 'register.html'), 255);
        if ($registerUrlRaw === '') {
            $registerUrlRaw = 'register.html';
        }
        if (!is_valid_register_url($registerUrlRaw)) {
            $errors[] = 'Register button link must be a safe relative path or http(s) URL.';
        }

        $chatUrlRaw = Security::sanitizeString((string) ($_POST['chat_url'] ?? ''), 500);
        if ($chatUrlRaw !== '' && !is_valid_external_url($chatUrlRaw)) {
            $errors[] = 'Chat button link must be a valid http(s) URL.';
        }

        $metaPayload = [];
        $postedMeta = is_array($_POST['meta'] ?? null) ? $_POST['meta'] : [];
        ksort($postedMeta, SORT_NUMERIC);
        foreach ($postedMeta as $index => $meta) {
            $label = Security::sanitizeString((string) ($meta['label'] ?? ''), 40);
            $value = Security::sanitizeString((string) ($meta['value'] ?? ''), 200);
            $icon = Security::sanitizeString((string) ($meta['icon'] ?? '📌'), 16);
            if ($label === '' && $value === '') {
                continue;
            }
            if ($label === '' || $value === '') {
                $errors[] = 'Each event detail needs both a label and a value, or leave both empty.';
                break;
            }
            $metaPayload[] = [
                'label' => $label,
                'value' => $value,
                'icon' => $icon !== '' ? $icon : '📌',
                'sort_order' => (int) $index,
            ];
        }

        if ($errors === []) {
            try {
                $repo->updateSection([
                    'badge' => Security::sanitizeString((string) ($_POST['badge'] ?? ''), 80),
                    'title' => $title,
                    'subtitle' => Security::sanitizeString((string) ($_POST['subtitle'] ?? ''), 2000),
                    'guest_label' => Security::sanitizeString((string) ($_POST['guest_label'] ?? ''), 80),
                    'guest_name' => Security::sanitizeString((string) ($_POST['guest_name'] ?? ''), 120),
                    'guest_role' => Security::sanitizeString((string) ($_POST['guest_role'] ?? ''), 2000),
                    'copy_text' => Security::sanitizeString((string) ($_POST['copy_text'] ?? ''), 2000),
                    'register_url' => $registerUrlRaw,
                    'chat_url' => $chatUrlRaw !== '' ? $chatUrlRaw : null,
                    'fee_note' => Security::sanitizeString((string) ($_POST['fee_note'] ?? ''), 255),
                    'is_active' => isset($_POST['is_active']),
                ]);
                $repo->replaceMetaItems($metaPayload);
                $repo->replaceHighlights($highlightTexts);

                redirect('hero.php?saved=1');
            } catch (Throwable $exception) {
                log_app('Hero save failed', ['error' => $exception->getMessage()]);
                $statusMessage = $exception->getMessage() ?: 'Could not save changes. Please try again.';
                $statusType = 'error';
            }
        } else {
            $statusMessage = implode(' ', $errors);
            $statusType = 'error';
        }

        $section = [
            'badge' => (string) ($_POST['badge'] ?? ''),
            'title' => $title,
            'subtitle' => (string) ($_POST['subtitle'] ?? ''),
            'guest_label' => (string) ($_POST['guest_label'] ?? ''),
            'guest_name' => (string) ($_POST['guest_name'] ?? ''),
            'guest_role' => (string) ($_POST['guest_role'] ?? ''),
            'copy_text' => (string) ($_POST['copy_text'] ?? ''),
            'register_url' => (string) ($_POST['register_url'] ?? 'register.html'),
            'chat_url' => (string) ($_POST['chat_url'] ?? ''),
            'fee_note' => (string) ($_POST['fee_note'] ?? ''),
            'is_active' => isset($_POST['is_active']),
        ];
        $metaItems = [];
        foreach ($postedMeta as $index => $meta) {
            $metaItems[] = [
                'label' => (string) ($meta['label'] ?? ''),
                'value' => (string) ($meta['value'] ?? ''),
                'icon' => (string) ($meta['icon'] ?? '📌'),
                'sort_order' => (int) $index,
            ];
        }
        $highlights = [];
        foreach ($highlightTexts as $item) {
            $highlights[] = ['text' => $item['text'], 'sort_order' => $item['sort_order']];
        }
    }
}

if (isset($_GET['saved'])) {
    $statusMessage = 'Hero section saved successfully.';
}

$section = $repo->getSection() ?? $section;
$metaItems = $repo->listMetaItems();
$highlights = $repo->listHighlights();

while (count($metaItems) < 4) {
    $metaItems[] = [
        'label' => '',
        'value' => '',
        'icon' => '📌',
        'sort_order' => count($metaItems),
    ];
}

$highlightCount = count($highlights);
$csrfToken = Security::getCsrfToken();
$pageTitle = 'Hero Section';
$activeNav = 'hero';

require __DIR__ . '/includes/header.php';
?>

<?php if ($statusMessage !== ''): ?>
  <div class="alert alert-<?= $statusType === 'error' ? 'error' : 'success' ?>" role="alert"><?= e($statusMessage) ?></div>
<?php endif; ?>

<form class="hero-admin-form" method="post" id="hero-admin-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />

  <div class="hero-admin-layout">
    <section class="panel hero-panel-settings">
      <div class="panel-head">
        <h2>Main headline</h2>
        <p class="panel-sub">Badge, title, and subtitle shown at the top of the homepage hero.</p>
      </div>

      <div class="admin-form">
        <label class="field">
          <span>Badge <small>(optional)</small></span>
          <input type="text" name="badge" maxlength="80" value="<?= e((string) ($section['badge'] ?? '')) ?>" placeholder="RECOGNITION OF MSMEs" />
        </label>

        <label class="field">
          <span>Title <small>(required)</small></span>
          <input type="text" name="title" maxlength="200" required value="<?= e((string) ($section['title'] ?? '')) ?>" placeholder="MSME CONNECT Summit 2026" />
        </label>

        <label class="field">
          <span>Subtitle</span>
          <textarea name="subtitle" rows="3" maxlength="2000" placeholder="Short description under the title…"><?= e((string) ($section['subtitle'] ?? '')) ?></textarea>
        </label>

        <label class="field field-checkbox">
          <input type="checkbox" name="is_active" value="1" id="hero-is-active" <?= !empty($section['is_active']) ? 'checked' : '' ?> />
          <span>Use CMS content on the homepage <small>(uncheck to show built-in fallback text)</small></span>
        </label>

        <span class="policy-status-pill <?= !empty($section['is_active']) ? 'is-live' : 'is-hidden' ?>" id="hero-status-pill">
          <?= !empty($section['is_active']) ? 'Live on site' : 'Using fallback content' ?>
        </span>
      </div>
    </section>

    <section class="panel hero-panel-guest">
      <div class="panel-head">
        <h2>Expert host</h2>
        <p class="panel-sub">Featured guest card in the hero section.</p>
      </div>

      <div class="admin-form">
        <label class="field">
          <span>Label</span>
          <input type="text" name="guest_label" maxlength="80" value="<?= e((string) ($section['guest_label'] ?? '')) ?>" placeholder="Expert Host" />
        </label>

        <label class="field">
          <span>Name</span>
          <input type="text" name="guest_name" maxlength="120" value="<?= e((string) ($section['guest_name'] ?? '')) ?>" placeholder="Guest name" />
        </label>

        <label class="field">
          <span>Role / bio</span>
          <textarea name="guest_role" rows="4" maxlength="2000" placeholder="Short bio or designation…"><?= e((string) ($section['guest_role'] ?? '')) ?></textarea>
        </label>
      </div>
    </section>

    <section class="panel hero-panel-meta">
      <div class="panel-head">
        <h2>Event details</h2>
        <p class="panel-sub">Up to four info cards (date, time, venue, language). Leave a row empty to hide it.</p>
      </div>

      <div class="hero-meta-grid-admin">
        <?php foreach (array_slice($metaItems, 0, 4) as $index => $meta): ?>
          <fieldset class="hero-meta-row">
            <legend>Detail <?= (int) $index + 1 ?></legend>
            <label class="field field-inline">
              <span>Icon</span>
              <input type="text" name="meta[<?= (int) $index ?>][icon]" maxlength="16" value="<?= e((string) ($meta['icon'] ?? '📌')) ?>" class="hero-icon-input" />
            </label>
            <label class="field">
              <span>Label</span>
              <input type="text" name="meta[<?= (int) $index ?>][label]" maxlength="40" value="<?= e((string) ($meta['label'] ?? '')) ?>" placeholder="Date" />
            </label>
            <label class="field">
              <span>Value</span>
              <input type="text" name="meta[<?= (int) $index ?>][value]" maxlength="200" value="<?= e((string) ($meta['value'] ?? '')) ?>" placeholder="25th July 2026" />
            </label>
          </fieldset>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel hero-panel-highlights">
      <div class="panel-head policy-cards-toolbar">
        <div>
          <h2>Highlight tags</h2>
          <p class="panel-sub">Short tags shown below the event details.</p>
        </div>
        <button type="button" class="btn btn-primary btn-small" id="add-hero-highlight" <?= $highlightCount >= $maxHighlights ? 'disabled' : '' ?>>
          + Add tag
        </button>
      </div>

      <div class="hero-highlights-list" id="hero-highlights-list">
        <p class="hero-highlights-empty" id="hero-highlights-empty" <?= $highlightCount > 0 ? 'hidden' : '' ?>>No tags yet. Add at least one highlight tag.</p>
        <?php foreach ($highlights as $index => $highlight): ?>
            <div class="hero-highlight-row" data-highlight-row>
              <input type="text" name="highlights[<?= (int) $index ?>][text]" maxlength="80" value="<?= e((string) ($highlight['text'] ?? '')) ?>" placeholder="Government Schemes" />
              <button type="button" class="btn btn-ghost btn-small hero-remove-highlight" aria-label="Remove tag">Remove</button>
            </div>
          <?php endforeach; ?>
      </div>

      <p class="panel-sub hero-highlight-count" id="hero-highlight-count" data-max="<?= (int) $maxHighlights ?>">
        <?= (int) $highlightCount ?> / <?= (int) $maxHighlights ?> tags
      </p>
    </section>

    <section class="panel hero-panel-cta">
      <div class="panel-head">
        <h2>Body copy &amp; actions</h2>
        <p class="panel-sub">Paragraph, buttons, and registration note at the bottom of the hero.</p>
      </div>

      <div class="admin-form">
        <label class="field">
          <span>Body paragraph</span>
          <textarea name="copy_text" rows="4" maxlength="2000" placeholder="Use a new line for a line break on the site…"><?= e((string) ($section['copy_text'] ?? '')) ?></textarea>
        </label>

        <label class="field">
          <span>Register button link</span>
          <input type="text" name="register_url" maxlength="255" value="<?= e((string) ($section['register_url'] ?? 'register.html')) ?>" placeholder="register.html" />
        </label>

        <label class="field">
          <span>Chat button link <small>(WhatsApp or external URL)</small></span>
          <input type="url" name="chat_url" maxlength="500" value="<?= e((string) ($section['chat_url'] ?? '')) ?>" placeholder="https://wa.me/..." />
        </label>

        <label class="field">
          <span>Registration note</span>
          <input type="text" name="fee_note" maxlength="255" value="<?= e((string) ($section['fee_note'] ?? '')) ?>" placeholder="Registration Closes 24th June 2026" />
        </label>
      </div>
    </section>
  </div>

  <div class="form-actions policy-form-actions">
    <button type="submit" class="btn btn-primary">Save hero section</button>
    <a class="btn btn-secondary" href="../index.html#home" target="_blank" rel="noopener noreferrer">Preview on site</a>
  </div>
</form>

<template id="hero-highlight-template">
  <div class="hero-highlight-row" data-highlight-row>
    <input type="text" name="highlights[__INDEX__][text]" maxlength="80" value="" placeholder="Highlight tag" />
    <button type="button" class="btn btn-ghost btn-small hero-remove-highlight" aria-label="Remove tag">Remove</button>
  </div>
</template>

<script src="../assets/admin/hero.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
