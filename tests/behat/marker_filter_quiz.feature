@block @block_my_feedback @bmf_test
Feature: Quiz with a due date of today and an attempt to mark is shown

  Scenario: Quiz with a due date of today and an attempt to mark is shown
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
      | teacher1 | C1     | editingteacher     |
      | teacher1 | C1     | uclnoneditingtutor |
      | student1 | C1     | student            |
    And the following "blocks" exist:
      | blockname   | contextlevel | reference | pagetypepattern | defaultregion | defaultweight |
      | my_feedback | system       |           | my-index        | content       | 0             |
    And the following "activity" exists:
      | activity        | quiz          |
      | name            | Test quiz     |
      | course          | C1            |
      | timeopen        | ##yesterday## |
      | timeclose       | ##now##       |
      | assessment_type | 1             |
    And the following quiz attempts exist:
      | quiz      | user     |
      | Test quiz | student1 |
    And I am logged in as "teacher1"
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test quiz"
