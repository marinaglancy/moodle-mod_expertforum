@mod @mod_expertforum
Feature: Installation succeeds
  In order to use this plugin
  As a user
  I need the installation to work

  Scenario: Check the Plugins overview for the name of this plugin
    Given I log in as "admin"
    And I navigate to "Plugins overview" node in "Site administration > Plugins"
    Then the following should exist in the "plugins-control-panel" table:
        |Plugin name|
        |mod_expertforum|

  @javascript
  Scenario: Add an expertforum
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
      | student3 | Student | 3 | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
      | student3 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Expert forum" to section "1" and I fill the form with:
      | Expert forum name | Questions about everything |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    #And I add a new question to "Questions about everything" expertforum with:
    #  | Title | Forum post 1 |
    #  | Question | This is the body |
    #  | Tags | Php, programming |
    And I follow "Questions about everything"
    And I press "Ask question"
    And I set the following fields to these values:
      | Title | Question about stars |
      | Question | How can I count the stars? |
      | Tags | Astronomy, Counting |
    And I press "Save changes"
    And I wait to be redirected
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Questions about everything"
    And I follow "Question about stars"
    And I set the following fields to these values:
      | Your answer | It is impossible |
    And I press "Save changes"
    And I wait to be redirected
    And I should see "1 answers"
    And I log out
    And I log in as "student3"
    And I follow "Course 1"
    And I follow "Questions about everything"
    And I follow "Question about stars"
    And I set the following fields to these values:
      | Your answer | Use the telescope |
    And I press "Save changes"
    And I wait to be redirected
    And I should see "2 answers"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Questions about everything"
    And I follow "Question about stars"
    And "Use the telescope" "text" should appear after "It is impossible" "text"
    And I click on "This answer is useful" "link" in the "//div[contains(@class,'answer') and contains(.,'Use the telescope')]" "xpath_element"
    And I follow "This question does not show any research effort"
    And I follow "Question about stars"
    And "Use the telescope" "text" should appear before "It is impossible" "text"
    And I log out

