<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use \Firebase\JWT\JWT;

require 'vendor/autoload.php';

define('JWT_SECRET', 'Tiima secret');

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
    return new PDO('pgsql:dbname='.getenv('TIIMA_DBNAME'), getenv('TIIMA_USERNAME'), getenv('TIIMA_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
};

$app = new Slim\App($container);

$app->post('/login', function(Request $request, Response $response) {
    $data = $request->getParsedBody();
    if (empty($data['username'])) {
        return $response->withJson(['error' => 'username required'], 400);
    }
    if (empty($data['password'])) {
        return $response->withJson(['error' => 'password required'], 400);
    }

    $sth = $this->db->prepare('SELECT * FROM "user" WHERE username = ?');
    $sth->execute([$data['username']]);
    $user = $sth->fetch();

    if ($user === false) {
        return $response->withJson(['error' => 'invalid username'], 401);
    }

    if (!password_verify($data['password'], $user['password'])) {
        return $response->withJson(['error' => 'wrong password'], 401);
    }

    $payload = ['user_id' => $user['id']];
    $token = JWT::encode($payload, JWT_SECRET);

    return $response->withJson(['token' => $token], 200);
});

$corsMiddleware = function ($request, $response, $next) {
    $response = $response->withHeader('Access-Control-Allow-Origin', 'http://tiima.dev')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, Authorization, Accept, Content-Type')
        ->withHeader('Access-Control-Allow-Max-Age', 60 * 60 * 24);
    return $next($request, $response);
};

$app->add($corsMiddleware);

$jwtMiddleware = function ($request, $response, $next) use ($container) {
    if (!$request->hasHeader('Authorization')) {
        return $response->withJson(['error' => 'no token'], 401);
    }
    list($type, $token) = explode(' ', $request->getHeaderLine('Authorization'));
    if (empty($type) || empty($token) || $type !== 'Bearer') {
        return $response->withJson(['error' => 'invalid autorization header'], 401);
    }
    try {
        $container['jwt'] = JWT::decode($token, JWT_SECRET, ['HS256']);
    } catch (Exception $e) {
        return $response->withJson(['error' => $e->getMessage()], 401);
    }
    return $next($request, $response);
};

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

$app->get('/activities', function(Request $request, Response $response) {
    $queryParams = $request->getQueryParams();

    $sql = 'SELECT activity.*, json_agg(activity_tag.tag_id) AS tags FROM activity LEFT JOIN activity_tag ON activity_tag.activity_id = activity.id WHERE user_id = ?';
    $params = [$this->jwt->user_id];

    if (isset($queryParams['start_date']) && isset($queryParams['end_date'])) {
        if (!validateDate($queryParams['start_date']) || !validateDate($queryParams['end_date'])) {
            return $response->withJson(['error' => 'invalid date format'], 400);
        }
        $sql .= ' AND started_at::date >= ? AND started_at::date <= ?';
        $params[] = $queryParams['start_date'];
        $params[] = $queryParams['end_date'];
    }

    $sql .= ' GROUP BY activity.id ORDER BY started_at DESC';

    $activities = [];
    $sth = $this->db->prepare($sql);
    $sth->execute($params);
    foreach ($sth->fetchAll() as $row) {
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

        if (isset($queryParams['tag']) && !in_array($queryParams['tag'], $row['tags'])) {
            continue;
        }

        $row['started_at'] = (new DateTime($row['started_at']))->format(DateTime::ISO8601);
        if (isset($row['finished_at'])) {
            $row['finished_at'] = (new DateTime($row['finished_at']))->format(DateTime::ISO8601);
        }
        $activities[] = $row;
    }

    return $response->withJson($activities);
})->add($jwtMiddleware);

$app->get('/activities/{id}', function(Request $request, Response $response, array $args) {
    $sth = $this->db->prepare('SELECT activity.*, json_agg(activity_tag.tag_id) AS tags FROM activity LEFT JOIN activity_tag ON activity_tag.activity_id = activity.id WHERE activity.id = ? AND activity.user_id = ? GROUP BY activity.id ORDER BY started_at DESC');
    $sth->execute([$args['id'], $this->jwt->user_id]);
    $row = $sth->fetch();
    if ($row === false) {
        return $response->withJson(['error' => 'not found'], 404);
    }
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
})->add($jwtMiddleware);

$app->post('/activities', function(Request $request, Response $response) {
    try {
        $this->db->beginTransaction();

        $data = $request->getParsedBody();

        $activity = [];
        $activity['title'] = $data['title'];
        $activity['started_at'] = $data['started_at'];
        $activity['finished_at'] = $data['finished_at'];
        $activity['tags'] = $data['tags'];

        $sth = $this->db->prepare('INSERT INTO activity (title, started_at, finished_at, user_id) VALUES (?, ?, ?, ?) RETURNING id');
        $sth->execute([
            $activity['title'],
            $activity['started_at'],
            $activity['finished_at'],
            $this->jwt->user_id,
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
})->add($jwtMiddleware);

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

        $sth = $this->db->prepare('UPDATE activity SET title = ?, started_at = ?, finished_at = ? WHERE id = ? AND user_id = ?');
        $sth->execute([
            $activity['title'],
            $activity['started_at'],
            $activity['finished_at'],
            $activity['id'],
            $this->jwt->user_id,
        ]);

        if ($sth->rowCount() !== 1) {
            return $response->withJson(['error' => 'activity not found'], 404);
        }

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
})->add($jwtMiddleware);

$app->delete('/activities/{id:\d+}', function(Request $request, Response $response, array $args) {
    try {
        $this->db->beginTransaction();

        $sth = $this->db->prepare('SELECT * FROM activity WHERE id = ? AND user_id = ?');
        $sth->execute([
            $args['id'],
            $this->jwt->user_id,
        ]);
        if ($sth->rowCount() !== 1) {
            return $response->withJson(['error' => 'activity not found'], 404);
        }

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
})->add($jwtMiddleware);

$app->run();
