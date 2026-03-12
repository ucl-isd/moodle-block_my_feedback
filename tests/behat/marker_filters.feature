@block @block_my_feedback
Feature: Marker dashboard filters and limits
  In order to show actionable marking items
  As a marker
  I need marking items to be filtered and limited correctly

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "roles" exist:
      | shortname          | name               | archetype |
      | uclnoneditingtutor | uclnoneditingtutor | teacher   |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role               |
      | teacher1 | C1     | editingteacher     |
      | teacher1 | C1     | uclnoneditingtutor |
      | student1 | C1     | student            |
    And the following "activity" exists:
      | activity                            | assign          |
      | name                                | Test assignment |
      | course                              | C1              |
      | duedate                             | ##tomorrow##    |
      | assignsubmission_onlinetext_enabled | 1               |
      | assignfeedback_comments_enabled     | 1               |
      | markingworkflow                     | 1               |
      | markingallocation                   | 1               |
      | submissiondrafts                    | 0               |
      | assessment_type                     | 1               |
    And the following "mod_assign > submissions" exist:
      | assign          | user     | onlinetext            |
      | Test assignment | student1 | Base test submission. |
    And the following "blocks" exist:
      | blockname   | contextlevel | reference | pagetypepattern | defaultregion | defaultweight |
      | my_feedback | system       |           | my-index        | content       | 0             |
    And I set due date of activity "Test assignment" to "now"

  Scenario Outline: Changing assessment category from summative hides the item
    Given I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"

    When I set assessment type of activity "Test assignment" to "<type>"
    And I reload the page
    Then I should not see "Marking for Teacher"

    Examples:
      | type      |
      | formative |
      | dummy     |

  Scenario: Setting due date to over 2 months in the past hides the item
    Given I set due date of activity "Test assignment" to "-3 months"
    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"

  Scenario: Setting due date to over 1 month in the future hides the item
    Given I set due date of activity "Test assignment" to "+2 months"
    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"

  Scenario: Hiding the activity hides it from marker view
    Given I set activity "Test assignment" to "hidden"
    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"

  Scenario: Hiding the course hides marker items
    Given I set course "C1" to "hidden"
    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"

  Scenario: Setting course start date in the future hides marker items
    Given I set course "C1" start date to "+1 day"
    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"

  Scenario: Setting course end date to over 3 months in the past hides marker items
    Given I set course "C1" end date to "-4 months"
    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"

  @javascript
  Scenario: Marker view shows a maximum of 5 items ordered by due date
    Given the following "activities" exist:
      | activity | name       | course | duedate      | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled | markingworkflow | markingallocation | submissiondrafts | assessment_type |
      | assign   | Assign 2    | C1     | ##tomorrow## | 1                                  | 1                               | 1               | 1                 | 0                | 1               |
      | assign   | Assign 3    | C1     | ##tomorrow## | 1                                  | 1                               | 1               | 1                 | 0                | 1               |
      | assign   | Assign 4    | C1     | ##tomorrow## | 1                                  | 1                               | 1               | 1                 | 0                | 1               |
      | assign   | Assign 5    | C1     | ##tomorrow## | 1                                  | 1                               | 1               | 1                 | 0                | 1               |
      | assign   | Assign 6    | C1     | ##tomorrow## | 1                                  | 1                               | 1               | 1                 | 0                | 1               |
    And the following "mod_assign > submissions" exist:
      | assign          | user     | onlinetext         |
      | Assign 2        | student1 | Submission 2 text. |
      | Assign 3        | student1 | Submission 3 text. |
      | Assign 4        | student1 | Submission 4 text. |
      | Assign 5        | student1 | Submission 5 text. |
      | Assign 6        | student1 | Submission 6 text. |
    And I set due date of activity "Assign 2" to "+1 day"
    And I set due date of activity "Assign 3" to "+2 day"
    And I set due date of activity "Assign 4" to "+3 day"
    And I set due date of activity "Assign 5" to "+4 day"
    And I set due date of activity "Assign 6" to "+5 day"

    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"

    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "Assign 2"
    And I should see "Assign 3"
    And I should see "Assign 4"
    And I should see "Assign 5"
    And I should not see "Assign 6"

    And "Test assignment" "text" should appear before "Assign 2" "text"
    And "Assign 2" "text" should appear before "Assign 3" "text"
    And "Assign 3" "text" should appear before "Assign 4" "text"
    And "Assign 4" "text" should appear before "Assign 5" "text"
