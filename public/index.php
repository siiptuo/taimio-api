<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

$container = new Slim\Container([
    'settings' => [
        'displayErrorDetails' => true,
    ]
]);

$container['db'] = function () {
    return new PDO('pgsql:dbname='.getenv('TAIMIO_DBNAME'), getenv('TAIMIO_USERNAME'), getenv('TAIMIO_PASSWORD'), [
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

    $token = bin2hex(random_bytes(16));

    $sth = $this->db->prepare('INSERT INTO token (token, user_id) VALUES (:token, :user_id)');
    $sth->execute([
        'token' => $token,
        'user_id' => $user['id'],
    ]);

    return $response->withJson(['token' => $token], 200);
});

$corsMiddleware = function ($request, $response, $next) {
    $response = $response->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, Authorization, Accept, Content-Type')
        ->withHeader('Access-Control-Allow-Max-Age', 60 * 60 * 24);
    return $next($request, $response);
};

$app->add($corsMiddleware);

$authMiddleware = function ($request, $response, $next) use ($container) {
    if (!$request->hasHeader('Authorization')) {
        return $response->withJson(['error' => 'no token'], 401);
    }
    list($type, $token) = explode(' ', $request->getHeaderLine('Authorization'));
    if (empty($type) || empty($token) || $type !== 'Bearer') {
        return $response->withJson(['error' => 'invalid authorization header'], 401);
    }
    try {
        $sth = $this->db->prepare('SELECT user_id FROM token WHERE token = ?');
        $sth->execute([$token]);
        $container['userId'] = $sth->fetchColumn();
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

    $sql = 'SELECT activity.id,
                   activity.title,
                   lower(activity.period) AS started_at,
                   upper(activity.period) AS finished_at,
                   COALESCE(json_agg(tag.title) FILTER (WHERE tag IS NOT NULL), \'[]\') AS tags
            FROM activity
            LEFT JOIN activity_tag ON activity_tag.activity_id = activity.id
            LEFT JOIN tag ON tag.id = activity_tag.tag_id
            WHERE user_id = ?';
    $params = [$this->userId];

    if (isset($queryParams['start_date']) || isset($queryParams['end_date'])) {
        if (!isset($queryParams['end_date'])) {
            return $response->withJson(['error' => 'end_date required'], 400);
        }
        if (!isset($queryParams['start_date'])) {
            return $response->withJson(['error' => 'start_date required'], 400);
        }
        if (!validateDate($queryParams['start_date']) || !validateDate($queryParams['end_date'])) {
            return $response->withJson(['error' => 'invalid date format'], 400);
        }
        if (new DateTime($queryParams['start_date']) > new DateTime($queryParams['end_date'])) {
            return $response->withJson(['error' => 'end_date before start_date'], 400);
        }
        $sql .= ' AND activity.period && ?';
        $params[] = "[{$queryParams['start_date']} 00:00,{$queryParams['end_date']} 24:00)";
    }

    $sql .= ' GROUP BY activity.id ORDER BY started_at DESC';

    $activities = [];
    $sth = $this->db->prepare($sql);
    $sth->execute($params);
    foreach ($sth->fetchAll() as $row) {
        $row['tags'] = json_decode($row['tags']);

        if (isset($queryParams['tag']) && !in_array($queryParams['tag'], $row['tags'])) {
            continue;
        }

        $row['started_at'] = (new DateTime($row['started_at']))->format(DateTime::ATOM);
        if (isset($row['finished_at'])) {
            $row['finished_at'] = (new DateTime($row['finished_at']))->format(DateTime::ATOM);
        }
        $activities[] = $row;
    }

    return $response->withJson($activities);
})->add($authMiddleware);

$app->get('/activities/current', function(Request $request, Response $response, array $args) {
    $sth = $this->db->prepare('SELECT activity.id,
                                      activity.title,
                                      lower(activity.period) AS started_at,
                                      upper(activity.period) AS finished_at,
                                      COALESCE(json_agg(tag.title) FILTER (WHERE tag IS NOT NULL), \'[]\') AS tags
                               FROM activity
                               LEFT JOIN activity_tag ON activity_tag.activity_id = activity.id
                               LEFT JOIN tag ON tag.id = activity_tag.tag_id
                               WHERE upper_inf(activity.period) AND activity.user_id = ?
                               GROUP BY activity.id');
    $sth->execute([$this->userId]);
    $row = $sth->fetch();
    if ($row === false) {
        return $response->withJson(['error' => 'not found'], 404);
    }
    $row['tags'] = json_decode($row['tags']);
    $row['started_at'] = (new DateTime($row['started_at']))->format(DateTime::ATOM);
    if (isset($row['finished_at'])) {
        $row['finished_at'] = (new DateTime($row['finished_at']))->format(DateTime::ATOM);
    }

    return $response->withJson($row);
})->add($authMiddleware);

$app->get('/activities/{id}', function(Request $request, Response $response, array $args) {
    $sth = $this->db->prepare('SELECT activity.id,
                                        activity.title,
                                        lower(activity.period) AS started_at,
                                        upper(activity.period) AS finished_at,
                                        COALESCE(json_agg(tag.title) FILTER (WHERE tag IS NOT NULL), \'[]\') AS tags
                               FROM activity
                               LEFT JOIN activity_tag ON activity_tag.activity_id = activity.id
                               LEFT JOIN tag ON tag.id = activity_tag.tag_id
                               WHERE activity.id = ? AND activity.user_id = ?
                               GROUP BY activity.id');
    $sth->execute([$args['id'], $this->userId]);
    $row = $sth->fetch();
    if ($row === false) {
        return $response->withJson(['error' => 'not found'], 404);
    }
    $row['tags'] = json_decode($row['tags']);
    $row['started_at'] = (new DateTime($row['started_at']))->format(DateTime::ATOM);
    if (isset($row['finished_at'])) {
        $row['finished_at'] = (new DateTime($row['finished_at']))->format(DateTime::ATOM);
    }

    return $response->withJson($row);
})->add($authMiddleware);

$app->post('/activities', function(Request $request, Response $response) {
    try {
        $this->db->beginTransaction();

        $data = $request->getParsedBody();

        $activity = [];
        $activity['title'] = $data['title'];
        $activity['started_at'] = (new DateTime($data['started_at']))->format(DateTime::ATOM);
        if (!empty($data['finished_at'])) {
            $activity['finished_at'] = (new DateTime($data['finished_at']))->format(DateTime::ATOM);
        } else {
            $activity['finished_at'] = null;
        }
        $activity['tags'] = $data['tags'];

        $sth = $this->db->prepare('INSERT INTO activity (title, period, user_id) VALUES (?, ?, ?) RETURNING id');
        $startedAt = $activity['started_at'];
        $finishedAt = $activity['finished_at'] ?? '';
        $sth->execute([
            $activity['title'],
            "[$startedAt,$finishedAt)",
            $this->userId,
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

        // Detect overlapping activities.
        if ($e->getCode() === '23P01') {
            return $response->withJson(['error' => 'overlap'], 400);
        }

        return $response->withJson(['error' => $e->getMessage()], 500);
    }
})->add($authMiddleware);

$app->put('/activities/{id:\d+}', function(Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();

    $activity = [];
    $activity['id'] = intval($args['id']);
    $activity['title'] = $data['title'];
    $activity['started_at'] = (new DateTime($data['started_at']))->format(DateTime::ATOM);
    if (!empty($data['finished_at'])) {
        $activity['finished_at'] = (new DateTime($data['finished_at']))->format(DateTime::ATOM);
    } else {
        $activity['finished_at'] = null;
    }
    $activity['tags'] = $data['tags'];

    try {
        $this->db->beginTransaction();

        $sth = $this->db->prepare('UPDATE activity SET title = ?, period = ? WHERE id = ? AND user_id = ?');
        $startedAt = $activity['started_at'];
        $finishedAt = $activity['finished_at'] ?? '';
        $sth->execute([
            $activity['title'],
            "[$startedAt,$finishedAt)",
            $activity['id'],
            $this->userId,
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

        // Detect overlapping activities.
        if ($e->getCode() === '23P01') {
            return $response->withJson(['error' => 'overlap'], 400);
        }

        return $response->withJson(['error' => $e->getMessage()], 500);
    }
})->add($authMiddleware);

$app->delete('/activities/{id:\d+}', function(Request $request, Response $response, array $args) {
    try {
        $this->db->beginTransaction();

        $sth = $this->db->prepare('SELECT * FROM activity WHERE id = ? AND user_id = ?');
        $sth->execute([
            $args['id'],
            $this->userId,
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
})->add($authMiddleware);

$app->run();
