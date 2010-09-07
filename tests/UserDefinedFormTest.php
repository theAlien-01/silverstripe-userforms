<?php

/**
 * @package userforms
 */

class UserDefinedFormTest extends FunctionalTest {
	
	static $fixture_file = 'userforms/tests/UserDefinedFormTest.yml';
	
	
	function testRollbackToVersion() {
		// @todo rolling back functionality (eg fields) is not supported yet
		
		$this->logInWithPermission('ADMIN');
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');

		$form->SubmitButtonText = 'Button Text';
		$form->write();
		$form->doPublish();
		$origVersion = $form->Version;
		
		$form->SubmitButtonText = 'Updated Button Text';
		$form->write();
		$form->doPublish();

		// check published site
		$updated = Versioned::get_one_by_stage("UserDefinedForm", "Stage", "\"UserDefinedForm\".\"ID\" = $form->ID");
		$this->assertEquals($updated->SubmitButtonText, 'Updated Button Text');

		$form->doRollbackTo($origVersion);
		
		$orignal = Versioned::get_one_by_stage("UserDefinedForm", "Stage", "\"UserDefinedForm\".\"ID\" = $form->ID");
		$this->assertEquals($orignal->SubmitButtonText, 'Button Text');
	}
	
	function testGetCMSFields() {
		// ensure all the tabs are present.
		// @todo a common bug with this is translations messing up the tabs.
		// @todo only logic we should check for is that the tablelistfield filter
		$this->logInWithPermission('ADMIN');
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		
		$fields = $form->getCMSFields();

		$this->assertTrue($fields->dataFieldByName('Fields') !== null);
		$this->assertTrue($fields->dataFieldByName('EmailRecipients') != null);
		$this->assertTrue($fields->dataFieldByName('Reports') != null);
		$this->assertTrue($fields->dataFieldByName('OnCompleteMessage') != null);
	}

	function testEmailRecipientPopup() {
		$this->logInWithPermission('ADMIN');
		
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		
		$popup = new UserDefinedForm_EmailRecipient();
		
		$fields = $popup->getCMSFields_forPopup();
		
		$this->assertTrue($fields->dataFieldByName('EmailSubject') !== null);
		$this->assertTrue($fields->dataFieldByName('EmailFrom') !== null);
		$this->assertTrue($fields->dataFieldByName('EmailAddress') !== null);
		$this->assertTrue($fields->dataFieldByName('HideFormData') !== null);
		$this->assertTrue($fields->dataFieldByName('SendPlain') !== null);
		$this->assertTrue($fields->dataFieldByName('EmailBody') !== null);
	
		// add an email field, it should now add a or from X address picker
		$email = $this->objFromFixture('EditableEmailField','email-field');
		$form->Fields()->add($email);
		
		$popup->Form = $form;
		$popup->write();

		$fields = $popup->getCMSFields_forPopup();
		$this->assertThat($fields->fieldByName('SendEmailToFieldID'), $this->isInstanceOf('DropdownField'));
		
		// if the front end has checkboxs or dropdown they can select from that can also be used to send things
		$dropdown = $this->objFromFixture('EditableDropdown', 'department-dropdown');
		$form->Fields()->add($dropdown);
	
		$fields = $popup->getCMSFields_forPopup();
		$this->assertTrue($fields->dataFieldByName('SendEmailToFieldID') !== null);
		
		$popup->delete();
	}
	
	function testPublishing() {
		$this->logInWithPermission('ADMIN');
		
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		$form->write();
		
		$form->doPublish();
		
		$live = Versioned::get_one_by_stage("UserDefinedForm", "Live", "\"UserDefinedForm_Live\".\"ID\" = $form->ID");
		
		$this->assertNotNull($live);
		$this->assertEquals($live->Fields()->Count(), 1);
		
		$dropdown = $this->objFromFixture('EditableDropdown', 'basic-dropdown');
		$form->Fields()->add($dropdown);
		
		$stage = Versioned::get_one_by_stage("UserDefinedForm", "Stage", "\"UserDefinedForm\".\"ID\" = $form->ID");
		$this->assertEquals($stage->Fields()->Count(), 2);
		
		// should not have published the dropdown
		$liveDropdown = Versioned::get_one_by_stage("EditableFormField", "Live", "\"EditableFormField_Live\".\"ID\" = $dropdown->ID");
		$this->assertFalse($liveDropdown);
		
		// when publishing it should have added it
		$form->doPublish();
		
		$live = Versioned::get_one_by_stage("UserDefinedForm", "Live", "\"UserDefinedForm_Live\".\"ID\" = $form->ID");
		$this->assertEquals($live->Fields()->Count(), 2);
		
		// edit the title 
		$text = $form->Fields()->First();
		
		$text->Title = 'Edited title';
		$text->write();
		
		$liveText = Versioned::get_one_by_stage("EditableFormField", "Live", "\"EditableFormField_Live\".\"ID\" = $text->ID");
		$this->assertFalse($liveText->Title == $text->Title);
		
		$form->doPublish();
		
		$liveText = Versioned::get_one_by_stage("EditableFormField", "Live", "\"EditableFormField_Live\".\"ID\" = $text->ID");
		$this->assertTrue($liveText->Title == $text->Title);
	}
	
	function testUnpublishing() {
		$this->logInWithPermission('ADMIN');
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		$form->write();
		
		$form->doPublish();

		// assert that it exists and has a field
		$live = Versioned::get_one_by_stage("UserDefinedForm", "Live", "\"UserDefinedForm_Live\".\"ID\" = $form->ID");
		
		$this->assertTrue(isset($live));
		$this->assertEquals(DB::query("SELECT COUNT(*) FROM \"EditableFormField_Live\"")->value(), 1);
		
		// unpublish
		$form->doUnpublish();
		
		$this->assertFalse(Versioned::get_one_by_stage("UserDefinedForm", "Live", "\"UserDefinedForm_Live\".\"ID\" = $form->ID"));
		$this->assertEquals(DB::query("SELECT COUNT(*) FROM \"EditableFormField_Live\"")->value(), 0);		
		
	}
	
	function testDoRevertToLive() {
		$this->logInWithPermission('ADMIN');
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		$form->SubmitButtonText = 'Button Text';
		$form->doPublish();
		$text = $form->Fields()->First();
		
		$form->SubmitButtonText = 'Edited Button Text';
		$form->write();
		
		$text->Title = 'Edited title';
		$text->write();
		
		// check that the published version is not updated
		$liveText = Versioned::get_one_by_stage("EditableFormField", "Live", "\"EditableFormField_Live\".\"ID\" = $text->ID");
		
		$revertTo = $liveText->Title;
		
		$this->assertFalse($revertTo == $text->Title);

		// revert back to the live data
		$form->doRevertToLive();
		
		$check = Versioned::get_one_by_stage("EditableFormField", "Stage", "\"EditableFormField\".\"ID\" = $text->ID");
		
		$this->assertEquals($check->Title, $revertTo);
		
		// check the edited buttoned
		$liveForm = Versioned::get_one_by_stage("UserDefinedForm", "Live", "\"UserDefinedForm_Live\".\"ID\" = $form->ID");
		$revertedForm = Versioned::get_one_by_stage("UserDefinedForm", "Stage", "\"UserDefinedForm\".\"ID\" = $form->ID");
		
		$this->assertEquals($liveForm->SubmitButtonText, $revertedForm->SubmitButtonText);
	}
	
	function testDuplicatingForm() {
		$this->logInWithPermission('ADMIN');
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		
		$duplicate = $form->duplicate();
		
		$this->assertEquals($form->Fields()->Count(), $duplicate->Fields()->Count());
		$this->assertEquals($form->EmailRecipients()->Count(), $form->EmailRecipients()->Count());
		
		// can't compare object since the dates/ids change
		$this->assertEquals($form->Fields()->First()->Title, $duplicate->Fields()->First()->Title);
	}
	
	/**
	 * @todo once getIsModifiedOnStage is implemented will need to implement this
	 */
	function testGetIsModifiedOnStage() {
		$this->logInWithPermission('ADMIN');
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		
		$this->assertTrue($form->getIsModifiedOnStage());
	}

	function testFormOptions() {
		$this->logInWithPermission('ADMIN');
		$form = $this->objFromFixture('UserDefinedForm', 'basic-form-page');
		
		$fields = $form->getFormOptions();
		$submit = $fields->fieldByName('SubmitButtonText');
		$reset = $fields->fieldByName('ShowClearButton');

		$this->assertEquals($submit->Title(), 'Text on submit button:');
		$this->assertEquals($reset->Title(), 'Show Clear Form Button');
	}
}