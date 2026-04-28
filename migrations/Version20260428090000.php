<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow duplicate user emails across tenants while enforcing uniqueness per tenant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_user_email');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_tenant_email ON "user" (tenant_id, email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_user_tenant_email');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON "user" (email)');
    }
}
