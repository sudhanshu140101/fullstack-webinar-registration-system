<?php

declare(strict_types=1);

/**
 * @param array{
 *   index: int|string,
 *   card_id?: int,
 *   message?: string,
 *   image_path?: string|null,
 *   is_new?: bool,
 *   new_index?: int|string
 * } $item
 */
function render_policy_card_editor(array $item): void
{
    $index = $item['index'];
    $indexKey = is_numeric($index) ? (string) (int) $index : (string) $index;
    $displayNum = is_numeric($index) ? (int) $index + 1 : '__NUMBER__';
    $isNew = !empty($item['is_new']);
    $cardId = (int) ($item['card_id'] ?? 0);
    $newIndex = $item['new_index'] ?? 0;
    $newIndexKey = is_numeric($newIndex) ? (string) (int) $newIndex : (string) $newIndex;
    $message = (string) ($item['message'] ?? '');
    $preview = policy_advocacy_preview_src($item['image_path'] ?? null);
    $label = $isNew ? 'New card' : 'Card ' . (is_numeric($index) ? ((int) $index + 1) : '');
    $cardClass = 'policy-card-editor' . ($isNew ? ' policy-card-editor--new' : '');
    ?>
    <article
      class="<?= e($cardClass) ?>"
      data-card-index="<?= e($indexKey) ?>"
      <?= $isNew ? 'data-new-card="1" data-new-index="' . e($newIndexKey) . '"' : 'data-card-id="' . $cardId . '"' ?>
    >
      <div class="policy-card-editor-head">
        <div class="policy-card-editor-title">
          <span class="policy-card-number"><?= is_numeric($index) ? (int) $index + 1 : e((string) $displayNum) ?></span>
          <strong class="policy-card-label"><?= e($label) ?></strong>
        </div>
        <div class="policy-card-editor-actions">
          <button type="button" class="btn btn-ghost btn-small policy-move-up" aria-label="Move card up">↑</button>
          <button type="button" class="btn btn-ghost btn-small policy-move-down" aria-label="Move card down">↓</button>
          <?php if ($isNew): ?>
            <button type="button" class="btn btn-ghost btn-small policy-remove-new" aria-label="Remove new card">Remove</button>
          <?php else: ?>
            <button type="button" class="btn btn-ghost btn-small policy-mark-remove" aria-label="Mark card for removal">Remove</button>
            <input type="hidden" name="cards[<?= e($indexKey) ?>][delete]" value="0" class="policy-delete-flag" />
          <?php endif; ?>
        </div>
      </div>

      <div class="policy-card-editor-body">
        <div class="policy-card-editor-media">
          <div class="policy-admin-preview">
            <img src="<?= e($preview) ?>" alt="" width="480" height="300" class="policy-preview-image" />
            <span class="policy-preview-badge">Preview</span>
          </div>
          <label class="policy-file-label">
            <span><?= $isNew ? 'Upload photo' : 'Replace photo' ?><?= $isNew ? ' <em>(required)</em>' : '' ?></span>
            <?php if ($isNew): ?>
              <input
                type="file"
                name="new_card_image_<?= e($newIndexKey) ?>"
                class="policy-image-input"
                accept="image/jpeg,image/png,image/webp"
                required
              />
            <?php else: ?>
              <input type="hidden" name="cards[<?= e($indexKey) ?>][id]" value="<?= $cardId ?>" />
              <input
                type="file"
                name="card_image_<?= $cardId ?>"
                class="policy-image-input"
                accept="image/jpeg,image/png,image/webp"
              />
            <?php endif; ?>
            <small class="field-hint">JPG, PNG or WEBP · max 5 MB</small>
          </label>
        </div>

        <div class="policy-card-editor-fields">
          <?php if ($isNew): ?>
            <input type="hidden" name="new_cards[<?= e($newIndexKey) ?>][sort_order]" value="<?= e($indexKey) ?>" class="card-sort-order" />
          <?php else: ?>
            <input type="hidden" name="cards[<?= e($indexKey) ?>][sort_order]" value="<?= e($indexKey) ?>" class="card-sort-order" />
          <?php endif; ?>

          <label class="field">
            <span>Short message on photo <small>(optional)</small></span>
            <?php if ($isNew): ?>
              <textarea
                name="new_cards[<?= e($newIndexKey) ?>][message]"
                rows="4"
                maxlength="2000"
                placeholder="Message displayed on the card photo…"
              ></textarea>
            <?php else: ?>
              <textarea
                name="cards[<?= e($indexKey) ?>][message]"
                rows="4"
                maxlength="2000"
                placeholder="Message displayed on the card photo…"
              ><?= e($message) ?></textarea>
            <?php endif; ?>
          </label>
        </div>
      </div>
    </article>
    <?php
}
