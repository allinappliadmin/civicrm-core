<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * Test class for CRM_Contact_BAO_GroupContact BAO
 *
 * @package   CiviCRM
 * @group headless
 */
class CRM_Contact_BAO_GroupContactTest extends CiviUnitTestCase {

  /**
   * Test case for add( ).
   */
  public function testAdd() {

    //creates a test group contact by recursively creation
    //lets create 10 groupContacts for fun
    $groupContacts = CRM_Core_DAO::createTestObject('CRM_Contact_DAO_GroupContact', NULL, 10);

    //check the group contact id is not null for each of them
    foreach ($groupContacts as $gc) {
      $this->assertNotNull($gc->id);
    }

    //cleanup
    foreach ($groupContacts as $gc) {
      $gc->deleteTestObjects('CRM_Contact_DAO_GroupContact');
    }
  }

  /**
   * Test case for getGroupId( )
   */
  public function testGetGroupId() {

    //creates a test groupContact object
    //force group_id to 1 so we can compare
    $groupContact = CRM_Core_DAO::createTestObject('CRM_Contact_DAO_GroupContact');

    //check the group contact id is not null
    $this->assertNotNull($groupContact->id);

    $groupId = CRM_Core_DAO::singleValueQuery('select max(id) from civicrm_group');

    $this->assertEquals($groupContact->group_id, $groupId, 'Check for group_id');

    //cleanup
    $groupContact->deleteTestObjects('CRM_Contact_DAO_GroupContact');
  }

  /**
   *  Test case for contact search: CRM-6706, CRM-6586 Parent Group search should return contacts from child groups too.
   *
   * @throws \Exception
   */
  public function testContactSearchByParentGroup() {
    // create a parent group
    $parentGroup = $this->callAPISuccess('Group', 'create', [
      'title' => 'Parent Group',
      'description' => 'Parent Group',
      'visibility' => 'User and User Admin Only',
      'is_active' => 1,
    ]);

    // create a child group
    $childGroup = $this->callAPISuccess('Group', 'create', [
      'title' => 'Child Group',
      'description' => 'Child Group',
      'visibility' => 'User and User Admin Only',
      'parents' => $parentGroup['id'],
      'is_active' => 1,
    ]);

    // create smart group based on saved criteria Gender = Male
    $batch = $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => 'a:1:{i:0;a:5:{i:0;s:9:"gender_id";i:1;s:1:"=";i:2;i:2;i:3;i:0;i:4;i:0;}}',
    ]);
    // Create contact with Gender - Male
    $childSmartGroupContact = $this->individualCreate([
      'gender_id' => "Male",
      'first_name' => 'C',
    ], 1);
    // then create smart group
    $childSmartGroup = $this->callAPISuccess('Group', 'create', [
      'title' => 'Child Smart Group',
      'description' => 'Child Smart Group',
      'visibility' => 'User and User Admin Only',
      'saved_search_id' => $batch['id'],
      'is_active' => 1,
      'parents' => $parentGroup['id'],
    ]);

    // Create a contact within parent group
    $parentContactParams = [
      'first_name' => 'Parent1 Fname',
      'last_name' => 'Parent1 Lname',
      'group' => [$parentGroup['id'] => 1],
    ];
    $parentContact = $this->individualCreate($parentContactParams);

    // create a contact within child dgroup
    $childContactParams = [
      'first_name' => 'Child1 Fname',
      'last_name' => 'Child2 Lname',
      'group' => [$childGroup['id'] => 1],
    ];
    $childContact = $this->individualCreate($childContactParams);

    // Check if searching by parent group  returns both parent and child group contacts
    $result = $this->callAPISuccess('contact', 'get', [
      'group' => $parentGroup['id'],
    ]);
    $validContactIds = [$parentContact, $childContact];
    $resultContactIds = [];
    foreach ($result['values'] as $k => $v) {
      $resultContactIds[] = $v['contact_id'];
    }
    $this->assertEquals(3, count($resultContactIds), 'Check the count of returned values');
    $this->assertEquals([], array_diff($validContactIds, $resultContactIds), 'Check that the difference between two arrays should be blank array');

    // Check if searching by child group returns just child group contacts
    $result = $this->callAPISuccess('contact', 'get', [
      'group' => $childGroup['id'],
    ]);
    $validChildContactIds = [$childContact];
    $resultChildContactIds = [];
    foreach ($result['values'] as $k => $v) {
      $resultChildContactIds[] = $v['contact_id'];
    }
    $this->assertEquals(1, count($resultChildContactIds), 'Check the count of returned values');
    $this->assertEquals([], array_diff($validChildContactIds, $resultChildContactIds), 'Check that the difference between two arrays should be blank array');

    // Check if searching by smart child group returns just smart child group contacts
    $result = $this->callAPISuccess('contact', 'get', [
      'group' => $childSmartGroup['id'],
    ]);
    $validChildContactIds = [$childSmartGroupContact];
    $resultChildContactIds = [];
    foreach ($result['values'] as $k => $v) {
      $resultChildContactIds[] = $v['contact_id'];
    }
    $this->assertEquals(1, count($resultChildContactIds), 'Check the count of returned values');
    $this->assertEquals([], array_diff($validChildContactIds, $resultChildContactIds), 'Check that the difference between two arrays should be blank array');

    //cleanup
    $this->callAPISuccess('Contact', 'delete', ['id' => $parentContact]);
    $this->callAPISuccess('Contact', 'delete', ['id' => $childContact]);
    $this->callAPISuccess('Contact', 'delete', ['id' => $childSmartGroupContact]);
  }

  /**
   *  CRM-19698: Test case for combine contact search in regular and smart group
   */
  public function testContactCombineGroupSearch() {
    // create regular group based
    $regularGroup = $this->callAPISuccess('Group', 'create', [
      'title' => 'Regular Group',
      'description' => 'Regular Group',
      'visibility' => 'User and User Admin Only',
      'is_active' => 1,
    ]);

    // Create contact with Gender - Male
    $contact1 = $this->individualCreate([
      'gender_id' => "Male",
      'first_name' => 'A',
    ]);

    // Create contact with Gender - Male and in regular group
    $contact2 = $this->individualCreate([
      'group' => [$regularGroup['id'] => 1],
      'gender_id' => "Male",
      'first_name' => 'B',
    ], 1);

    // Create contact with Gender - Female and in regular group
    $contact3 = $this->individualCreate([
      'group' => [$regularGroup['id'] => 1],
      'gender_id' => "Female",
      'first_name' => 'C',
    ], 1);

    // create smart group based on saved criteria Gender = Male
    $batch = $this->callAPISuccess('SavedSearch', 'create', [
      'form_values' => 'a:1:{i:0;a:5:{i:0;s:9:"gender_id";i:1;s:1:"=";i:2;i:2;i:3;i:0;i:4;i:0;}}',
    ]);
    $smartGroup = $this->callAPISuccess('Group', 'create', [
      'title' => 'Smart Group',
      'description' => 'Smart Group',
      'visibility' => 'User and User Admin Only',
      'saved_search_id' => $batch['id'],
      'is_active' => 1,
    ]);

    $useCases = [
      //Case 1: Find all contacts in regular group
      [
        'form_value' => ['group' => $regularGroup['id']],
        'expected_count' => 2,
        'expected_contact' => [$contact2, $contact3],
      ],
      //Case 2: Find all contacts in smart group
      [
        'form_value' => ['group' => $smartGroup['id']],
        'expected_count' => 2,
        'expected_contact' => [$contact1, $contact2],
      ],
      //Case 3: Find all contacts in regular group and smart group
      [
        'form_value' => ['group' => ['IN' => [$regularGroup['id'], $smartGroup['id']]]],
        'expected_count' => 3,
        'expected_contact' => [$contact1, $contact2, $contact3],
      ],
    ];
    foreach ($useCases as $case) {
      $query = new CRM_Contact_BAO_Query(CRM_Contact_BAO_Query::convertFormValues($case['form_value']));
      list($select, $from, $where, $having) = $query->query();
      $groupContacts = CRM_Core_DAO::executeQuery("SELECT DISTINCT contact_a.* $from $where ORDER BY contact_a.first_name")->fetchAll();
      foreach ($groupContacts as $key => $value) {
        $groupContacts[$key] = $value['id'];
      }
      $this->assertEquals($case['expected_count'], count($groupContacts));
      $this->checkArrayEquals($case['expected_contact'], $groupContacts);
    }
  }

  /**
   *  CRM-19333: Test case for contact search on basis of group type
   */
  public function testbyGroupType() {
    $groupTypes = CRM_Core_BAO_OptionValue::getOptionValuesAssocArrayFromName('group_type');
    $mailingListGT = array_search('Mailing List', $groupTypes);
    $accessControlGT = array_search('Access Control', $groupTypes);

    // create group with group type - Mailing list
    $group1 = $this->callAPISuccess('Group', 'create', [
      'title' => 'Group 1',
      'visibility' => 'User and User Admin Only',
      'is_active' => 1,
      'group_type' => $mailingListGT,
    ]);

    // create group with group type - Access Control
    $group2 = $this->callAPISuccess('Group', 'create', [
      'title' => 'Group 2',
      'visibility' => 'User and User Admin Only',
      'is_active' => 1,
      'group_type' => $accessControlGT,
    ]);

    // create contact in 'Group 1'
    $contact1 = $this->individualCreate([
      'group' => [$group1['id'] => 1],
      'first_name' => 'A',
    ]);

    // create contact in 'Group 2'
    $contact2 = $this->individualCreate([
      'group' => [$group2['id'] => 1],
      'first_name' => 'B',
    ], 1);

    $useCases = [
      //Case 1: Find contacts in group type - Mailing List
      [
        'form_value' => ['group_type' => [$mailingListGT]],
        'expected_count' => 1,
        'expected_contact' => [$contact1],
      ],
      //Case 2: Find contacts in group type - Access Control
      [
        'form_value' => ['group_type' => [$accessControlGT]],
        'expected_count' => 1,
        'expected_contact' => [$contact2],
      ],
      //Case 3: Find contacts in group type - Mailing List or Access List
      [
        'form_value' => ['group_type' => [$mailingListGT, $accessControlGT]],
        'expected_count' => 2,
        'expected_contact' => [$contact1, $contact2],
      ],
    ];

    foreach ($useCases as $case) {
      $query = new CRM_Contact_BAO_Query(CRM_Contact_BAO_Query::convertFormValues($case['form_value']));
      list($select, $from, $where, $having) = $query->query();
      $groupContacts = CRM_Core_DAO::executeQuery("SELECT DISTINCT contact_a.id, contact_a.first_name $from $where ORDER BY contact_a.first_name")->fetchAll();
      foreach ($groupContacts as $key => $value) {
        $groupContacts[$key] = $value['id'];
      }
      $this->assertEquals($case['expected_count'], count($groupContacts));
      $this->checkArrayEquals($case['expected_contact'], $groupContacts);
    }
  }

}
