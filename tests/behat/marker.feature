@block @block_my_feedback
Feature: As a marker I want to see only submissions to mark where I am the assigned marker
  or there is no assigned marker.

  In order to manage submissions more easily
  As a teacher
  I need to view submissions allocated to markers.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "roles" exist:
      | shortname           | name                | archetype |
      | uclnoneditingtutor  | uclnoneditingtutor  | teacher   |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | marker1  | Marker    | 1        | marker1@example.com  |

    And the following "course enrolments" exist:
      | user     | course | role                |
      | teacher1 | C1     | editingteacher      |
      | teacher2 | C1     | editingteacher      |
      | teacher1 | C1     | uclnoneditingtutor  |
      | teacher2 | C1     | uclnoneditingtutor  |
      | student1 | C1     | student             |
      | student2 | C1     | student             |
      | student3 | C1     | student             |
      | marker1  | C1     | editingteacher      |

    And the following "activities" exist:
      | activity | name             | intro             | course | duedate      | idnumber | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled | assignfeedback_editpdf_enabled  | markingworkflow | markingallocation | submissiondrafts  | Formative or summative?                           |
      | assign   | Test assignment  | Assignment intro. | C1     | ##tomorrow## | assign1  | 1                                   | 1                               | 1                               | 1               | 1                 | 0                 | Summative - counts towards the final module mark  |

    # Make assessment summative.
    And I am on the "Course 1" "course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Feedback tracker" "link"
    Then "Report" "field" should exist in the "tertiary-navigation" "region"
    And I should see "Feedback tracker" in the "tertiary-navigation" "region"
    And I should see "Test assignment"
    And I should not see "Summative"
    When I click on the "Edit" button in the "Test assignment" module
    When I set the field "assesstype" to "Summative - counts towards the final module mark"
    And I press "Save"
    Then I should see "Summative"

    And the following "mod_assign > submissions" exist:
      | assign          | user     | onlinetext                            |
      | Test assignment | student1 | This is a submission for assignment 1 |
      | Test assignment | student2 | This is a submission for assignment 1 |
      | Test assignment | student3 | This is a submission for assignment 1 |

    And the following "blocks" exist:
      | blockname      | contextlevel | reference | pagetypepattern | defaultregion | defaultweight |
      | my_feedback    | system       |           | my-index        | content       | 0             |

  @javascript
  Scenario: A Teacher should see upcoming markings for submission w/o an assigened marker.
    # As no marker has been set yet teacher1 should see all 3 submissions.
    Given I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "3 to mark"

    # As no marker has been set yet teacher2 should see all 3 submissions.
    Given I am logged in as "teacher2"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "3 to mark"

  @javascript
  Scenario: A Teacher should only see upcoming markings for submission from students that the teacher is assigned as marker.
    Given I am on the "Course 1" "course" page logged in as "teacher1"
    And I change window size to "large"

    And I follow "Test assignment"
    And I navigate to "Submissions" in current page administration

    # Assign teacher1 to student1 and student2 as marker.
    And I select the submissions of "Student 1, Student 2"
    And I click on "More" "button"
    And I click on "Allocate marker" "link"
    And I click on "Allocate marker" "button"
    And I set the field "Allocated marker" to "Teacher 1"
    And I click on "Save changes" "button"

    # Assign teacher2 to student3 as marker.
    And I select the submissions of "Student 3"
    And I click on "More" "button"
    And I click on "Allocate marker" "link"
    And I click on "Allocate marker" "button"
    And I set the field "Allocated marker" to "Teacher 2"
    And I click on "Save changes" "button"

    # Now teacher1 should see 2 allocated submissions.
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "2 to mark"
    And I log out

    # Now teacher2 should see 1 allocated submissions.
    Given I am logged in as "teacher2"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "1 to mark"

    # Allocate teacher1 as marker for student3
    And I follow "Test assignment"
    And I navigate to "Submissions" in current page administration

    And I select the submissions of "Student 3"
    And I click on "More" "button"
    And I click on "Allocate marker" "link"
    And I click on "Allocate marker" "button"
    And I set the field "Allocated marker" to "Teacher 1"
    And I click on "Save changes" "button"

    # Now My Feedback should no longer show any submissions for teacher2
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"
