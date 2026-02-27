<h1><?= htmlspecialchars($contact->firstName . ' ' . $contact->lastName, ENT_QUOTES, 'UTF-8') ?></h1>
<article class="contact-card">
   <p><strong>Phone:</strong> <?= htmlspecialchars($contact->phone, ENT_QUOTES, 'UTF-8') ?></p>
   <p><strong>Address:</strong> <?= htmlspecialchars($contact->address, ENT_QUOTES, 'UTF-8') ?></p>
   <p><strong>Age:</strong> <?= (int) $contact->age ?></p>
</article>
<div class="contact-form-actions">
   <a class="btn btn-primary" href="/contacts/<?= (int) $contact->id ?>/edit">Edit Contact</a>
   <a class="btn btn-secondary" href="/contacts">Back to contacts</a>
</div>
