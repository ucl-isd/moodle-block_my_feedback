@block @block_my_feedback @javascript
Feature: Marker view shows a maximum of 5 items ordered by due date

  Scenario: Marker view shows a maximum of 5 items ordered by due date
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

    And the following "activities" exist:
      | activity | name     | course | duedate              | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled | submissiondrafts | assessment_type |
      | assign   | Assign 1 | C1     | ##tomorrow##         | 1                                  | 1                               | 0                | 1               |
      | assign   | Assign 2 | C1     | ##tomorrow + 1 day## | 1                                  | 1                               | 0                | 1               |
      | assign   | Assign 3 | C1     | ##tomorrow + 2 day## | 1                                  | 1                               | 0                | 1               |
      | assign   | Assign 4 | C1     | ##tomorrow + 3 day## | 1                                  | 1                               | 0                | 1               |
      | assign   | Assign 5 | C1     | ##tomorrow + 4 day## | 1                                  | 1                               | 0                | 1               |
      | assign   | Assign 6 | C1     | ##tomorrow + 5 day## | 1                                  | 1                               | 0                | 1               |
    And the following "mod_assign > submissions" exist:
      | assign          | user     | onlinetext         |
      | Assign 1        | student1 | Submission 1 text. |
      | Assign 2        | student1 | Submission 2 text. |
      | Assign 3        | student1 | Submission 3 text. |
      | Assign 4        | student1 | Submission 4 text. |
      | Assign 5        | student1 | Submission 5 text. |
      | Assign 6        | student1 | Submission 6 text. |

    And I am logged in as "teacher1"
    And I follow "Dashboard"

    Then I should see "Marking for Teacher"
    And I should see "Assign 1" in the "My feedback" "block"
    And I should see "Assign 2" in the "My feedback" "block"
    And I should see "Assign 3" in the "My feedback" "block"
    And I should see "Assign 4" in the "My feedback" "block"
    And I should see "Assign 5" in the "My feedback" "block"
    But I should not see "Assign 6" in the "My feedback" "block"

    And "Assign 1" "text" should appear before "Assign 2" "text"
    And "Assign 2" "text" should appear before "Assign 3" "text"
    And "Assign 3" "text" should appear before "Assign 4" "text"
    And "Assign 4" "text" should appear before "Assign 5" "text"
