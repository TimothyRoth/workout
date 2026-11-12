<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

/**
 * Display order of exercises within a workout and of sets within an exercise ("position" columns).
 * Table and column names only ever come from the repositories, never from input.
 */
final class Positions
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Give the siblings positions 1..n in their current display order. Returns id => position.
     *
     * @return array<int, int>
     */
    public function renumber(string $table, string $parentColumn, int $parentId): array
    {
        $ids = $this->db->fetchFirstColumn("SELECT id FROM $table WHERE $parentColumn = :parent ORDER BY position, id", ['parent' => $parentId]);

        $positions = [];
        foreach ($ids as $i => $id) {
            $positions[(int) $id] = $i + 1;
            $this->db->executeStatement("UPDATE $table SET position = :position WHERE id = :id", ['position' => $i + 1, 'id' => $id]);
        }

        return $positions;
    }

    /**
     * New order; false (and nothing changed) when $ids is not exactly the current set of siblings.
     *
     * @param list<int|string> $ids
     */
    public function reorder(string $table, string $parentColumn, int $parentId, array $ids): bool
    {
        $this->db->beginTransaction();
        try {
            $current = array_keys($this->renumber($table, $parentColumn, $parentId));
            $wanted = array_map('intval', $ids);

            $sortedCurrent = $current;
            $sortedWanted = $wanted;
            sort($sortedCurrent);
            sort($sortedWanted);
            if ($sortedCurrent !== $sortedWanted) {
                $this->db->rollBack();

                return false;
            }

            foreach ($wanted as $i => $id) {
                $this->db->executeStatement("UPDATE $table SET position = :position WHERE id = :id", ['position' => $i + 1, 'id' => $id]);
            }
            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
