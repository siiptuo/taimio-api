<?php

use Phinx\Migration\AbstractMigration;

class AddToken extends AbstractMigration
{
    public function change()
    {
        $this->table('token', ['id' => false, 'primary_key' => 'token'])
            ->addColumn('token', 'string')
            ->addColumn('user_id', 'integer')
            ->addForeignKey('user_id', 'user', 'id')
            ->create();
    }
}
