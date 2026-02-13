<!doctype html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title><?= htmlspecialchars((string) ($title ?? 'Celeris MVC'), ENT_QUOTES, 'UTF-8') ?></title>
   <link rel="stylesheet" href="/assets/css/app.min.css">
</head>

<body>
   <?php
   $headerTemplate = __DIR__ . '/partials/header.php';
   if (is_file($headerTemplate)) {
      require $headerTemplate;
   }
   ?>
   <main class="page">
      <?= $content ?? '' ?>
   </main>
   <?php
   $footerTemplate = __DIR__ . '/partials/footer.php';
   if (is_file($footerTemplate)) {
      require $footerTemplate;
   }
   ?>
   <script src="/assets/js/app.min.js" defer></script>
</body>

</html>
