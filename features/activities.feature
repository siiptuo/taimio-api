Feature: Activities
    Background:
        Given there is a user named "mika"
        And user "mika" has an activity "hello world #first #test"
        And user "mika" has an activity "second activity #second"
        And there is a user named "heikki"
        And user "heikki" has an activity "my activity #hello"

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
