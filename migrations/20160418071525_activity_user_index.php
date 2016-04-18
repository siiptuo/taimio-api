<?php

use Phinx\Migration\AbstractMigration;

class ActivityUserIndex extends AbstractMigration
{
    public function change()
    {
        $activity = $this->table('activity');
        $activity->addIndex('user_id')->update();
    }
}
