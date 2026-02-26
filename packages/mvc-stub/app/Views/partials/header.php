<header class="site-header">
   <div class="page site-shell">
      <nav class="site-nav" aria-label="Primary navigation">
         <a href="/">Home</a>
         <a href="/contacts">Contacts</a>
      </nav>
      <p class="site-user">
         Welcome, <?= htmlspecialchars((string) ($username ?? 'Guest'), ENT_QUOTES, 'UTF-8') ?>
      </p>
   </div>
</header>
