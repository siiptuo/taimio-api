Feature: Login
    Background:
        Given these is a user named "mika" with password "pa$$w0rd"

    Scenario: Success
        When I request "POST /login" with data:
            | property | value    |
            | username | mika     |
            | password | pa$$w0rd |
        Then I get "200" response
        And The response contains property "token"

    Scenario: Wrong password
        When I request "POST /login" with data:
            | property | value |
            | username | mika  |
            | password | wrong |
        Then I get "401" response
        And The response property "error" contains "wrong password"

    Scenario: Invalid username
        When I request "POST /login" with data:
            | property | value    |
            | username | pete     |
            | password | pa$$w0rd |
        Then I get "401" response
        And The response property "error" contains "invalid username"

    Scenario: Missing data
        When I request "POST /login"
        Then I get "400" response
        And The response property "error" contains "username required"

    Scenario: Missing username
        When I request "POST /login" with data:
            | property | value    |
            | password | pa$$w0rd |
        Then I get "400" response
        And The response property "error" contains "username required"

    Scenario: Missing password
        When I request "POST /login" with data:
            | property | value |
            | username | mika  |
        Then I get "400" response
        And The response property "error" contains "password required"
