<?php
$values = is_array($values ?? null) ? $values : [];
$errorText = (string) ($error ?? '');
$actionUrl = (string) ($action ?? '/contacts/create');
$submit = (string) ($submitLabel ?? 'Save');
?>
<h1><?= htmlspecialchars((string) ($title ?? (($mode ?? 'create') === 'edit' ? 'Edit Contact' : 'Add Contact')), ENT_QUOTES, 'UTF-8') ?></h1>
<p class="lead">Fill out the fields below and save your changes.</p>

<?php if ($errorText !== ''): ?>
   <p class="form-error"><?= htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<section class="contact-form-card">
   <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="contact-form-grid">
      <label>
         <span>First Name</span>
         <input type="text" name="first_name" required value="<?= htmlspecialchars((string) ($values['first_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <label>
         <span>Last Name</span>
         <input type="text" name="last_name" required value="<?= htmlspecialchars((string) ($values['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <label>
         <span>Phone</span>
         <input type="text" name="phone" value="<?= htmlspecialchars((string) ($values['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <label>
         <span>Address</span>
         <input type="text" name="address" value="<?= htmlspecialchars((string) ($values['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <label>
         <span>Age</span>
         <input type="number" min="0" step="1" name="age" required value="<?= htmlspecialchars((string) ($values['age'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <div class="contact-form-actions">
         <button type="submit" class="btn btn-primary"><?= htmlspecialchars($submit, ENT_QUOTES, 'UTF-8') ?></button>
         <a class="btn btn-secondary" href="/contacts">Cancel</a>
      </div>
   </form>
</section>
