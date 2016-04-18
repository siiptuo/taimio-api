<?php

use Phinx\Migration\AbstractMigration;

class AddUser extends AbstractMigration
{
    public function change()
    {
        $user = $this->table('user');
        $user->addColumn('username', 'string')
            ->addColumn('password', 'string')
            ->addIndex(['username'], ['unique' => true])
            ->create();

        $activity = $this->table('activity');
        $activity->addColumn('user_id', 'integer', ['null' => true])
            ->update();
    }
}
