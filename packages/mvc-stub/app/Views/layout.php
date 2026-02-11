<!doctype html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <title><?= htmlspecialchars((string) ($title ?? 'Celeris MVC'), ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body>
   <?= $content ?? '' ?>
</body>

</html>