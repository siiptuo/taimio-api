Feature: Activities
    Background:
        Given there are activities:
            | user   | started_at       | finished_at      | title           | tags          |
            | mika   | 2016-05-09 10:00 | 2016-05-09 10:00 | hello world     | first, test   |
            | mika   | 2016-05-09 12:00 | 2016-05-09 13:00 | second activity | second, hello |
            | heikki | 2016-05-09 10:00 | 2016-05-09 10:00 | my activity     | hello         |

    Scenario: Fail to list activities
        When I request "GET /activities"
        Then I get "401" response

    Scenario: List all activities
        Given I have token for user "mika"
        When I request "GET /activities"
        Then I get "200" response
        And The response is an array that contains 2 items

    Scenario: List all activities
        Given I have token for user "heikki"
        When I request "GET /activities"
        Then I get "200" response
        And The response is an array that contains 1 item

    Scenario: Activity id
        Given I have token for user "mika"
        When I request "GET /activities/1"
        Then I get "200" response
        And The response property "title" contains "hello world"

    Scenario: Activity id
        Given I have token for user "heikki"
        When I request "GET /activities/3"
        Then I get "200" response
        And The response property "title" contains "my activity"

    Scenario: Non-existing activity id
        Given I have token for user "mika"
        When I request "GET /activities/4"
        Then I get "404" response

    Scenario: Other user's activity id
        Given I have token for user "heikki"
        When I request "GET /activities/1"
        Then I get "404" response
