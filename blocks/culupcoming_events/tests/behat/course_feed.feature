@block @cul @block_culupcoming_events @block_culupcoming_events_course_feed @javascript
Feature: CUL Upcoming events block used in a course
  In order to be kept informed
  As a user
  I see a feed of relevant events in my course

  Background:
    Given the following "courses" exist:
        | fullname | shortname | category | groupmode |
        | Course 1 | C1 | 0 | 1 |
        | Course 2 | C2 | 0 | 1 |
    And the following "users" exist:
        | username | firstname | lastname | email |
        | teacher1 | Teacher | 1 | teacher1@example.com |
        | student1 | Student | 1 | student1@example.com |
    And the following "course enrolments" exist:
        | user | course | role |
        | teacher1 | C1 | editingteacher |
        | student1 | C1 | student |
        | teacher1 | C2 | editingteacher |
        | student1 | C2 | student |
    And I log in as "admin"
    And I create a calendar event with form data:
        | id_eventtype | Site |
        | id_name | My Site Event |
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the CUL Upcoming Events block
    And I add a "Assignment" to section "1" and I fill the form with:
        | Assignment name | Test assignment name 1 |
        | Description | Submit your online text |
        | assignsubmission_onlinetext_enabled | 1 |
        | assignsubmission_file_enabled | 0 |
    And I am on "Course 2" course homepage
    And I add the CUL Upcoming Events block
    And I add a "Assignment" to section "1" and I fill the form with:
        | Assignment name | Test assignment name 2 |
        | Description | Submit your online text |
        | assignsubmission_onlinetext_enabled | 1 |
        | assignsubmission_file_enabled | 0 |
    And I log out

  Scenario: Upcoming events shows current course events
    When I log in as "student1"
    And I am on "Course 1" course homepage
    # Confirm the feed is showing two notifications.
    Then I should see "1" events in feed
    # Confirm the submission event is visible.
    And I should see "Test assignment name 1 is due in C1" in the "block_culupcoming_events" "block"
    And I should not see "Test assignment name 2 is due in C2" in the "block_culupcoming_events" "block"
    And I should not see "My Site Event" in the "block_culupcoming_events" "block"
    And I am on "Course 2" course homepage
    # Confirm the feed is showing one notification.
    Then I should see "1" events in feed
    # Confirm the submission event is visible.
    And I should not see "Test assignment name 1 is due in C1" in the "block_culupcoming_events" "block"
    And I should see "Test assignment name 2 is due in C2" in the "block_culupcoming_events" "block"
    And I should not see "My Site Event" in the "block_culupcoming_events" "block"
    And I log out
