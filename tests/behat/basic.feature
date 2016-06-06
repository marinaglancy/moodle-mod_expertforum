@mod @mod_expertforum
Feature: Add forum activities and posts
  In order to discuss topics with other users
  As a user
  I need to add expertforum activities to moodle courses

  @javascript
  Scenario: Add an expertforum
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Expert forum" to section "1" and I fill the form with:
      | Expert forum name | Test expertforum name |
      | Description | Test expertforum description |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I add a new question to "Test expertforum name" expertforum with:
      | Title | Forum post 1 |
      | Question | This is the body |
      | Tags | Php, programming |
    And I log out
