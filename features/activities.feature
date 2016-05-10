Feature: Activities
    Background:
        Given there are activities:
            | user   | started_at       | finished_at      | title           | tags          |
            | mika   | 2016-05-09 10:00 | 2016-05-09 10:00 | hello world     | first, test   |
            | mika   | 2016-05-09 12:00 | 2016-05-09 13:00 | second activity | second, hello |
            | mika   | 2016-05-10 15:00 | 2016-05-10 16:00 | third activity  | test          |
            | heikki | 2016-05-09 10:00 | 2016-05-09 10:00 | my activity     | hello         |

    Scenario: Fail to list activities
        When I request "GET /activities"
        Then I get "401" response

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

    Scenario: Filter activities by date
        Given I have token for user "mika"
        When I request "GET /activities?date=2016-05-09"
        Then I get "200" response
        And The response is an array that contains 2 items

    Scenario: Filter empty activities by date
        Given I have token for user "mika"
        When I request "GET /activities?date=2016-05-08"
        Then I get "200" response
        And The response is an array that contains 0 items

    Scenario Outline: Invalid date format
        Given I have token for user "mika"
        When I request "GET /activities?date=<date>"
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
