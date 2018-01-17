<?php

use Phinx\Migration\AbstractMigration;

class AddTagTable extends AbstractMigration
{
    public function up()
    {
        $tag = $this->table('tag');
        $tag->addColumn('title', 'text')->save();
        $this->execute('CREATE UNIQUE INDEX ON tag ((lower(title)))');
        $activityTag = $this->table('activity_tag', [
            'id' => false,
            'primary_key' => ['activity_id', 'tag_id']
        ]);
        $activityTag->addColumn('activity_id', 'integer')
            ->addColumn('tag_id', 'integer')
            ->save();
    }

    public function down()
    {
        $this->dropTable('tag');
        $this->dropTable('activity_tag');
    }
}
