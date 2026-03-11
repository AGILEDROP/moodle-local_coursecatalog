@local_coursecatalog
Feature: Manage catalog page toggles
  In order to control catalog visibility safely
  As an administrator
  I need to enable or disable pages and navigation links from the manage screen

  Background:
    Given the following "categories" exist:
      | name                    | idnumber  |
      | Behat Catalog Category  | behatcat1 |
    And I log in as "admin"

  Scenario: Enabled page can be shown in primary navigation
    Given I visit "/local/coursecatalog/pages.php"
    When I set the field "Page name" to "Behat Catalog Page"
    And I set the field "Slug" to "behat-catalog-page"
    And I set the field "course_category" to "Behat Catalog Category"
    And I press "Add new page"
    And I click on "Enable page" "link"
    And I click on "Show in primary navigation" "link"
    And I am on homepage
    Then I should see "Behat Catalog Page" in the ".primary-navigation" "css_element"
    When I select "Behat Catalog Page" from primary navigation
    Then I should see "Behat Catalog Page"

  Scenario: Disabling an enabled page hides its primary navigation link
    Given I visit "/local/coursecatalog/pages.php"
    When I set the field "Page name" to "Behat Catalog Page 2"
    And I set the field "Slug" to "behat-catalog-page-2"
    And I set the field "course_category" to "Behat Catalog Category"
    And I press "Add new page"
    And I click on "Enable page" "link"
    And I click on "Show in primary navigation" "link"
    And I am on homepage
    And I should see "Behat Catalog Page 2" in the ".primary-navigation" "css_element"
    When I visit "/local/coursecatalog/pages.php"
    And I click on "Disable page" "link"
    And I am on homepage
    Then I should not see "Behat Catalog Page 2" in the ".primary-navigation" "css_element"

  Scenario: Navigation cannot be enabled while page is disabled
    Given I visit "/local/coursecatalog/pages.php"
    When I set the field "Page name" to "Behat Disabled Page"
    And I set the field "Slug" to "behat-disabled-page"
    And I set the field "course_category" to "Behat Catalog Category"
    And I press "Add new page"
    Then the "class" attribute of "Show in primary navigation" "link" should contain "disabled"
    And I am on homepage
    And I should not see "Behat Disabled Page" in the ".primary-navigation" "css_element"
