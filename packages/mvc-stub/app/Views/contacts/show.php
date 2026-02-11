<!doctype html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <title><?= htmlspecialchars((string) ($title ?? 'Contact'), ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body>
   <h1><?= htmlspecialchars($contact->firstName . ' ' . $contact->lastName, ENT_QUOTES, 'UTF-8') ?></h1>
   <p>Phone: <?= htmlspecialchars($contact->phone, ENT_QUOTES, 'UTF-8') ?></p>
   <p>Address: <?= htmlspecialchars($contact->address, ENT_QUOTES, 'UTF-8') ?></p>
   <p>Age: <?= (int) $contact->age ?></p>
</body>

</html>