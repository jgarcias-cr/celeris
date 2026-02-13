<h1><?= htmlspecialchars($contact->firstName . ' ' . $contact->lastName, ENT_QUOTES, 'UTF-8') ?></h1>
<article class="contact-card">
   <p><strong>Phone:</strong> <?= htmlspecialchars($contact->phone, ENT_QUOTES, 'UTF-8') ?></p>
   <p><strong>Address:</strong> <?= htmlspecialchars($contact->address, ENT_QUOTES, 'UTF-8') ?></p>
   <p><strong>Age:</strong> <?= (int) $contact->age ?></p>
</article>
<p>
   <a class="back-link" href="/contacts">Back to contacts</a>
</p>
