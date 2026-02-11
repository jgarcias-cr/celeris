<!doctype html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <title><?= htmlspecialchars((string) ($title ?? 'Contacts'), ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body>
   <h1><?= htmlspecialchars((string) ($title ?? 'Contacts'), ENT_QUOTES, 'UTF-8') ?></h1>
   <ul>
      <?php foreach (($contacts ?? []) as $contact): ?>
         <li>
            <a href="/contacts/<?= (int) $contact->id ?>">
               <?= htmlspecialchars($contact->firstName . ' ' . $contact->lastName, ENT_QUOTES, 'UTF-8') ?>
            </a>
         </li>
      <?php endforeach; ?>
   </ul>
</body>

</html>