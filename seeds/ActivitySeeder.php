<?php

use Phinx\Seed\AbstractSeed;

class ActivitySeeder extends AbstractSeed
{
    private function arrayGetRandom($array, $count = 1)
    {
        $i = array_rand($array, $count);
        if ($count == 1) {
            return [$array[$i]];
        }
        return array_map(function ($i) use ($array) {
            return $array[$i];
        }, $i);
    }

    public function run()
    {
        $words = require 'words.php';

        $userTable = $this->getAdapter()->getAdapterTableName('user');
        $users = $this->fetchAll("SELECT * from \"$userTable\"");

        $tagTable = $this->getAdapter()->getAdapterTableName('tag');
        $tags = $this->fetchAll("SELECT * FROM $tagTable");

        $activityTagData = [];

        foreach ($users as $user) {
            $time = time();
            for ($j = 0; $j < 100; $j++) {
                $duration = random_int(60, 60 * 60 * 4);
                $startedAt = date('Y-m-d H:i:s', $time - $duration);
                $finishedAt = date('Y-m-d H:i:s', $time);
                $activityData = [
                    'user_id' => $user['id'],
                    'title' => implode(' ', $this->arrayGetRandom($words, random_int(1, 2))),
                    'period' => "[$startedAt,$finishedAt)",
                ];
                $this->table('activity')->insert($activityData)->save();
                $activityId = $this->getAdapter()->getConnection()->lastInsertId();
                $activityTagData = array_merge($activityTagData, array_map(function ($tag) use ($activityId) {
                    return [
                        'activity_id' => $activityId,
                        'tag_id' => $tag['id'],
                    ];
                }, $this->arrayGetRandom($tags, random_int(1, 3))));
                $time -= $duration + random_int(0, 60 * 60 * 24);
            }
        }

        $this->table('activity_tag')->insert($activityTagData)->save();
    }
}
