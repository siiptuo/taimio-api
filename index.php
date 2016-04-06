<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';

function getTags($pdo, $tagId) {
    $sth = $pdo->prepare('SELECT array_to_json(array_agg(tag2.title) || tag1.title) FROM tag tag1 LEFT JOIN related_tag ON related_tag.tag_id = tag1.id LEFT JOIN tag AS tag2 ON tag2.id = related_tag.related_tag_id WHERE tag1.id = ? GROUP BY tag1.id');
    $sth->execute([$tagId]);
    return array_filter(json_decode($sth->fetchColumn()));
}

$container = new Slim\Container([
    'settings' => [
        'displayErrorDetails' => true,
    ]
]);

$container['db'] = function () {
    return new PDO('pgsql:dbname=tiima', 'tuomas', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
};

$app = new Slim\App($container);

$app->get('/activities', function(Request $request, Response $response) {
    $activities = [];
    foreach ($this->db->query('SELECT activity.*, json_agg(activity_tag.tag_id) AS tags FROM activity LEFT JOIN activity_tag ON activity_tag.activity_id = activity.id GROUP BY activity.id ORDER BY started_at DESC') as $row) {
        if ($row['tags'] === '[null]') {
            $row['tags'] = [];
        } else {
            $tagIds = json_decode($row['tags']);
            $row['tags'] = [];
            foreach ($tagIds as $tagId) {
                $row['tags'] = array_unique(array_merge($row['tags'], getTags($this->db, $tagId)));
            }
            $row['tags'] = array_values($row['tags']);
        }
        $row['started_at'] = (new DateTime($row['started_at']))->format(DateTime::ISO8601);
        if (isset($row['finished_at'])) {
            $row['finished_at'] = (new DateTime($row['finished_at']))->format(DateTime::ISO8601);
        }
        $activities[] = $row;
    }

    return $response->withJson($activities);
});

$app->get('/activities/{id}', function(Request $request, Response $response, array $args) {
    $sth = $this->db->prepare('SELECT activity.*, json_agg(activity_tag.tag_id) AS tags FROM activity LEFT JOIN activity_tag ON activity_tag.activity_id = activity.id WHERE activity.id = ? GROUP BY activity.id ORDER BY started_at DESC');
    $sth->execute([$args['id']]);
    $row = $sth->fetch();
    if ($row['tags'] === '[null]') {
        $row['tags'] = [];
    } else {
        $tagIds = json_decode($row['tags']);
        $row['tags'] = [];
        foreach ($tagIds as $tagId) {
            $row['tags'] = array_unique(array_merge($row['tags'], getTags($this->db, $tagId)));
        }
        $row['tags'] = array_values($row['tags']);
    }
    $row['started_at'] = (new DateTime($row['started_at']))->format(DateTime::ISO8601);
    if (isset($row['finished_at'])) {
        $row['finished_at'] = (new DateTime($row['finished_at']))->format(DateTime::ISO8601);
    }

    return $response->withJson($row);
});

$app->post('/activities', function(Request $request, Response $response) {
    try {
        $this->db->beginTransaction();

        $data = $request->getParsedBody();

        $activity = [];
        $activity['title'] = $data['title'];
        $activity['started_at'] = $data['started_at'];
        $activity['finished_at'] = $data['finished_at'];
        $activity['tags'] = $data['tags'];

        $sth = $this->db->prepare('INSERT INTO activity (title, started_at, finished_at) VALUES (?, ?, ?) RETURNING id');
        $sth->execute([
            $activity['title'],
            $activity['started_at'],
            $activity['finished_at'],
        ]);
        $activity['id'] = $sth->fetchColumn();

        if (count($activity['tags']) > 0) {
            $tags = [];
            foreach ($activity['tags'] as $tag) {
                $sth = $this->db->prepare('INSERT INTO tag (title) VALUES (?) ON CONFLICT DO NOTHING RETURNING id');
                $sth->execute([$tag]);
                $id = $sth->fetchColumn();
                if ($id === false) {
                    $sth = $this->db->prepare('SELECT id FROM tag WHERE LOWER(title) = LOWER(?)');
                    $sth->execute([$tag]);
                    $id = $sth->fetchColumn();
                }
                $tags[] = [
                    'id' => $id,
                    'title' => $tag,
                ];
            }

            $placeholders = substr(str_repeat('(?, ?), ', count($tags)), 0, -2);
            $sth = $this->db->prepare("INSERT INTO activity_tag (activity_id, tag_id) VALUES $placeholders ON CONFLICT DO NOTHING");
            $params = [];
            foreach ($tags as $tag) {
                array_push($params, $activity['id'], $tag['id']);
            }
            $sth->execute($params);
        }

        $this->db->commit();

        return $response->withJson($activity);
    } catch (PDOException $e) {
        $this->db->rollBack();

        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});

$app->put('/activities/{id:\d+}', function(Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();

    $activity = [];
    $activity['id'] = intval($args['id']);
    $activity['title'] = $data['title'];
    $activity['started_at'] = $data['started_at'];
    $activity['finished_at'] = $data['finished_at'];
    $activity['tags'] = $data['tags'];

    try {
        $this->db->beginTransaction();

        $sth = $this->db->prepare('UPDATE activity SET title = ?, started_at = ?, finished_at = ? WHERE id = ?');
        $sth->execute([
            $activity['title'],
            $activity['started_at'],
            $activity['finished_at'],
            $activity['id'],
        ]);

        $sth = $this->db->prepare('DELETE FROM activity_tag WHERE activity_id = ?');
        $sth->execute([$activity['id']]);

        if (count($activity['tags']) > 0) {
            $tags = [];
            foreach ($activity['tags'] as $tag) {
                $sth = $this->db->prepare('INSERT INTO tag (title) VALUES (?) ON CONFLICT DO NOTHING RETURNING id');
                $sth->execute([$tag]);
                $id = $sth->fetchColumn();
                if ($id === false) {
                    $sth = $this->db->prepare('SELECT id FROM tag WHERE LOWER(title) = LOWER(?)');
                    $sth->execute([$tag]);
                    $id = $sth->fetchColumn();
                }
                $tags[] = [
                    'id' => $id,
                    'title' => $tag,
                ];
            }

            $placeholders = substr(str_repeat('(?, ?), ', count($activity['tags'])), 0, -2);
            $sth = $this->db->prepare("INSERT INTO activity_tag (activity_id, tag_id) VALUES $placeholders");
            $params = [];
            foreach ($tags as $tag) {
                array_push($params, $activity['id'], $tag['id']);
            }
            $sth->execute($params);
        }

        $this->db->commit();

        return $response->withJson($activity);
    } catch (PDOException $e) {
        $this->db->rollBack();

        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});

$app->delete('/activities/{id:\d+}', function(Request $request, Response $response, array $args) {
    try {
        $this->db->beginTransaction();

        $sth = $this->db->prepare('DELETE FROM activity_tag WHERE activity_id = ?');
        $sth->execute([$args['id']]);

        $sth = $this->db->prepare('DELETE FROM activity WHERE id = ?');
        $sth->execute([$args['id']]);

        $this->db->commit();

        return $response->withStatus(204);
    } catch (PDOException $e) {
        $this->db->rollBack();

        return $response->withJson(['error' => $e->getMessage()], 500);
    }
});

$app->run();
