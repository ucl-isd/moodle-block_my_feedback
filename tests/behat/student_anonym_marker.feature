@block @block_my_feedback @javascript
Feature: Student feedback hides coursework marker identity when assessor anonymity is enabled
  In order to protect anonymous marking in coursework
  As a student
  I should not see the marker identity in My feedback when assessor anonymity is enabled

  @skip_if_component_missing_mod_coursework
  Scenario Outline: Student does not see coursework marker identity when assessor anonymity is enabled
    Given the following config values are set as admin:
      | supportcoursework | 1 | report_feedback_tracker |
      | enabled           | 1 | local_assess_type       |
    And the following "custom field categories" exist:
      | name | component   | area   | itemid |
      | CLC  | core_course | course | 0      |
    And the following "custom fields" exist:
      | name        | shortname   | category | type |
      | Course Year | course_year | CLC      | text |
    And the following "courses" exist:
      | fullname | shortname | customfield_course_year |
      | Course 1 | C1        | ##now##%Y##             |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
    And the following "blocks" exist:
      | blockname   | contextlevel | reference | pagetypepattern | defaultregion | defaultweight |
      | my_feedback | system       |           | my-index        | content       | 0             |
    And the following "activity" exists:
      | activity          | coursework      |
      | course            | C1              |
      | name              | Test coursework |
      | assessment_type   | 1               |
      | deadline          | ##tomorrow##    |
      | numberofmarkers   | 1               |
      | assessoranonymity | <anonymity>     |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework      | finalisedstatus |
      | student1    | Test coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework      | assessor | stageidentifier | grade | feedbackcomment | finalised |
      | student1    | Test coursework | teacher1 | assessor_1      | 58    | Blah            | 1         |

    And I am logged in as "admin"
    And I visit the coursework page
    And I press the release marks button
    And I log out

    When I am logged in as "student1"
    And I follow "Dashboard"
    Then "My feedback" "block" should exist
    And I should see "Test coursework" in the "My feedback" "block"
    But I should <seenotsee> "Admin User" in the "My feedback" "block"

    Examples:
      | anonymity | seenotsee |
      | 0         | see       |
      | 1         | not see   |
