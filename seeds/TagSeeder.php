<?php

use Phinx\Seed\AbstractSeed;

class TagSeeder extends AbstractSeed
{
    public function run()
    {
        $tagData = [];
        $tagList = require 'words.php';
        shuffle($tagList);
        for ($i = 0; $i < 10; $i++) {
            $tagData[] = [
                'id' => $i,
                'title' => array_pop($tagList),
            ];
        }
        $this->table('tag')->insert($tagData)->save();
    }
}
