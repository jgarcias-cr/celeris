<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Celeris\Framework\Database\Migration\SqlMigration;

final class SeedContactsTableMigration extends SqlMigration
{
   public function version(): string
   {
      return '20260227_000002';
   }

   public function description(): string
   {
      return 'Seed contacts table with initial records';
   }

   protected function buildUp(): void
   {
      $this->addSql(<<<'SQL'
INSERT INTO contacts (id, first_name, last_name, phone, address, age)
SELECT 1, 'Ada', 'Lovelace', '+1-555-0100', 'Analytical St', 36
WHERE NOT EXISTS (SELECT 1 FROM contacts WHERE id = 1)
SQL);

      $this->addSql(<<<'SQL'
INSERT INTO contacts (id, first_name, last_name, phone, address, age)
SELECT 2, 'Grace', 'Hopper', '+1-555-0101', 'Compiler Ave', 37
WHERE NOT EXISTS (SELECT 1 FROM contacts WHERE id = 2)
SQL);

      $this->addSql(<<<'SQL'
SELECT setval(
   pg_get_serial_sequence('contacts', 'id'),
   COALESCE((SELECT MAX(id) FROM contacts), 1),
   true
)
SQL);
   }

   protected function buildDown(): void
   {
      $this->addDownSql('DELETE FROM contacts WHERE id IN (1, 2)');
   }
}
