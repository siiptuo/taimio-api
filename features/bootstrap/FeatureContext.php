<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\AfterScenarioScope;

use \Firebase\JWT\JWT;

class FeatureContext implements Context, SnippetAcceptingContext
{
    public function __construct()
    {
        $this->client = new GuzzleHttp\Client();
        $this->db = new PDO('pgsql:dbname='.getenv('TIIMA_DBNAME'), getenv('TIIMA_USERNAME'), getenv('TIIMA_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * @AfterScenario
     */
    public function after(AfterScenarioScope $scope)
    {
        $this->db->query('TRUNCATE activity RESTART IDENTITY CASCADE');
        $this->db->query('TRUNCATE "user" RESTART IDENTITY CASCADE');
        $this->db->query('TRUNCATE tag RESTART IDENTITY CASCADE');
        $this->users = [];
    }

    /**
     * @When I request :arg1
     */
    public function iRequest($arg1)
    {
        list($method, $url) = explode(' ', $arg1, 2);
        $headers = [];
        if (isset($this->token)) {
            $headers['Authorization'] = "Bearer $this->token";
        }
        $this->response = $this->client->request($method, "localhost:9000$url", [
            'http_errors' => false,
            'headers' => $headers,
        ]);
    }

    /**
     * @Then I get :statusCode response
     */
    public function iGetResponse($statusCode)
    {
        PHPUnit_Framework_Assert::assertEquals($statusCode, $this->response->getStatusCode());
    }

    /**
     * @Given there is a user named :user
     */
    public function thereIsAUserNamed($user)
    {
        $sth = $this->db->prepare('INSERT INTO "user" (username, password) VALUES (?, ?) RETURNING id');
        $sth->execute([$user, '1234']);
        $this->users[$user] = [
            'id' => $sth->fetchColumn(),
        ];
    }

    /**
     * @Given user :user has an activity :activity
     */
    public function userHasAnActivity($user, $activity)
    {
        $activity = trim($activity);
        $s = strpos($activity, '#');
        $title = trim(substr($activity, 0, $s));
        $tags = preg_split('/\s*#/', substr($activity, $s + 1));

        $sth = $this->db->prepare('INSERT INTO activity (title, started_at, finished_at, user_id) VALUES (?, ?, ?, ?)');
        $sth->execute([
            $title,
            (new DateTime())->format('Y-m-d H:i:s'),
            (new DateTime())->format('Y-m-d H:i:s'),
            $this->users[$user]['id'],
        ]);
    }

    /**
     * @Given I have token for user :user
     */
    public function iHaveTokenForUser($user)
    {
        $secret = 'Tiima secret';
        $payload = ['user_id' => $this->users[$user]['id']];
        $token = JWT::encode($payload, $secret);
        $this->token = $token;
    }

    /**
     * @Then The response is an array that contains :count item(s)
     */
    public function theResponseIsAnArrayThatContainsItems($count)
    {
        $data = json_decode($this->response->getBody(), true);
        PHPUnit_Framework_Assert::assertInternalType('array', $data);
        PHPUnit_Framework_Assert::assertCount(intval($count), $data);
    }

    /**
     * @Then The response property :property contains :value
     */
    public function theResponsePropertyContains($property, $value)
    {
        $data = json_decode($this->response->getBody(), true);
        PHPUnit_Framework_Assert::assertEquals($value, $data[$property]);
    }
}
