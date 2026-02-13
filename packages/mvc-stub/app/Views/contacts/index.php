<h1 data-page-title><?= htmlspecialchars((string) ($title ?? 'Contacts'), ENT_QUOTES, 'UTF-8') ?></h1>
<p class="lead">Choose a contact to open details.</p>
<ul class="contacts-list">
   <?php foreach (($contacts ?? []) as $contact): ?>
      <li>
         <a data-contact-link href="/contacts/<?= (int) $contact->id ?>">
            <?= htmlspecialchars($contact->firstName . ' ' . $contact->lastName, ENT_QUOTES, 'UTF-8') ?>
         </a>
      </li>
   <?php endforeach; ?>
</ul>
