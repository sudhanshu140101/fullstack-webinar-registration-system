<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/policy_card_editor.php';

$repo = new PolicyAdvocacyRepository();
$uploadDir = policy_advocacy_upload_dir();
$statusMessage = '';
$statusType = 'success';
$maxCards = PolicyAdvocacyRepository::maxCards();

$section = $repo->getSection() ?? [
    'section_title' => 'Policy Advocacy',
    'section_message' => '',
    'is_active' => 1,
];
$cards = $repo->listCards();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf(is_string($csrf) ? $csrf : null)) {
        $statusMessage = 'Invalid security token. Please try again.';
        $statusType = 'error';
    } else {
        $sectionTitle = Security::sanitizeString((string) ($_POST['section_title'] ?? ''), 120);
        $sectionMessage = Security::sanitizeString((string) ($_POST['section_message'] ?? ''), 2000);
        $isActive = isset($_POST['is_active']);
        $errors = [];

        $sectionTitle = $sectionTitle !== '' ? $sectionTitle : null;
        $sectionMessage = $sectionMessage !== '' ? $sectionMessage : null;

        $postedCards = is_array($_POST['cards'] ?? null) ? $_POST['cards'] : [];
        ksort($postedCards, SORT_NUMERIC);

        $newCards = is_array($_POST['new_cards'] ?? null) ? $_POST['new_cards'] : [];
        ksort($newCards, SORT_NUMERIC);

        $keptExisting = 0;
        foreach ($postedCards as $cardData) {
            if ((string) ($cardData['delete'] ?? '0') === '1') {
                continue;
            }
            $keptExisting++;
        }

        if ($keptExisting + count($newCards) > $maxCards) {
            $errors[] = 'Maximum ' . $maxCards . ' cards allowed.';
        }

        foreach ($newCards as $index => $newCard) {
            $fileKey = 'new_card_image_' . $index;
            if (!isset($_FILES[$fileKey]) || (int) ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Each new card needs a photo.';
                break;
            }
        }

        if ($errors === []) {
            try {
                $repo->updateSection($sectionTitle, $sectionMessage, $isActive);

                foreach ($postedCards as $sortOrder => $cardData) {
                    $cardId = (int) ($cardData['id'] ?? 0);
                    if ($cardId <= 0) {
                        continue;
                    }

                    $existing = $repo->getCardById($cardId);
                    if ($existing === null) {
                        continue;
                    }

                    if ((string) ($cardData['delete'] ?? '0') === '1') {
                        $repo->deleteCard($cardId);
                        continue;
                    }

                    $cardMessage = Security::sanitizeString((string) ($cardData['message'] ?? ''), 2000);
                    $cardMessage = $cardMessage !== '' ? $cardMessage : null;
                    $imagePath = null;

                    $fileKey = 'card_image_' . $cardId;
                    if (isset($_FILES[$fileKey]) && is_array($_FILES[$fileKey])) {
                        $upload = FileUpload::handleOptional($_FILES[$fileKey], $uploadDir, true);
                        if (!$upload['ok']) {
                            throw new RuntimeException($upload['error'] ?? 'Image upload failed.');
                        }
                        if (isset($upload['stored_name'])) {
                            $repo->deleteImageFile((string) ($existing['image_path'] ?? ''));
                            $imagePath = 'uploads/policy-advocacy/' . $upload['stored_name'];
                        }
                    }

                    $repo->updateCard(
                        $cardId,
                        $cardMessage,
                        $imagePath,
                        (int) ($cardData['sort_order'] ?? $sortOrder)
                    );
                }

                foreach ($newCards as $index => $newCard) {
                    $fileKey = 'new_card_image_' . $index;
                    if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey])) {
                        continue;
                    }

                    $upload = FileUpload::handleOptional($_FILES[$fileKey], $uploadDir, true);
                    if (!$upload['ok'] || !isset($upload['stored_name'])) {
                        throw new RuntimeException($upload['error'] ?? 'Image upload failed for a new card.');
                    }

                    $cardMessage = Security::sanitizeString((string) ($newCard['message'] ?? ''), 2000);
                    $cardMessage = $cardMessage !== '' ? $cardMessage : null;
                    $sortOrder = (int) ($newCard['sort_order'] ?? (100 + (int) $index));

                    $repo->createCard(
                        $cardMessage,
                        'uploads/policy-advocacy/' . $upload['stored_name'],
                        $sortOrder
                    );
                }

                redirect('policy-advocacy.php?saved=1');
            } catch (Throwable $exception) {
                log_app('Policy advocacy save failed', ['error' => $exception->getMessage()]);
                $statusMessage = $exception->getMessage() ?: 'Could not save changes. Please try again.';
                $statusType = 'error';
            }
        } else {
            $statusMessage = implode(' ', $errors);
            $statusType = 'error';
        }

        $section = [
            'section_title' => $sectionTitle ?? '',
            'section_message' => $sectionMessage ?? '',
            'is_active' => $isActive,
        ];
    }
}

if (isset($_GET['saved'])) {
    $statusMessage = '';
}

$section = $repo->getSection() ?? $section;
$cards = $repo->listCards();
$cardCount = count($cards);
$csrfToken = Security::getCsrfToken();
$pageTitle = 'Policy Advocacy';
$activeNav = 'policy-advocacy';

require __DIR__ . '/includes/header.php';
?>

<?php if ($statusMessage !== ''): ?>
  <div class="alert alert-<?= $statusType === 'error' ? 'error' : 'success' ?>" role="alert"><?= e($statusMessage) ?></div>
<?php endif; ?>

<form class="policy-admin-form" method="post" enctype="multipart/form-data" id="policy-advocacy-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>" />

  <div class="policy-admin-layout">
    <section class="panel policy-panel-settings">
      <div class="panel-head">
        <h2>Section settings</h2>
        <p class="panel-sub">Optional heading and intro shown above the card grid on the homepage.</p>
      </div>

      <div class="admin-form policy-settings-form">
        <label class="field">
          <span>Section title <small>(optional)</small></span>
          <input type="text" name="section_title" maxlength="120" value="<?= e((string) ($section['section_title'] ?? '')) ?>" placeholder="Policy Advocacy" />
        </label>

        <label class="field">
          <span>Section intro message <small>(optional)</small></span>
          <textarea name="section_message" rows="4" maxlength="2000" placeholder="Short introduction above the cards…"><?= e((string) ($section['section_message'] ?? '')) ?></textarea>
        </label>

        <label class="field field-checkbox">
          <input type="checkbox" name="is_active" value="1" id="policy-is-active" <?= !empty($section['is_active']) ? 'checked' : '' ?> />
          <span>Show this section on the homepage</span>
        </label>

        <div class="policy-settings-meta">
          <span class="policy-status-pill <?= !empty($section['is_active']) ? 'is-live' : 'is-hidden' ?>" id="policy-status-pill">
            <?= !empty($section['is_active']) ? 'Visible on site' : 'Hidden on site' ?>
          </span>
          <span class="policy-card-count" id="policy-card-count" data-max="<?= (int) $maxCards ?>">
            <?= (int) $cardCount ?> / <?= (int) $maxCards ?> cards
          </span>
        </div>
      </div>
    </section>

    <section class="panel policy-panel-cards">
      <div class="panel-head policy-cards-toolbar">
        <div>
          <h2>Photo cards</h2>
          <p class="panel-sub">Use ↑ ↓ to reorder. Each card needs a photo; message on the photo is optional.</p>
        </div>
        <button type="button" class="btn btn-primary btn-small" id="add-policy-card" <?= $cardCount >= $maxCards ? 'disabled' : '' ?>>
          + Add card
        </button>
      </div>

      <div class="policy-cards-empty" id="policy-cards-empty" <?= $cardCount > 0 ? 'hidden' : '' ?>>
        <p>No cards yet. Add your first photo card to show this section on the homepage.</p>
      </div>

      <div class="policy-cards-list" id="policy-cards-list">
        <?php foreach ($cards as $index => $card): ?>
          <?php
            render_policy_card_editor([
                'index' => $index,
                'card_id' => (int) $card['id'],
                'message' => (string) ($card['message'] ?? ''),
                'image_path' => $card['image_path'] ?? null,
            ]);
          ?>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <div class="form-actions policy-form-actions">
    <button type="submit" class="btn btn-primary">Save all changes</button>
    <a class="btn btn-secondary" href="../index.html#policy-advocacy" target="_blank" rel="noopener noreferrer">Preview on site</a>
  </div>
</form>

<template id="policy-new-card-template">
  <?php
    render_policy_card_editor([
        'index' => '__INDEX__',
        'is_new' => true,
        'new_index' => '__NEW_INDEX__',
        'message' => '',
        'image_path' => null,
    ]);
  ?>
</template>

<script src="../assets/admin/policy-advocacy.js" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
