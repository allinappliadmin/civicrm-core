<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | Use of this source code is governed by the AGPL license with some  |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * Class CRM_Contribute_Form_TaskTest
 */
class CRM_Contribute_Form_TaskTest extends CiviUnitTestCase {

  protected $_individualId;

  /**
   * Clean up after each test.
   *
   * @throws \CRM_Core_Exception
   */
  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    parent::tearDown();
  }

  /**
   * CRM-19722 - Check CRM_Contribute_Form_Task::preProcessCommon()
   * executes without any error after sorting the search result.
   *
   * @throws \CRM_Core_Exception
   */
  public function testPreProcessCommonAfterSorting(): void {
    $fields = [
      'source' => 'contribution_source',
      'status' => 'contribution_status',
      'financialTypes' => 'financial_type',
    ];
    $contributionIds = [];
    $financialTypes = ['Member Dues', 'Event Fee', 'Donation'];
    $status = ['Completed', 'Partially paid', 'Pending'];
    $source = ['test source text', 'check source text', 'source text'];
    $this->_individualId = $this->individualCreate();

    for ($i = 0; $i < 3; $i++) {
      $contributionParams = [
        'contact_id' => $this->_individualId,
        'total_amount' => 100,
        'source' => $source[$i],
        'financial_type_id' => $financialTypes[$i],
        'contribution_status_id' => $status[$i],
      ];
      if ($status[$i] === 'Partially paid') {
        $contributionParams['contribution_status_id'] = 'Pending';
        $contributionParams['api.Payment.create'] = ['total_amount' => 50];
      }
      $contribution = $this->callAPISuccess('Contribution', 'create', $contributionParams);
      $contributionIds[] = $contribution['id'];
    }

    // Generate expected sorted array.
    $expectedValues = [];
    foreach ($fields as $key => $fld) {
      $sortedFields = array_combine($$key, $contributionIds);
      ksort($sortedFields);
      $expectedValues[$fld] = $sortedFields;
    }

    foreach ($fields as $val) {
      // Assert contribIds are returned in a sorted order.
      /** @var CRM_Contribute_Form_Task $form */
      $form = $this->getFormObject('CRM_Contribute_Form_Task', ['radio_ts' => 'ts_all'], 'Search');
      $form->set(CRM_Utils_Sort::SORT_ORDER, "`{$val}` asc");
      CRM_Contribute_Form_Task::preProcessCommon($form);

      $contribIds = array_filter(array_map('intval', $form->get('contributionIds')));
      $expectedValues = array_map('array_values', $expectedValues);

      $this->assertEquals(array_values($contribIds), $expectedValues[$val], "Failed asserting values for {$val}");
    }
  }

}
