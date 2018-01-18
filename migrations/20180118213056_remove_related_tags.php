<?php

use Phinx\Migration\AbstractMigration;

class RemoveRelatedTags extends AbstractMigration
{
    public function up()
    {
        $this->dropTable('related_tag');
    }

    public function down()
    {
        $relatedTag = $this->table('related_tag', [
            'id' => false,
            'primary_key' => ['tag_id', 'related_tag_id']
        ]);
        $relatedTag->addColumn('tag_id', 'integer')
            ->addColumn('related_tag_id', 'integer')
            ->create();
    }
}
