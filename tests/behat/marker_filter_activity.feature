@block @block_my_feedback
Feature: Setting due date to over 2 months in the past hides the activity
  Setting due date to over 1 month in the future hides the activity
  Hiding the activity hides it from marker view

  Scenario Outline: Setting due date outside of 2 month in the past or 1 month in the future hides activity.
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
      | duedate                             | <duedate>       |
      | assignsubmission_onlinetext_enabled | 1               |
      | assignfeedback_comments_enabled     | 1               |
      | submissiondrafts                    | 0               |
      | assessment_type                     | 1               |
      | visible                             | <visible>       |
    And the following "mod_assign > submissions" exist:
      | assign          | user     | onlinetext            |
      | Test assignment | student1 | Base test submission. |
    And the following "blocks" exist:
      | blockname   | contextlevel | reference | pagetypepattern | defaultregion | defaultweight |
      | my_feedback | system       |           | my-index        | content       | 0             |
    When I am logged in as "teacher1"
    And I follow "Dashboard"
    Then I should <seenotsee> "Marking for Teacher"

    Examples:
      | duedate           | visible | seenotsee |
      | ##1 months ago##  | 1       | see       |
      | ##3 months ago##  | 1       | not see   |
      | ##+2 months##     | 1       | not see   |
      | ##tomorrow ##     | 1       | see       |
      | ##tomorrow ##     | 0       | not see   |
