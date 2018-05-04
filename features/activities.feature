Feature: Activities
    Background:
        Given there are activities:
            | user   | started_at           | finished_at          | title           | tags          |
            | mika   | 2016-05-09T10:00:00Z | 2016-05-09T11:00:00Z | hello world     | first, test   |
            | mika   | 2016-05-09T12:00:00Z | 2016-05-09T13:00:00Z | second activity | second, hello |
            | mika   | 2016-05-10T15:00:00Z | 2016-05-10T16:00:00Z | third activity  | test          |
            | heikki | 2016-05-09T10:00:00Z | 2016-05-09T10:00:00Z | my activity     | hello         |

    Scenario: Fail to list activities without token
        When I request "GET /activities"
        Then I get "401" response

    Scenario: Fail to list activities with invalid token
        Given I have token "invalid"
        When I request "GET /activities"
        Then I get "401" response
        And The response property "error" contains "invalid token"

    Scenario: List all activities
        Given I have token for user "mika"
        When I request "GET /activities"
        Then I get "200" response
        And The response is an array that contains 3 items

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
        When I request "GET /activities/4"
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

    Scenario Outline: Filter activities between dates
        Given I have token for user "mika"
        When I request "GET /activities?start_date=<start_date>&end_date=<end_date>"
        Then I get "200" response
        And The response is an array that contains <count> items

        Examples:
            | start_date | end_date   | count |
            | 2016-05-08 | 2016-05-08 | 0     |
            | 2016-05-08 | 2016-05-09 | 2     |
            | 2016-05-10 | 2016-05-11 | 1     |

    Scenario Outline: Invalid date formats
        Given I have token for user "mika"
        When I request "GET /activities?start_date=<date>&end_date=<date>"
        Then I get "400" response
        And The response property "error" contains "invalid date format"

        Examples:
            | date       |
            | 20160508   |
            | 2016-5-8   |
            | 08052016   |
            | 8.5.2016   |
            | 5/8/2016   |
            | today      |
            | now        |
            | 2016-05-32 |

    Scenario: Activities contain current activity
        Given I have token for user "heikki"
        And User "heikki" has started activity "current activity" at "2017-06-12 12:00"
        When I request "GET /activities?start_date=2017-06-12&end_date=2017-06-12"
        Then I get "200" response
        And The response is an array that contains 1 item

    Scenario: Missing end date
        Given I have token for user "mika"
        When I request "GET /activities?start_date=<date>"
        Then I get "400" response
        And The response property "error" contains "end_date required"

    Scenario: Missing start date
        Given I have token for user "mika"
        When I request "GET /activities?end_date=<date>"
        Then I get "400" response
        And The response property "error" contains "start_date required"

    Scenario: End before start date
        Given I have token for user "mika"
        When I request "GET /activities?start_date=2016-05-10&end_date=2016-05-09"
        Then I get "400" response
        And The response property "error" contains "end_date before start_date"

    Scenario: Filter activities by tag
        Given I have token for user "mika"
        When I request "GET /activities?tag=test"
        Then I get "200" response
        And The response is an array that contains 2 items

    Scenario: Filter activities by non-existing tag
        Given I have token for user "mika"
        When I request "GET /activities?tag=something"
        Then I get "200" response
        And The response is an array that contains 0 items

    Scenario: Add new activity
        Given I have token for user "mika"
        When I request "POST /activities" with body:
            """
            {
                "title": "new activity",
                "started_at": "2017-06-12T20:30:00Z",
                "finished_at": "2017-06-12T21:00:00Z",
                "tags": ["hello", "world"]
            }
            """
        Then I get "200" response
        And User user "mika" has activity "new activity"

    Scenario: Overlapping activities
        Given I have token for user "mika"
        When I request "POST /activities" with body:
            """
            {
                "title": "overlapping activity",
                "started_at": "2016-05-09T10:30:00Z",
                "finished_at": "2016-05-09T11:00:00Z",
                "tags": ["hello", "world"]
            }
            """
        Then I get "400" response
        And The response property "error" contains "overlap"

    Scenario: Multiple current activities
        Given I have token for user "mika"
        When I request "POST /activities" with body:
            """
            {
                "title": "first activity",
                "started_at": "2016-05-09T10:30:00Z",
                "finished_at": null,
                "tags": ["hello", "world"]
            }
            """
        And I request "POST /activities" with body:
            """
            {
                "title": "first activity",
                "started_at": "2016-05-09T11:00:00Z",
                "finished_at": null,
                "tags": ["hello", "world"]
            }
            """
        Then I get "400" response
        And The response property "error" contains "overlap"

    Scenario: Overlapping activities from other user
        Given I have token for user "heikki"
        When I request "POST /activities" with body:
            """
            {
                "title": "unrelated activity",
                "started_at": "2016-05-09T10:30:00Z",
                "finished_at": "2016-05-09T11:00:00Z",
                "tags": ["hello", "world"]
            }
            """
        Then I get "200" response
        And User user "heikki" has activity "unrelated activity"

    Scenario: User has no current activity
        Given I have token for user "heikki"
        When I request "GET /activities/current"
        Then I get "404" response

    Scenario: User has current activity
        Given I have token for user "heikki"
        And User "heikki" has started activity "current activity" at "2017-06-12 12:00"
        When I request "GET /activities/current"
        Then I get "200" response
        And The response property "title" contains "current activity"
