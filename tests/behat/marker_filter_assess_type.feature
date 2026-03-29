@block @block_my_feedback
Feature: Changing assessment category from summative hides the item

  Scenario Outline: Changing assessment category from summative hides the item
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "roles" exist:
      | shortname          | name               | archetype |
      | uclnoneditingtutor | uclnoneditingtutor | teacher   |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role               |
      | teacher1 | C1     | uclnoneditingtutor |
      | student1 | C1     | student            |
    And the following "activity" exists:
      | activity                            | assign          |
      | name                                | Test assignment |
      | course                              | C1              |
      | duedate                             | ##tomorrow##    |
      | assignsubmission_onlinetext_enabled | 1               |
      | assignfeedback_comments_enabled     | 1               |
      | submissiondrafts                    | 0               |
      | assessment_type                     | <type>          |
    And the following "mod_assign > submissions" exist:
      | assign          | user     | onlinetext            |
      | Test assignment | student1 | Base test submission. |
    And the following "blocks" exist:
      | blockname   | contextlevel | reference | pagetypepattern | defaultregion | defaultweight |
      | my_feedback | system       |           | my-index        | content       | 0             |

    When I am logged in as "teacher1"
    And I follow "Dashboard"
    Then "My feedback" "block" should <existornot>

    Examples:
      | type | existornot | comment   |
      | 0    | not exist  | Formative |
      | 1    | exist      | Summative |
      | 2    | not exist  | Dummy     |
