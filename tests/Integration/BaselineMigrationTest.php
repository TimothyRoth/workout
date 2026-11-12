<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\DatabaseTestCase;

final class BaselineMigrationTest extends DatabaseTestCase
{
    public function testAFreshDatabaseHasExactlyTheProductionSchema(): void
    {
        $production = new \PDO('sqlite::memory:');
        $production->exec(file_get_contents(dirname(__DIR__).'/Fixtures/production_schema.sql'));

        self::assertSame($this->describe($production), $this->describe($this->db->getNativeConnection()));
        self::assertSame(5, (int) $this->db->fetchOne('PRAGMA user_version'));
    }

    public function testForeignKeysAreEnforced(): void
    {
        self::assertSame(1, (int) $this->db->fetchOne('PRAGMA foreign_keys'));
    }

    private function describe(\PDO $pdo): array
    {
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name <> 'doctrine_migration_versions' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
        $schema = [];
        foreach ($tables as $table) {
            $schema[$table]['columns'] = $pdo->query("PRAGMA table_xinfo(\"$table\")")->fetchAll(\PDO::FETCH_ASSOC);
            $schema[$table]['foreign_keys'] = $pdo->query("PRAGMA foreign_key_list(\"$table\")")->fetchAll(\PDO::FETCH_ASSOC);
            $indexes = $pdo->query("PRAGMA index_list(\"$table\")")->fetchAll(\PDO::FETCH_ASSOC);
            usort($indexes, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            foreach ($indexes as $index) {
                $schema[$table]['indexes'][$index['name']] = [$index['unique'], $pdo->query("PRAGMA index_xinfo(\"{$index['name']}\")")->fetchAll(\PDO::FETCH_ASSOC)];
            }
        }

        return $schema;
    }
}
