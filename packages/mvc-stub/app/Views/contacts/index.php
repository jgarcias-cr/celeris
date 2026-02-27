<h1 data-page-title><?= htmlspecialchars((string) ($title ?? 'Contacts'), ENT_QUOTES, 'UTF-8') ?></h1>
<p class="lead">Manage your contacts with quick create, edit, and delete actions.</p>
<?php if (!empty($notice)): ?>
   <p class="form-notice"><?= htmlspecialchars((string) $notice, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<div class="contacts-toolbar">
   <a class="btn btn-primary" href="/contacts/create">Add Contact</a>
</div>
<section class="contacts-table-card">
   <div class="contacts-table-wrap">
      <table class="contacts-table" data-contacts-table>
         <thead>
            <tr>
               <th scope="col">Name</th>
               <th scope="col">Phone</th>
               <th scope="col">Address</th>
               <th scope="col">Actions</th>
            </tr>
         </thead>
         <tbody>
            <?php foreach (($contacts ?? []) as $contact): ?>
               <tr data-contact-row>
                  <td><?= htmlspecialchars($contact->firstName . ' ' . $contact->lastName, ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string) ($contact->phone ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string) ($contact->address ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                     <div class="table-actions">
                        <a class="table-link" data-contact-link href="/contacts/<?= (int) $contact->id ?>/edit">Edit</a>
                        <form method="post" action="/contacts/<?= (int) $contact->id ?>/delete" data-confirm="Delete this contact?">
                           <button type="submit" class="table-link danger-link">Delete</button>
                        </form>
                     </div>
                  </td>
               </tr>
            <?php endforeach; ?>
            <?php if (empty($contacts)): ?>
               <tr data-contact-row>
                  <td colspan="4">
                     No contacts yet.
                     <a class="table-link" href="/contacts/create">Create the first one</a>.
                  </td>
               </tr>
            <?php endif; ?>
         </tbody>
      </table>
   </div>

   <div class="contacts-pagination" data-pagination>
      <div class="pagination-size">
         <label for="page-size">Rows per page</label>
         <select id="page-size" data-page-size>
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
         </select>
      </div>

      <div class="pagination-nav">
         <button type="button" data-pagination-prev>Previous</button>
         <span data-pagination-info>Page 1 of 1</span>
         <button type="button" data-pagination-next>Next</button>
      </div>
   </div>
</section>
