@editor @editor_tiny @tiny_aimedia @javascript
Feature: Describing a picture that has just been pasted
  In order to write alt text for a screenshot I have just taken
  As somebody allowed to use AI on media
  I need the button to work on a picture I pasted rather than uploaded

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Terry     | Teacher  |
    And the following "courses" exist:
      | fullname    | shortname |
      | Test course | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "role capability" exists:
      | role                   | user  |
      | local/aimedia:use      | allow |
    And "teacher1" has accepted the AI usage policy

  # Moodle uploads a pasted picture into the draft area at once, and TinyMCE then
  # appends a timestamp to its address so the browser does not show the previous
  # picture of the same name from its cache. That question mark was being read as
  # part of the file's name, so a picture that had just been pasted came back as
  # one that had not just been added -- which is the opposite of what happened.
  Scenario: A pasted picture can be described
    Given I log in as "teacher1"
    And I open my profile in edit mode
    When I paste a picture into the "Description" TinyMCE editor
    Then the picture in the "Description" TinyMCE editor should be a "draft file"
    And the picture in the "Description" TinyMCE editor should be a "cache busted address"
    And I select the pasted picture in the "Description" TinyMCE editor
    And I click on the "Describe this image" button for the "Description" TinyMCE editor
    # Said as what should happen rather than what should not. This site has no AI
    # provider, so getting as far as looking for one is the proof that the address
    # was resolved to the file it names -- and it is an answer that only arrives
    # once the request has come back, which "should not see" would not wait for.
    Then I should see "No AI provider on this site can carry out either of these actions"
    And I should not see "That picture is not one you have just added to this editor"
