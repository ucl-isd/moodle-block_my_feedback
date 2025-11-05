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

    And the following "course enrolments" exist:
      | user     | course | role                |
      | teacher1 | C1     | editingteacher      |
      | teacher2 | C1     | editingteacher      |
      | teacher1 | C1     | uclnoneditingtutor  |
      | teacher2 | C1     | uclnoneditingtutor  |
      | student1 | C1     | student             |
      | student2 | C1     | student             |
      | student3 | C1     | student             |

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
      | assign          | user     | onlinetext                            |
      | Test assignment | student1 | This is a submission for assignment 1 |
      | Test assignment | student2 | This is a submission for assignment 1 |
      | Test assignment | student3 | This is a submission for assignment 1 |

    And the following "blocks" exist:
      | blockname      | contextlevel | reference | pagetypepattern | defaultregion | defaultweight |
      | my_feedback    | system       |           | my-index        | content       | 0             |

  Scenario: A Teacher should see upcoming markings for submission w/o an assigned marker.
    # As no marker has been set yet teacher1 should see all 3 submissions.
    Given I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "3 to mark"

  Scenario: teacher1 should see 2 allocated submissions.
    Given I allocate the following markers for assignment "Test assignment":
      | Student   | Marker    |
      | student1  | teacher1  |
      | student2  | teacher1  |
      | student3  | teacher2  |

    And I am logged in as "teacher1"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "2 to mark"

  Scenario: teacher2 should see 1 allocated submissions.
    Given I allocate the following markers for assignment "Test assignment":
      | Student   | Marker    |
      | student1  | teacher1  |
      | student2  | teacher1  |
      | student3  | teacher2  |

    And I am logged in as "teacher2"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should see "Marking for Teacher"
    And I should see "Test assignment"
    And I should see "1 to mark"

  Scenario: A Teacher should not see upcoming markings for submission from students where others are assigned as marker.
    Given I allocate the following markers for assignment "Test assignment":
      | Student   | Marker    |
      | student1  | teacher1  |
      | student2  | teacher1  |
      | student3  | teacher1  |

    # Now My Feedback should no longer show any submissions for teacher2
    And I am logged in as "teacher2"
    And I am on site homepage
    And I follow "Dashboard"
    Then I should not see "Marking for Teacher"
