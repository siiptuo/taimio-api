<?php

use Phinx\Migration\AbstractMigration;

class UseRangeType extends AbstractMigration
{
    public function up()
    {
        $this->execute('CREATE EXTENSION btree_gist');
        $this->execute('ALTER TABLE activity ADD COLUMN period tstzrange');
        $this->execute('ALTER TABLE activity ADD EXCLUDE USING GIST (user_id WITH =, period WITH &&)');
        $activities = $this->fetchAll('SELECT * FROM activity');
        foreach ($activities as $activity) {
            $startedAt = (new DateTime($activity['started_at']))->format(DateTime::ATOM);
            $finishedAt = $activity['finished_at'] ? (new DateTime($activity['finished_at']))->format(DateTime::ATOM) : '';
            $this->execute("UPDATE activity
                SET period = '[$startedAt,$finishedAt)'
                WHERE id = {$activity['id']}");
        }
        $this->execute('ALTER TABLE activity ALTER COLUMN period SET NOT NULL');
        $this->table('activity')
            ->removeColumn('started_at')
            ->removeColumn('finished_at')
            ->save();
    }

    public function down()
    {
        $this->table('activity')
            ->addColumn('started_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'timezone' => true])
            ->addColumn('finished_at', 'timestamp', ['null' => true, 'timezone' => true])
            ->save();
        $activities = $this->fetchAll('SELECT id,
            lower(period) as started_at,
            upper(period) as finished_at
            FROM activity');
        foreach ($activities as $activity) {
            $finishedAt = $activity['finished_at'] ? "'{$activity['finished_at']}'" : 'NULL';
            $this->execute("UPDATE activity
                SET started_at = '${activity['started_at']}',
                    finished_at = $finishedAt
                WHERE id = ${activity['id']}");
        }
        $this->table('activity')
            ->removeColumn('period')
            ->save();
        $this->execute('DROP EXTENSION btree_gist');
    }
}
