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

  # One press of the button has to be one press of undo, and the editor has to know
  # it has been changed: that is what every "you have unsaved work" prompt reads.
  Scenario: The description can be taken back with one undo
    Given I log in as "teacher1"
    And I open my profile in edit mode
    And I paste a picture into the "Description" TinyMCE editor
    And I select the pasted picture in the "Description" TinyMCE editor
    And the AI will answer "A cat asleep on a keyboard"
    When I click on the "Describe this image" button for the "Description" TinyMCE editor
    Then the picture in the "Description" TinyMCE editor should be described as "A cat asleep on a keyboard"
    And the "Description" TinyMCE editor should be marked as changed
    And I undo once in the "Description" TinyMCE editor
    And the picture in the "Description" TinyMCE editor should be described as "nothing"

  # Nothing may be written into the page while the request is out. The alt text is
  # content: a placeholder put there is in the page the moment somebody saves,
  # whether or not the answer ever arrives.
  Scenario: Nothing is written into the page while the AI is thinking
    Given I log in as "teacher1"
    And I open my profile in edit mode
    And I paste a picture into the "Description" TinyMCE editor
    And I select the pasted picture in the "Description" TinyMCE editor
    And the AI will never answer
    When I click on the "Describe this image" button for the "Description" TinyMCE editor
    Then the picture in the "Description" TinyMCE editor should be described as "nothing"
    And the "Description" TinyMCE editor content should contain "draftfile.php"
