<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use Celeris\Framework\Database\Migration\SqlMigration;

final class CreateContactsTableMigration extends SqlMigration
{
   public function version(): string
   {
      return '20260227_000001';
   }

   public function description(): string
   {
      return 'Create contacts table';
   }

   protected function buildUp(): void
   {
      $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS contacts (
   id BIGSERIAL PRIMARY KEY,
   first_name VARCHAR(100) NOT NULL,
   last_name VARCHAR(100) NOT NULL,
   phone VARCHAR(32) NOT NULL,
   address VARCHAR(255) NOT NULL,
   age INTEGER NOT NULL CHECK (age >= 0),
   created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

      $this->addSql('CREATE INDEX IF NOT EXISTS contacts_last_first_idx ON contacts (last_name, first_name)');
   }

   protected function buildDown(): void
   {
      $this->addDownSql('DROP INDEX IF EXISTS contacts_last_first_idx');
      $this->addDownSql('DROP TABLE IF EXISTS contacts');
   }
}
